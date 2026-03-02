<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use App\Domain\Factory\Models\Factory;
use App\Domain\Shared\Enums\Role as RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureFactoryMembership
 *
 * Verifies that the authenticated user belongs to the factory
 * referenced in the current route before permitting the request.
 *
 * PREVENTS:
 *   - Horizontal privilege escalation: User from Factory A
 *     accessing data belonging to Factory B.
 *   - IDOR attacks on factory-scoped resources.
 *
 * USAGE:
 *   Route::middleware(['auth:sanctum', 'factory.scope', 'factory.member'])
 *        ->group(function () { ... });
 *
 * ROUTE PARAMETER RESOLUTION:
 *   The middleware looks for a Factory model in route parameters.
 *   Name your route parameter `factory` or pass a factoryId parameter.
 *
 *   Route::apiResource('factories/{factory}/machines', MachineController::class);
 *   Route::get('/machines', MachineController::class)->defaults('resolveFactory', true);
 *
 * SUPER ADMIN:
 *   Bypassed entirely — Super Admin can access all factories.
 */
class EnsureFactoryMembership
{
    public function __construct(
        private readonly PermissionRegistrar $registrar
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        // Super Admin bypasses factory membership check
        if ($this->isSuperAdmin($user)) {
            return $next($request);
        }

        // isSuperAdmin() loaded $user->roles with team_id=0 (returns empty for
        // non-super-admins). Clear the stale cache so downstream permission
        // checks (Spatie Gate::before, policies) re-query with the correct
        // factory-scoped team_id set by SetFactoryPermissionScope.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $targetFactoryId = $this->resolveTargetFactoryId($request);

        // No factory in route: allow (some endpoints are not factory-specific)
        if ($targetFactoryId === null) {
            return $next($request);
        }

        // User must belong to the target factory
        if ($user->factory_id !== $targetFactoryId) {
            abort(
                Response::HTTP_FORBIDDEN,
                "Access denied: you do not have access to factory [{$targetFactoryId}]."
            );
        }

        return $next($request);
    }

    /**
     * Resolve the target factory ID from multiple possible route parameter shapes.
     */
    private function resolveTargetFactoryId(Request $request): ?int
    {
        // Route model binding: {factory} parameter resolves to Factory model
        $factory = $request->route('factory');
        if ($factory instanceof Factory) {
            return $factory->id;
        }

        // Raw integer parameter: {factory} resolves to ID
        if (is_numeric($factory)) {
            return (int) $factory;
        }

        // Explicit factoryId parameter
        $factoryId = $request->route('factoryId');
        if (is_numeric($factoryId)) {
            return (int) $factoryId;
        }

        // Request body factory_id (POST/PATCH)
        if ($request->has('factory_id')) {
            return (int) $request->input('factory_id');
        }

        return null;
    }

    /**
     * Check super-admin without team constraint applied.
     */
    private function isSuperAdmin(mixed $user): bool
    {
        $originalTeamId = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId(0);

        $result = $user->hasRole(RoleEnum::SUPER_ADMIN->value);

        $this->registrar->setPermissionsTeamId($originalTeamId);

        return $result;
    }
}
