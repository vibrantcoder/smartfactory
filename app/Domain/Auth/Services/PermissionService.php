<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Factory\Models\Factory;
use App\Domain\Shared\Enums\Permission as PermissionEnum;
use App\Domain\Shared\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * PermissionService
 *
 * Centralises all RBAC business logic:
 *   - Factory-scoped role assignment
 *   - Privilege escalation prevention
 *   - Checkbox UI matrix building
 *   - Role-permission synchronisation
 *   - Cache invalidation
 */
class PermissionService
{
    public function __construct(
        private readonly PermissionRegistrar $registrar
    ) {}

    // ─────────────────────────────────────────────────────────
    // Role Assignment (factory-scoped)
    // ─────────────────────────────────────────────────────────

    /**
     * Assign a role to a user within a specific factory.
     *
     * SECURITY:
     *   - $assigner cannot assign a role with a higher level than their own.
     *   - Super Admin role cannot be assigned via this method.
     *   - User factory_id must match the factory being assigned to.
     *
     * @throws \DomainException on privilege escalation or cross-factory assignment.
     */
    public function assignRoleInFactory(
        User    $target,
        RoleEnum $role,
        Factory  $factory,
        User    $assigner
    ): void {
        $this->guardAgainstPrivilegeEscalation($role, $assigner);
        $this->guardAgainstCrossFactoryAssignment($target, $factory);
        $this->guardAgainstSuperAdminAssignment($role);

        // Set factory scope before assigning
        $this->registrar->setPermissionsTeamId($factory->id);

        // Remove any existing factory-scoped role first (one role per user per factory)
        $this->revokeAllFactoryRoles($target, $factory);

        $target->assignRole($role->value);

        $this->flushUserPermissionCache($target);

        $this->registrar->setPermissionsTeamId(null);
    }

    /**
     * Revoke all factory-scoped roles from a user.
     */
    public function revokeAllFactoryRoles(User $user, Factory $factory): void
    {
        $this->registrar->setPermissionsTeamId($factory->id);

        foreach (RoleEnum::cases() as $roleEnum) {
            if ($roleEnum->isFactoryScoped() && $user->hasRole($roleEnum->value)) {
                $user->removeRole($roleEnum->value);
            }
        }

        $this->flushUserPermissionCache($user);
        $this->registrar->setPermissionsTeamId(null);
    }

    /**
     * Get the user's role within a specific factory.
     */
    public function getUserRoleInFactory(User $user, Factory $factory): ?RoleEnum
    {
        $this->registrar->setPermissionsTeamId($factory->id);

        foreach (RoleEnum::cases() as $roleEnum) {
            if ($roleEnum->isFactoryScoped() && $user->hasRole($roleEnum->value)) {
                $this->registrar->setPermissionsTeamId(null);
                return $roleEnum;
            }
        }

        $this->registrar->setPermissionsTeamId(null);
        return null;
    }

    // ─────────────────────────────────────────────────────────
    // Role Permission Synchronisation
    // ─────────────────────────────────────────────────────────

    /**
     * Sync permissions on a role from an array of permission names.
     * Called by RoleController when admin submits the checkbox form.
     *
     * SECURITY:
     *   - Only Super Admin can sync permissions (enforced at controller level).
     *   - Validates that all provided permission names are valid enum values.
     *
     * @param string[] $permissionNames
     */
    public function syncRolePermissions(Role $role, array $permissionNames): void
    {
        // Validate all permission names against the enum
        $validNames = array_column(PermissionEnum::cases(), 'value');
        $invalid    = array_diff($permissionNames, $validNames);

        if (!empty($invalid)) {
            throw new \InvalidArgumentException(
                'Invalid permission names: ' . implode(', ', $invalid)
            );
        }

        // Disallow assigning permissions to super-admin role
        if ($role->name === RoleEnum::SUPER_ADMIN->value) {
            throw new \DomainException('Super Admin permissions are managed via Gate bypass, not explicit assignment.');
        }

        $this->registrar->setPermissionsTeamId(null);
        $role->syncPermissions($permissionNames);

        // Flush ALL cached permissions — role change affects every user with this role
        $this->registrar->forgetCachedPermissions();
    }

    // ─────────────────────────────────────────────────────────
    // Checkbox UI Matrix Builder
    // ─────────────────────────────────────────────────────────

