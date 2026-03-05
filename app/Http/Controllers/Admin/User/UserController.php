<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\User;

use App\Domain\Auth\Services\PermissionService;
use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Domain\Shared\Enums\Permission;
use App\Domain\Shared\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\AssignRoleRequest;
use App\Http\Requests\Admin\User\CreateUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * UserController (Admin)
 *
 * ROUTES:
 *   GET    /admin/users                    → index
 *   POST   /admin/users                    → store   (create user)
 *   GET    /admin/users/{user}             → show
 *   PUT    /admin/users/{user}             → update  (edit user)
 *   POST   /admin/users/{user}/assign-role → assignRole
 *   DELETE /admin/users/{user}/revoke-role → revokeRole
 */
class UserController extends Controller
{
    public function __construct(
        private readonly PermissionService   $permissionService,
        private readonly PermissionRegistrar $registrar
    ) {}

    // ── index ─────────────────────────────────────────────────

    public function index(Request $request): mixed
    {
        $this->authorize('viewAny', User::class);

        $authUser = $request->user();

        if (! $request->expectsJson()) {
            $factories = $authUser->factory_id === null
                ? Factory::where('status', 'active')->orderBy('name')->get(['id', 'name'])
                : collect();

            $permGroups = collect(Permission::groupedMatrix())->map(fn ($group) => [
                'label'       => $group['label'],
                'permissions' => collect($group['permissions'])->map(fn (Permission $p) => [
                    'value' => $p->value,
                    'label' => $p->label(),
                ])->values()->toArray(),
            ])->values()->toArray();

            return view('admin.users.index', [
                'apiToken'          => session('api_token'),
                'factoryId'         => $authUser->factory_id,
                'factories'         => $factories,
                'permissionGroups'  => $permGroups,
            ]);
        }

        $query = User::query()->with(['roles']);

        if (!$this->isSuperAdmin($authUser)) {
            $query->where('factory_id', $authUser->factory_id);
        } elseif ($request->filled('factory_id')) {
            $query->where('factory_id', $request->integer('factory_id'));
        }

        $users = $query
            ->select(['id', 'name', 'email', 'factory_id', 'machine_id', 'is_active', 'created_at'])
            ->orderBy('name')
            ->paginate(25);

        $assignerRoles = $this->permissionService->getAssignableRolesFor($authUser);
        $canCreate     = $authUser->can('create', User::class);

        $users->through(function (User $user) use ($authUser, $assignerRoles) {
            $userRole = $user->roles->first();
            return [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'factory_id'   => $user->factory_id,
                'machine_id'   => $user->machine_id,
                'is_active'    => $user->is_active,
                'role'         => $userRole?->name,
                'role_label'   => $userRole ? RoleEnum::from($userRole->name)->label() : null,
                'can_reassign' => $this->canReassign($authUser, $user, $assignerRoles),
                'can_edit'     => $authUser->can('update', $user),
            ];
        });

        return response()->json(array_merge($users->toArray(), ['can_create' => $canCreate]));
    }

    // ── store (create user) ────────────────────────────────────

