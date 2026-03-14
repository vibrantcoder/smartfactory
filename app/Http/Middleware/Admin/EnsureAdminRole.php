<?php

declare(strict_types=1);

namespace App\Http\Middleware\Admin;

use App\Domain\Shared\Enums\Role as RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureAdminRole
 *
 * Blocks operator and viewer roles from the admin panel.
 * Redirects them to the employee portal instead.
 */
class EnsureAdminRole
{
    private const EMPLOYEE_ONLY_ROLES = [
        RoleEnum::OPERATOR->value,
        RoleEnum::VIEWER->value,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user === null) {
            return redirect()->route('login');
        }

        // Ensure a valid Sanctum API token exists in the session.
        // The token can become invalid after migrate:fresh, DB reset, or logout
        // from another tab. Regenerate it transparently so API calls succeed.
        $this->ensureApiToken($request, $user);

        $registrar = app(PermissionRegistrar::class);
        $teamId    = $user->factory_id ?? 0;
        $registrar->setPermissionsTeamId($teamId);

        foreach (self::EMPLOYEE_ONLY_ROLES as $role) {
            if ($user->hasRole($role)) {
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $registrar->setPermissionsTeamId($teamId);
                return redirect()->route('employee.dashboard');
            }
        }

        $user->unsetRelation('roles')->unsetRelation('permissions');
        $registrar->setPermissionsTeamId($teamId);

        return $next($request);
    }

    private function ensureApiToken(Request $request, $user): void
    {
        $token = $request->session()->get('api_token');

        // Check if a token exists in the session AND still lives in the DB.
        $valid = $token && $user->tokens()->where('name', 'admin-web')->exists();

        if (! $valid) {
            // Revoke any stale admin-web tokens for this user, then issue a fresh one.
            $user->tokens()->where('name', 'admin-web')->delete();
            $newToken = $user->createToken('admin-web')->plainTextToken;
            $request->session()->put('api_token', $newToken);
        }
    }
}
