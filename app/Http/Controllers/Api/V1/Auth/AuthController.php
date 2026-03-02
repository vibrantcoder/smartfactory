<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        // Set team scope so getRoleNames() returns scoped roles
        $this->setScopeForUser($user);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'factory_id' => $user->factory_id,
                'roles'      => $user->getRoleNames(),
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // factory.scope middleware may not run on this route; set scope explicitly
        $this->setScopeForUser($user);

        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'factory_id'  => $user->factory_id,
            'roles'       => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * Set the Spatie team scope based on whether user is super-admin or not.
     * Also clears Spatie's cached permission/role relations to prevent stale data.
     */
    private function setScopeForUser(User $user): void
    {
        $registrar = app(PermissionRegistrar::class);

        // Check super-admin with global scope (team_id=0)
        $registrar->setPermissionsTeamId(0);
        $isSuperAdmin = $user->hasRole('super-admin');

        if ($isSuperAdmin) {
            // Clear cached relations so getRoleNames() re-queries with team_id=0
            $user->unsetRelation('roles')->unsetRelation('permissions');
            return;
        }

        // Factory-scoped users: set to their factory and clear cache
        $registrar->setPermissionsTeamId($user->factory_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}
