<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Customer;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\ProcessMaster;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Shared\Enums\Role as RoleEnum;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\FactoryPolicy;
use App\Policies\MachinePolicy;
use App\Policies\PartPolicy;
use App\Policies\ProcessMasterPolicy;
use App\Policies\ProductionPlanPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * AppServiceProvider
 *
 * Configures the Gate super-admin bypass and registers all model policies.
 *
 * ═══════════════════════════════════════════════════════
 * SUPER ADMIN GATE BYPASS
 * ═══════════════════════════════════════════════════════
 * Gate::before() fires BEFORE any policy method.
 * Returning `true`  → always allow (super-admin)
 * Returning `null`  → fall through to policy method
 * Returning `false` → always deny
 *
 * The bypass MUST check super-admin without any team constraint,
 * otherwise Spatie's team scoping would prevent the check from
 * finding the role if team_id is set to a factory.
 *
 * ═══════════════════════════════════════════════════════
 * POLICY REGISTRATION
 * ═══════════════════════════════════════════════════════
 * All model → policy mappings are registered here.
 * Laravel auto-discovers policies, but explicit registration
 * is more predictable in large codebases.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Model → Policy map.
     * Laravel resolves these when $this->authorize() is called.
     */
    protected array $policies = [
        Factory::class        => FactoryPolicy::class,
        Machine::class        => MachinePolicy::class,
        Customer::class       => CustomerPolicy::class,
        Part::class           => PartPolicy::class,
        ProcessMaster::class  => ProcessMasterPolicy::class,
        ProductionPlan::class => ProductionPlanPolicy::class,
        Role::class           => \App\Policies\RolePolicy::class,
        User::class           => UserPolicy::class,
    ];

    public function register(): void
    {
        // Bind singleton services
        $this->app->singleton(
            \App\Domain\Auth\Services\PermissionService::class
        );
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerSuperAdminBypass();
        $this->registerCustomGates();
    }

    // ─────────────────────────────────────────────────────────
    // Super Admin Bypass
    // ─────────────────────────────────────────────────────────

    private function registerSuperAdminBypass(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            // Guard: deactivated users are always denied
            if (! $user->is_active) {
                return false;
            }

            // Super Admin check WITHOUT team constraint
            // (team_id may be set to a factory by SetFactoryPermissionScope)
            $registrar      = app(PermissionRegistrar::class);
            $originalTeamId = $registrar->getPermissionsTeamId();

            $registrar->setPermissionsTeamId(0);
            $isSuperAdmin = $user->hasRole(RoleEnum::SUPER_ADMIN->value);
            $registrar->setPermissionsTeamId($originalTeamId);

            if (! $isSuperAdmin) {
                // Clear cached roles (loaded with team_id=0) so subsequent
                // policy checks re-query with the correct factory team_id.
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }

            // true  = always allow; null = defer to policy
            return $isSuperAdmin ? true : null;
        });
    }

    // ─────────────────────────────────────────────────────────
    // Custom Gates
    // ─────────────────────────────────────────────────────────

    private function registerCustomGates(): void
    {
        // Convenience gate: check if user has at minimum a given role level
        Gate::define('has-minimum-role', function (User $user, string $roleName): bool {
            $minimumRole = RoleEnum::from($roleName);

            foreach (RoleEnum::cases() as $roleEnum) {
                if ($roleEnum->level() >= $minimumRole->level() && $user->hasRole($roleEnum->value)) {
                    return true;
                }
            }

            return false;
        });

        // Gate for checking if the user can manage a given role
        Gate::define('manage-role', function (User $user, Role $targetRole): bool {
            $targetRoleEnum = RoleEnum::tryFrom($targetRole->name);
            if ($targetRoleEnum === null) {
                return false;
            }

            foreach (RoleEnum::cases() as $roleEnum) {
                if ($user->hasRole($roleEnum->value) && $roleEnum->level() > $targetRoleEnum->level()) {
                    return true;
                }
            }

            return false;
        });
    }

    // ─────────────────────────────────────────────────────────
    // Policy Registration
    // ─────────────────────────────────────────────────────────

    private function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