    /**
     * Build the full permission matrix for the admin checkbox UI.
     *
     * Returns a structure the Blade/Vue view iterates to render
     * a grouped checkbox table:
     *
     *   [
     *     [
     *       'group_key'   => 'machine_management',
     *       'group_label' => 'Machine Management',
     *       'permissions' => [
     *         [
     *           'id'       => 7,
     *           'name'     => 'view-any.machine',
     *           'label'    => 'List Machines',
     *           'assigned' => true,
     *         ], ...
     *       ]
     *     ], ...
     *   ]
     *
     * @return array<int, array{group_key: string, group_label: string, permissions: array}>
     */
    public function getPermissionMatrixForRole(Role $role): array
    {
        $this->registrar->setPermissionsTeamId(null);

        // Eager-load role permissions once
        $assignedNames = $role->permissions->pluck('name')->flip();

        // Load all permissions once keyed by name
        $allPermissions = Permission::all()->keyBy('name');

        $matrix = [];

        foreach (PermissionEnum::groupedMatrix() as $groupKey => $group) {
            $matrixPermissions = [];

            foreach ($group['permissions'] as $permEnum) {
                $permModel = $allPermissions->get($permEnum->value);

                $matrixPermissions[] = [
                    'id'       => $permModel?->id,
                    'name'     => $permEnum->value,
                    'label'    => $permEnum->label(),
                    'assigned' => $assignedNames->has($permEnum->value),
                ];
            }

            $matrix[] = [
                'group_key'   => $groupKey,
                'group_label' => $group['label'],
                'permissions' => $matrixPermissions,
            ];
        }

        return $matrix;
    }

    /**
     * Returns a flat list of all roles with their current permission counts.
     * Used by the admin role index page.
     *
     * @return Collection<int, array{role: Role, permission_count: int, label: string, description: string}>
     */
    public function getRoleSummaries(): Collection
    {
        $this->registrar->setPermissionsTeamId(null);

        return Role::with('permissions')->get()->map(function (Role $role) {
            $roleEnum = RoleEnum::from($role->name);
            return [
                'role'             => $role,
                'label'            => $roleEnum->label(),
                'description'      => $roleEnum->description(),
                'level'            => $roleEnum->level(),
                'is_factory_scoped'=> $roleEnum->isFactoryScoped(),
                'permission_count' => $role->permissions->count(),
            ];
        })->sortByDesc('level')->values();
    }

    /**
     * Returns assignable roles for a given user (cannot escalate beyond own level).
     *
     * @return RoleEnum[]
     */
    public function getAssignableRolesFor(User $assigner): array
    {
        if ($assigner->hasRole(RoleEnum::SUPER_ADMIN->value)) {
            // Super Admin can assign all factory-scoped roles
            return array_filter(RoleEnum::cases(), fn(RoleEnum $r) => $r->isFactoryScoped());
        }

        $assignerRole = $this->detectAssignerRole($assigner);

        if ($assignerRole === null) {
            return [];
        }

        return $assignerRole->assignableRoles();
    }

    // ─────────────────────────────────────────────────────────
    // Security Guards
    // ─────────────────────────────────────────────────────────

    /**
     * Prevent privilege escalation: assigner cannot assign a role
     * with a level equal to or higher than their own.
     *
     * @throws \DomainException
     */
    private function guardAgainstPrivilegeEscalation(RoleEnum $role, User $assigner): void
    {
        if ($assigner->hasRole(RoleEnum::SUPER_ADMIN->value)) {
            return; // Super Admin is exempt
        }

        $assignerRole = $this->detectAssignerRole($assigner);

        if ($assignerRole === null || $role->level() >= $assignerRole->level()) {
            throw new \DomainException(
                "Cannot assign role [{$role->label()}]: insufficient privilege level."
            );
        }
    }

    /**
     * Prevent cross-factory assignment: target user must belong to the factory.
     *
     * @throws \DomainException
     */
    private function guardAgainstCrossFactoryAssignment(User $target, Factory $factory): void
    {
        if ($target->factory_id !== null && $target->factory_id !== $factory->id) {
            throw new \DomainException(
                "User [{$target->id}] does not belong to factory [{$factory->id}]."
            );
        }
    }

    /**
     * Super Admin role can only be assigned via RbacSeeder or console, never the UI.
     *
     * @throws \DomainException
     */
    private function guardAgainstSuperAdminAssignment(RoleEnum $role): void
    {
        if ($role === RoleEnum::SUPER_ADMIN) {
            throw new \DomainException('Super Admin role cannot be assigned via the UI.');
        }
    }

    /**
     * Detect the highest-level role the assigner holds in any factory.
     */
    private function detectAssignerRole(User $assigner): ?RoleEnum
    {
        foreach (RoleEnum::cases() as $roleEnum) {
            if ($assigner->hasRole($roleEnum->value)) {
                return $roleEnum;
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────
    // Cache Management
    // ─────────────────────────────────────────────────────────

    private function flushUserPermissionCache(User $user): void
    {
        // Spatie stores per-user permission cache; bust it after role changes
        $this->registrar->forgetCachedPermissions();

        // Also clear any application-level user cache
        Cache::forget("user.{$user->id}.permissions");
        Cache::forget("user.{$user->id}.roles");
    }
}
