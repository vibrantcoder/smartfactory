<?php

declare(strict_types=1);

namespace App\Http\Middleware\Employee;

use App\Domain\Shared\Enums\Role as RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureEmployeeRole
 *
 * Allows ONLY operator and viewer roles to access /employee/* routes.
 * Admin-level roles (factory-admin, production-manager, supervisor) are
 * redirected to the admin dashboard. Unauthenticated users go to /employee/login.
 */
class EnsureEmployeeRole
{
    private const ALLOWED_ROLES = [
        RoleEnum::OPERATOR->value,
        RoleEnum::VIEWER->value,
    ];

    private const ADMIN_ROLES = [
        RoleEnum::SUPER_ADMIN->value,
        RoleEnum::FACTORY_ADMIN->value,
        RoleEnum::PRODUCTION_MANAGER->value,
        RoleEnum::SUPERVISOR->value,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user === null) {
            return redirect()->route('employee.login');
        }

        if (! $user->is_active) {
            auth('web')->logout();
            return redirect()->route('employee.login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        $registrar = app(PermissionRegistrar::class);

        // Check allowed roles in the user's factory scope
        $teamId = $user->factory_id ?? 0;
        $registrar->setPermissionsTeamId($teamId);

        foreach (self::ALLOWED_ROLES as $role) {
            if ($user->hasRole($role)) {
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $registrar->setPermissionsTeamId($teamId);
                return $next($request);
            }
        }

        // Check if this is an admin-level user — redirect to admin panel
        $registrar->setPermissionsTeamId(0);
        $isSuperAdmin = $user->hasRole(RoleEnum::SUPER_ADMIN->value);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        if ($isSuperAdmin) {
            $registrar->setPermissionsTeamId(0);
            return redirect()->route('admin.dashboard');
        }

        $registrar->setPermissionsTeamId($teamId);
        foreach (self::ADMIN_ROLES as $role) {
            if ($user->hasRole($role)) {
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $registrar->setPermissionsTeamId($teamId);
                return redirect()->route('admin.dashboard');
            }
        }

        $user->unsetRelation('roles')->unsetRelation('permissions');
        $registrar->setPermissionsTeamId($teamId);

        abort(Response::HTTP_FORBIDDEN, 'You do not have access to the employee portal.');
    }
}
