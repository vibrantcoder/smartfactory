<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Role;

use App\Domain\Auth\Services\PermissionService;
use App\Domain\Shared\Enums\Permission as PermissionEnum;
use App\Domain\Shared\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\SyncPermissionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * RoleController
 *
 * Manages roles and their permission assignments via the admin panel.
 *
 * AUTHORIZATION: Super Admin only. Every method enforces this.
 *
 * ROUTES (routes/admin.php):
 *   GET    /admin/roles                      → index
 *   GET    /admin/roles/{role}               → show (with permission matrix)
 *   GET    /admin/roles/{role}/matrix        → matrix (JSON for Vue component)
 *   POST   /admin/roles/{role}/permissions   → syncPermissions
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService
    ) {}

    // ─────────────────────────────────────────────────────────
    // index
    // ─────────────────────────────────────────────────────────

    /**
     * List all roles with permission counts.
     *
     * AUTHORIZATION: super-admin only via middleware ('role:super-admin')
     *
     * @return \Illuminate\View\View|JsonResponse
     */
    public function index(Request $request): mixed
    {
        $this->authorize('viewAny', Role::class);

        $summaries = $this->permissionService->getRoleSummaries();

        if ($request->expectsJson()) {
            return response()->json(['data' => $summaries]);
        }

        return view('admin.roles.index', ['summaries' => $summaries]);
    }

    // ─────────────────────────────────────────────────────────
    // show
    // ─────────────────────────────────────────────────────────

    /**
     * Show a single role with full permission matrix for checkbox rendering.
     */
    public function show(Request $request, Role $role): mixed
    {
        $this->authorize('view', $role);

        $matrix  = $this->permissionService->getPermissionMatrixForRole($role);
        $roleEnum = RoleEnum::from($role->name);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'role'            => $role,
                    'label'           => $roleEnum->label(),
                    'description'     => $roleEnum->description(),
                    'is_factory_scoped' => $roleEnum->isFactoryScoped(),
                    'permission_matrix' => $matrix,
                ],
            ]);
        }

        return view('admin.roles.show', compact('role', 'matrix', 'roleEnum'));
    }

    // ─────────────────────────────────────────────────────────
    // matrix (JSON endpoint for dynamic Vue checkbox component)
    // ─────────────────────────────────────────────────────────

    /**
     * Return the permission matrix as JSON.
     * Consumed by the Vue PermissionMatrix component.
     *
     * RESPONSE SHAPE:
     *   {
     *     "data": [
     *       {
     *         "group_key":   "machine_management",
     *         "group_label": "Machine Management",
     *         "permissions": [
     *           { "id": 23, "name": "view-any.machine", "label": "List Machines", "assigned": true },
     *           { "id": 24, "name": "view.machine",     "label": "View Machine",  "assigned": true },
     *           { "id": 25, "name": "create.machine",   "label": "Create Machine","assigned": false },
     *           ...
     *         ]
     *       }, ...
     *     ]
     *   }
     */
    public function matrix(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return response()->json([
            'data' => $this->permissionService->getPermissionMatrixForRole($role),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // syncPermissions
    // ─────────────────────────────────────────────────────────

    /**
     * Sync permissions on a role from the checkbox form submission.
     *
     * REQUEST BODY (from SyncPermissionsRequest):
     *   {
     *     "permissions": [
     *       "view-any.machine",
     *       "view.machine",
     *       "create.downtime",
     *       ...
     *     ]
     *   }
     *
     * Unchecked permissions are absent from the array — they will be REMOVED.
     * This is a full sync, not a partial update.
     *
     * SECURITY:
     *   - Only super-admin can call this endpoint.
     *   - Cannot sync permissions on the super-admin role itself.
     *   - All permission names are validated against PermissionEnum.
     */
    public function syncPermissions(SyncPermissionsRequest $request, Role $role): JsonResponse
    {
        $this->authorize('syncPermissions', $role);

        try {
            $this->permissionService->syncRolePermissions(
                $role,
                $request->validated('permissions', [])
            );
        } catch (\DomainException $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Reload fresh from DB after sync
        $role->refresh()->load('permissions');
        $matrix = $this->permissionService->getPermissionMatrixForRole($role);

        return response()->json([
            'message'           => "Permissions for [{$role->name}] updated successfully.",
            'permission_count'  => $role->permissions->count(),
            'permission_matrix' => $matrix,
        ]);
    }
}
