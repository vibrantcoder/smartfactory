<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use App\Domain\Shared\Enums\Role as RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetFactoryPermissionScope
 *
 * Middleware that activates Spatie's team-based permission scoping
 * for every authenticated request.
 *
 * HOW IT WORKS:
 *   Spatie teams are enabled (config/permission.php: 'teams' => true).
 *   When a team ID is set on the PermissionRegistrar singleton,
 *   every hasRole() and hasPermissionTo() call is filtered by team_id.
 *
 *   For Super Admin:
 *     team_id is set to 0 (global sentinel). The Gate::before bypass
 *     in AppServiceProvider ensures they pass all permission checks.
 *
 *   For all other roles:
 *     team_id is set to the user's factory_id so that only permissions
 *     granted within that factory are considered.
 *
 * IMPORTANT — Spatie relation cache:
 *   The isSuperAdmin() helper temporarily sets team_id=0 and calls hasRole(),
 *   which loads the user's `roles` Eloquent relation (returns empty for non-admins).
 *   After that check, we call unsetRelation('roles') to clear the stale cache
 *   so subsequent permission checks use the correct factory scope.
 */
class SetFactoryPermissionScope
{
    public function __construct(
        private readonly PermissionRegistrar $registrar
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            // Unauthenticated request: clear any stale team scope
            $this->registrar->setPermissionsTeamId(null);
            return $next($request);
        }

        // Super Admin is never scoped to a factory
        if ($this->isSuperAdmin($user)) {
            $this->registrar->setPermissionsTeamId(0);
            return $next($request);
        }

        // isSuperAdmin() loaded roles with team_id=0 (empty for non-super-admins).
        // Clear the cached Eloquent relation before setting the factory scope.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // All other users: scope to their factory
        if ($user->factory_id !== null) {
            $this->registrar->setPermissionsTeamId($user->factory_id);
        } else {
            // User with no factory and no super-admin role — deny all
            $this->registrar->setPermissionsTeamId(-1); // impossible factory ID
        }

        return $next($request);
    }

    /**
     * Check super-admin using global scope (team_id=0).
     * Super-admin roles are seeded with team_id=0.
     */
    private function isSuperAdmin(mixed $user): bool
    {
        $originalTeamId = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId(0);

        $isSuperAdmin = $user->hasRole(RoleEnum::SUPER_ADMIN->value);

        $this->registrar->setPermissionsTeamId($originalTeamId);

        return $isSuperAdmin;
    }
}
