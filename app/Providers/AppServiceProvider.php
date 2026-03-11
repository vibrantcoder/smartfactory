<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Customer;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\ProcessMaster;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Models\WorkOrder;
use App\Domain\Shared\Enums\Role as RoleEnum;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\FactoryPolicy;
use App\Policies\MachinePolicy;
use App\Policies\PartPolicy;
use App\Policies\ProcessMasterPolicy;
use App\Policies\ProductionPlanPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkOrderPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
            WorkOrder::class      => WorkOrderPolicy::class,
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
        $this->registerIotRateLimiters();
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
    // IoT Rate Limiters
    // ─────────────────────────────────────────────────────────

    /**
     * Register per-machine rate limiters for IoT ingest endpoints.
     *
     * Each machine gets its own bucket keyed by device token hash, machine_id,
     * or IP — so a factory with 50 machines each gets independent limits and
     * one misbehaving source cannot starve the others.
     *
     * Limits:
     *   iot.ingest : 65 req/min per machine (~1/sec with a 5-second jitter buffer)
     *   iot.batch  : 10 req/min per machine (one batch every 6 seconds is ample)
     */
    private function registerIotRateLimiters(): void
    {
        // Single record ingest
        RateLimiter::for('iot.ingest', function (Request $request): Limit {
            $key = $this->iotRateLimitKey($request, $request->json()->all());

            return Limit::perMinute(65)->by($key);
        });

        // Batch ingest (up to 500 records per request)
        RateLimiter::for('iot.batch', function (Request $request): Limit {
            $payloads   = $request->json()->all();
            $firstEntry = is_array($payloads) && isset($payloads[0]) ? $payloads[0] : [];
            $key        = $this->iotRateLimitKey($request, $firstEntry);

            return Limit::perMinute(10)->by($key);
        });
    }

    /**
     * Derive a stable, per-machine rate-limit cache key from the request.
     *
     * Priority:
     *   1. X-Device-Token header  — SHA-256 hash, same as what the controller uses
     *   2. machine_id in payload  — demo / gateway mode
     *   3. Client IP              — last-resort fallback
     */
    private function iotRateLimitKey(Request $request, array $payload): string
    {
        $token = $request->header('X-Device-Token');
        if ($token !== null && $token !== '') {
            return 'iot:token:' . hash('sha256', $token);
        }

        if (!empty($payload['machine_id'])) {
            return 'iot:machine:' . (int) $payload['machine_id'];
        }

        return 'iot:ip:' . $request->ip();
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
