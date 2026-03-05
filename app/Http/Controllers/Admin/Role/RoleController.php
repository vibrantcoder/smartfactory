<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Role;

use App\Domain\Auth\Services\PermissionService;
use App\Domain\Shared\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\SyncPermissionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly PermissionService   $permissionService,
        private readonly PermissionRegistrar $registrar
    ) {}

    // ── index ────────────────────────────────────────────────

    public function index(Request $request): mixed
    {
        $this->authorize('viewAny', Role::class);

        $summaries = $this->buildSummaries();

        if ($request->expectsJson()) {
            return response()->json(['data' => $summaries]);
        }

        return view('admin.roles.index', [
            'apiToken'  => session('api_token'),
            'summaries' => $summaries,
            'canCreate' => $request->user()->can('create', Role::class),
        ]);
    }

    // ── store ────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => 'required|string|min:3|max:64|regex:/^[a-z][a-z0-9\-]*$/|unique:roles,name',
        ]);

        // Guard system role names
        if (in_array($data['name'], array_column(RoleEnum::cases(), 'value'), true)) {
            return response()->json(['message' => 'Cannot use a reserved system role name.'], 422);
        }

        $this->registrar->setPermissionsTeamId(null);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'sanctum']);
        $role->load('permissions');

        return response()->json([
            'message' => "Role [{$role->name}] created.",
            'data'    => $this->formatRole($role),
        ], 201);
    }

    // ── show ─────────────────────────────────────────────────

    public function show(Request $request, Role $role): mixed
    {
        $this->authorize('view', $role);

        $matrix = $this->permissionService->getPermissionMatrixForRole($role);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => array_merge(
                    $this->formatRole($role->load('permissions')),
                    ['permission_matrix' => $matrix]
                ),
            ]);
        }

        try {
            $roleEnum = RoleEnum::from($role->name);
        } catch (\ValueError) {
            $roleEnum = null;
        }

        return view('admin.roles.show', compact('role', 'matrix', 'roleEnum'));
    }

    // ── matrix ───────────────────────────────────────────────

    public function matrix(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return response()->json([
            'data' => $this->permissionService->getPermissionMatrixForRole($role),
        ]);
    }

    // ── destroy ──────────────────────────────────────────────

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if (in_array($role->name, array_column(RoleEnum::cases(), 'value'), true)) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => "Cannot delete role [{$role->name}]: users are still assigned.",
            ], 409);
        }

        $roleName = $role->name;
        $role->delete();
        $this->registrar->forgetCachedPermissions();

        return response()->json(['message' => "Role [{$roleName}] deleted."]);
    }

    // ── syncPermissions ──────────────────────────────────────

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

        $role->refresh()->load('permissions');
        $matrix = $this->permissionService->getPermissionMatrixForRole($role);

        return response()->json([
            'message'           => "Permissions for [{$role->name}] updated successfully.",
            'permission_count'  => $role->permissions->count(),
            'permission_matrix' => $matrix,
        ]);
    }

    // ── Private Helpers ───────────────────────────────────────

    private function formatRole(Role $role): array
    {
        $isSystem = in_array($role->name, array_column(RoleEnum::cases(), 'value'), true);

        try {
            $enum            = RoleEnum::from($role->name);
            $label           = $enum->label();
            $description     = $enum->description();
            $level           = $enum->level();
            $isFactoryScoped = $enum->isFactoryScoped();
        } catch (\ValueError) {
            $label           = ucwords(str_replace('-', ' ', $role->name));
            $description     = null;
            $level           = 0;
            $isFactoryScoped = true;
        }

        return [
            'id'                => $role->id,
            'name'              => $role->name,
            'label'             => $label,
            'description'       => $description,
            'level'             => $level,
            'is_factory_scoped' => $isFactoryScoped,
            'is_system'         => $isSystem,
            'permission_count'  => $role->relationLoaded('permissions') ? $role->permissions->count() : 0,
        ];
    }

    private function buildSummaries(): array
    {
        $this->registrar->setPermissionsTeamId(null);

        return Role::with('permissions')->get()
            ->map(fn(Role $r) => $this->formatRole($r))
            ->sortByDesc('level')
            ->values()
            ->toArray();
    }
}
