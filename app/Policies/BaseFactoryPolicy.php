<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Shared\Enums\Role as RoleEnum;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * BaseFactoryPolicy
 *
 * Abstract base for all factory-scoped policies.
 *
 * Provides shared helpers that prevent horizontal privilege escalation:
 *   - belongsToSameFactory(): ensures user and model share a factory
 *   - isSuperAdmin(): bypasses all factory constraints
 *   - hasPermissionFor(): checks Spatie permission in factory scope
 *
 * USAGE:
 *   class MachinePolicy extends BaseFactoryPolicy { ... }
 *
 * GATE BYPASS (AppServiceProvider):
 *   Gate::before(function (User $user, string $ability): ?bool {
 *       return app(BaseFactoryPolicy::class)->isSuperAdmin($user) ? true : null;
 *   });
 *
 * NOTE on before():
 *   Returning true  = always allow (super-admin)
 *   Returning null  = fall through to policy method
 *   Returning false = always deny (use for deactivated accounts)
 *
 * IMPORTANT — Spatie role relation cache:
 *   isSuperAdmin() temporarily sets team_id=0 and calls hasRole(), which loads
 *   $user->roles as an Eloquent relation (cached as empty for non-super-admins).
 *   We must call $user->unsetRelation('roles') after the check so that the
 *   subsequent hasPermissionFor() re-queries with the correct factory team_id.
 */
abstract class BaseFactoryPolicy
{
    /**
     * Global pre-check: deactivated users are denied everything.
     *
     * In Gate::before, returning null defers to the policy method.
     * We override the Spatie before() here instead.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Deactivated accounts are always denied
        if (! $user->is_active) {
            return false;
        }

        // Super Admin bypasses all policy checks
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // isSuperAdmin() loads $user->roles with team_id=0 (returns empty for
        // non-super-admins). Clear the cached relation so the subsequent
        // permission check re-queries with the correct factory-scoped team_id.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // Delegate to the individual policy method
        return null;
    }

    // ─────────────────────────────────────────────────────────
    // Shared Guards
    // ─────────────────────────────────────────────────────────

    /**
     * Verify user and subject belong to the same factory.
     * The subject must expose a `factory_id` attribute.
     *
     * @param object $subject Any model with a factory_id property
     */
    protected function belongsToSameFactory(User $user, object $subject): bool
    {
        if (!property_exists($subject, 'factory_id') && !isset($subject->factory_id)) {
            return false;
        }

        return $user->factory_id === $subject->factory_id;
    }

    /**
     * Checks whether a user has the given permission
     * scoped to their factory via Spatie teams.
     */
    protected function hasPermissionFor(User $user, string $permission): bool
    {
        $registrar = app(PermissionRegistrar::class);

        // Set team scope to user's factory
        $registrar->setPermissionsTeamId($user->factory_id);

        return $user->hasPermissionTo($permission);
    }

    /**
     * Checks super-admin status without any team constraint.
     * Uses team_id=0 (the global sentinel used when seeding super-admin).
     */
    protected function isSuperAdmin(User $user): bool
    {
        $registrar      = app(PermissionRegistrar::class);
        $originalTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId(0);
        $result = $user->hasRole(RoleEnum::SUPER_ADMIN->value);
        $registrar->setPermissionsTeamId($originalTeamId);

        return $result;
    }

    /**
     * Checks if user has a role at or above a given level.
     * Used for role-hierarchy checks inside policies.
     */
    protected function hasMinimumRole(User $user, RoleEnum $minimumRole): bool
    {
        foreach (RoleEnum::cases() as $roleEnum) {
            if ($roleEnum->level() >= $minimumRole->level() && $user->hasRole($roleEnum->value)) {
                return true;
            }
        }
        return false;
    }
}