    public function store(CreateUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $authUser  = $request->user();
        $factoryId = $this->isSuperAdmin($authUser)
            ? $request->integer('factory_id')
            : $authUser->factory_id;

        $user = User::create([
            'name'       => $request->validated('name'),
            'email'      => $request->validated('email'),
            'password'   => Hash::make($request->validated('password')),
            'factory_id' => $factoryId,
            'is_active'  => (bool) $request->validated('is_active', true),
        ]);

        return response()->json([
            'message' => "User [{$user->name}] created successfully.",
            'data'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'factory_id' => $user->factory_id,
                'is_active'  => $user->is_active,
                'role'       => null,
                'role_label' => null,
                'can_reassign' => true,
                'can_edit'     => true,
            ],
        ], 201);
    }

    // ── show ─────────────────────────────────────────────────

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);
        $this->abortIfCrossFactory($request->user(), $user);

        // Temporarily switch to target user's factory to load their role correctly,
        // then restore the auth user's team context before calling getAssignableRolesFor.
        $originalTeamId = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($user->factory_id);
        $userRole = $user->roles->first();
        $this->registrar->setPermissionsTeamId($originalTeamId);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return response()->json([
            'data' => [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'factory_id'       => $user->factory_id,
                'is_active'        => $user->is_active,
                'role'             => $userRole?->name,
                'role_label'       => $userRole ? RoleEnum::from($userRole->name)->label() : null,
                'assignable_roles' => array_map(
                    fn(RoleEnum $r) => ['value' => $r->value, 'label' => $r->label()],
                    $this->permissionService->getAssignableRolesFor($request->user())
                ),
            ],
        ]);
    }

    // ── update (edit user) ────────────────────────────────────

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $this->abortIfCrossFactory($request->user(), $user);

        $authUser = $request->user();

        $data = [
            'name'      => $request->validated('name'),
            'email'     => $request->validated('email'),
            'is_active' => (bool) $request->validated('is_active', $user->is_active),
        ];

        // Only super-admin can move a user to a different factory
        if ($this->isSuperAdmin($authUser) && $request->filled('factory_id')) {
            $data['factory_id'] = $request->integer('factory_id');
        }

        // Only update password if explicitly provided
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->update($data);
        $user->refresh()->load('roles');

        $userRole = $user->roles->first();

        return response()->json([
            'message' => "User [{$user->name}] updated successfully.",
            'data'    => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'factory_id'   => $user->factory_id,
                'is_active'    => $user->is_active,
                'role'         => $userRole?->name,
                'role_label'   => $userRole ? RoleEnum::from($userRole->name)->label() : null,
                'can_reassign' => true,
                'can_edit'     => true,
            ],
        ]);
    }

    // ── assignRole ────────────────────────────────────────────

    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        $this->authorize('assignRole', $user);

        $authUser = $request->user();
        $this->abortIfCrossFactory($authUser, $user);

        $roleEnum = RoleEnum::from($request->validated('role'));
        $factory  = Factory::findOrFail($user->factory_id);

        try {
            $this->permissionService->assignRoleInFactory($user, $roleEnum, $factory, $authUser);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message'    => "Role [{$roleEnum->label()}] assigned to {$user->name}.",
            'role'       => $roleEnum->value,
            'role_label' => $roleEnum->label(),
        ]);
    }

    // ── assignMachine ─────────────────────────────────────────

    /**
     * POST /admin/users/{user}/assign-machine
     *
     * Assigns (or clears) a machine to an operator/viewer user.
     * The machine must belong to the same factory as the user.
     */
    public function assignMachine(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $this->abortIfCrossFactory($request->user(), $user);

        $validated = $request->validate([
            'machine_id' => 'nullable|integer|exists:machines,id',
        ]);

        if ($validated['machine_id'] !== null) {
            $machine = \App\Domain\Machine\Models\Machine::withoutGlobalScopes()
                ->find($validated['machine_id']);
            if ($machine && $machine->factory_id !== $user->factory_id) {
                return response()->json(['message' => 'Machine does not belong to this user\'s factory.'], 422);
            }
        }

        $user->update(['machine_id' => $validated['machine_id']]);

        return response()->json([
            'message'    => $validated['machine_id']
                ? "Machine assigned to {$user->name}."
                : "Machine unassigned from {$user->name}.",
            'machine_id' => $user->machine_id,
        ]);
    }

    // ── revokeRole ────────────────────────────────────────────

    public function revokeRole(Request $request, User $user): JsonResponse
    {
        $this->authorize('revokeRole', $user);

        $authUser = $request->user();
        $this->abortIfCrossFactory($authUser, $user);

        $factory = Factory::findOrFail($user->factory_id);

        try {
            $this->permissionService->revokeAllFactoryRoles($user, $factory);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return response()->json(['message' => "All roles revoked from {$user->name}."]);
    }

    // ── permissions ──────────────────────────────────────────

    /**
     * GET /admin/users/{user}/permissions
     *
     * Returns the user's role-inherited permissions and direct-grant permissions
     * so the employee permission UI can render checked/disabled states.
     */
    public function permissions(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);
        $this->abortIfCrossFactory($request->user(), $user);

        $originalTeamId = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($user->factory_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $rolePerms   = $user->getPermissionsViaRoles()->pluck('name')->values()->toArray();
        $directPerms = $user->getDirectPermissions()->pluck('name')->values()->toArray();

        $this->registrar->setPermissionsTeamId($originalTeamId);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return response()->json([
            'role_permissions'   => $rolePerms,
            'direct_permissions' => $directPerms,
        ]);
    }

    // ── syncPermissions ───────────────────────────────────────

    /**
     * POST /admin/users/{user}/sync-permissions
     *
     * Replaces the user's DIRECT permissions (role permissions are unaffected).
     * Body: { "permissions": ["create.downtime", "view.machine-logs", ...] }
     */
    public function syncPermissions(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $this->abortIfCrossFactory($request->user(), $user);

        $data = $request->validate([
            'permissions'   => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $originalTeamId = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($user->factory_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $user->syncPermissions($data['permissions'] ?? []);

        $this->registrar->setPermissionsTeamId($originalTeamId);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return response()->json([
            'message'            => "Permissions updated for {$user->name}.",
            'direct_permissions' => collect($data['permissions'] ?? [])->values()->toArray(),
        ]);
    }

    // ── Private Helpers ───────────────────────────────────────

    private function abortIfCrossFactory(User $authUser, User $targetUser): void
    {
        if ($this->isSuperAdmin($authUser)) {
            return;
        }

        if ($authUser->factory_id !== $targetUser->factory_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cross-factory user management is not permitted.');
        }
    }

    private function isSuperAdmin(User $user): bool
    {
        $originalTeamId = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId(0);

        $result = $user->hasRole(RoleEnum::SUPER_ADMIN->value);

        $this->registrar->setPermissionsTeamId($originalTeamId);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $result;
    }

    /**
     * @param RoleEnum[] $assignerRoles
     */
    private function canReassign(User $authUser, User $targetUser, array $assignerRoles): bool
    {
        if ($this->isSuperAdmin($authUser)) {
            return !$this->isSuperAdmin($targetUser);
        }

        if ($authUser->id === $targetUser->id) {
            return false;
        }

        return !empty($assignerRoles);
    }
}
