<?php

use App\Http\Middleware\Api\EnsureFactoryMembership;
use App\Http\Middleware\Api\SetFactoryPermissionScope;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__ . '/../routes/web.php',
        api:      __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'factory.scope'    => SetFactoryPermissionScope::class,
            'factory.member'   => EnsureFactoryMembership::class,
            'role'             => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'       => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_perm'     => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'admin.role'       => \App\Http\Middleware\Admin\EnsureAdminRole::class,
            'employee.role'    => \App\Http\Middleware\Employee\EnsureEmployeeRole::class,
            'employee.machine' => \App\Http\Middleware\Employee\EnsureHasMachineAssigned::class,
        ]);

        $middleware->appendToGroup('api', [
            SetFactoryPermissionScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            \Spatie\Permission\Exceptions\UnauthorizedException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'  => 'You do not have the required permissions.',
                    'required' => $e->getRequiredPermissions(),
                ], 403);
            }
        });

        $exceptions->render(function (
            \Illuminate\Auth\Access\AuthorizationException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                ], 403);
            }
        });
    })->create();
