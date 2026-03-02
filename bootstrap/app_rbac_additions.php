<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 * RBAC ADDITIONS FOR bootstrap/app.php
 * ═══════════════════════════════════════════════════════════════════
 *
 * Copy the contents of the withMiddleware() and withExceptions()
 * sections below into your existing bootstrap/app.php.
 *
 * ═══════════════════════════════════════════════════════════════════
 * STEP 1: Install Spatie Laravel Permission
 * ═══════════════════════════════════════════════════════════════════
 *   composer require spatie/laravel-permission
 *   php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
 *   php artisan migrate
 *
 * ═══════════════════════════════════════════════════════════════════
 * STEP 2: Enable Teams in config/permission.php
 * ═══════════════════════════════════════════════════════════════════
 *   'teams'            => true,
 *   'team_foreign_key' => 'team_id',    ← column name in model_has_roles pivot
 *
 * ═══════════════════════════════════════════════════════════════════
 * STEP 3: Add HasRoles to User model
 * ═══════════════════════════════════════════════════════════════════
 *   use Spatie\Permission\Traits\HasRoles;
 *
 *   class User extends Authenticatable
 *   {
 *       use HasRoles;
 *       // ...
 *   }
 *
 * ═══════════════════════════════════════════════════════════════════
 * STEP 4: Add to bootstrap/app.php
 * ═══════════════════════════════════════════════════════════════════
 */

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
        channels: __DIR__ . '/../routes/channels.php',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'role:factory-admin|super-admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ── Middleware Aliases ────────────────────────────────
        // These aliases let you use short names in route definitions.
        $middleware->alias([
            // Factory-level scoping (MUST be first in any auth group)
            'factory.scope'  => SetFactoryPermissionScope::class,

            // Cross-factory IDOR prevention
            'factory.member' => EnsureFactoryMembership::class,

            // Spatie built-ins
            'role'           => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'     => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_perm'   => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // ── Append to API group ───────────────────────────────
        // factory.scope runs on every API request AFTER auth resolves.
        // It must run before any role/permission check.
        $middleware->appendToGroup('api', [
            SetFactoryPermissionScope::class,
        ]);

        // ── Append to web group (for admin panel) ─────────────
        $middleware->appendToGroup('web', [
            SetFactoryPermissionScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Render Spatie's UnauthorizedException as 403 JSON for API consumers
        $exceptions->render(function (
            \Spatie\Permission\Exceptions\UnauthorizedException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have the required permissions.',
                    'required'=> $e->getRequiredPermissions(),
                ], 403);
            }
        });

        // Render AuthorizationException as clean JSON for API
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
    });


/**
 * ═══════════════════════════════════════════════════════════════════
 * ROUTE USAGE EXAMPLES
 * ═══════════════════════════════════════════════════════════════════
 *
 * routes/api_v1.php:
 *
 * // All routes below require auth + factory scope + factory membership
 * Route::middleware(['auth:sanctum', 'factory.scope', 'factory.member'])
 *      ->prefix('v1')
 *      ->group(function () {
 *
 *          // Spatie permission middleware — quick gate on route level
 *          Route::middleware('permission:view-any.machine')
 *               ->get('/machines', [MachineController::class, 'index']);
 *
 *          // Full resource with different permissions per verb
 *          Route::apiResource('machines', MachineController::class)
 *               ->middleware([
 *                   'index'   => 'permission:view-any.machine',
 *                   'show'    => 'permission:view.machine',
 *                   'store'   => 'permission:create.machine',
 *                   'update'  => 'permission:update.machine',
 *                   'destroy' => 'permission:delete.machine',
 *               ]);
 *
 *          // Role-based gate (alternative to permission middleware)
 *          Route::middleware('role:factory-admin|super-admin')
 *               ->apiResource('users', UserController::class);
 *
 *          // Role OR permission (either is sufficient)
 *          Route::middleware('role_or_perm:super-admin|sync-permissions.role')
 *               ->post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
 *      });
 *
 *
 * ═══════════════════════════════════════════════════════════════════
 * BLADE DIRECTIVE EXAMPLES (admin panel views)
 * ═══════════════════════════════════════════════════════════════════
 *
 * @role('super-admin')
 *     <a href="{{ route('admin.roles.index') }}">Manage Roles</a>
 * @endrole
 *
 * @hasanyrole('factory-admin|super-admin')
 *     <a href="{{ route('admin.users.index') }}">Manage Users</a>
 * @endhasanyrole
 *
 * @can('create.machine')
 *     <button>Add Machine</button>
 * @endcan
 *
 * @cannot('delete.machine')
 *     {{-- Delete button hidden --}}
 * @endcannot
 *
 *
 * ═══════════════════════════════════════════════════════════════════
 * config/permission.php — KEY SETTINGS
 * ═══════════════════════════════════════════════════════════════════
 *
 * return [
 *     'models' => [
 *         'permission' => Spatie\Permission\Models\Permission::class,
 *         'role'       => Spatie\Permission\Models\Role::class,
 *     ],
 *     'table_names' => [
 *         'roles'                 => 'roles',
 *         'permissions'           => 'permissions',
 *         'model_has_permissions' => 'model_has_permissions',
 *         'model_has_roles'       => 'model_has_roles',
 *         'role_has_permissions'  => 'role_has_permissions',
 *     ],
 *     'teams'            => true,         ← ENABLE THIS
 *     'team_foreign_key' => 'team_id',    ← Column in model_has_roles/model_has_permissions
 *     'cache' => [
 *         'expiration_time' => \DateInterval::createFromDateString('24 hours'),
 *         'key'             => 'spatie.permission.cache',
 *         'store'           => 'redis',   ← Use Redis for multi-server deployments
 *     ],
 * ];
 */
