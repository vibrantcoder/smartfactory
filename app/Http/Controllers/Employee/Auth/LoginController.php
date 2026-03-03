<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee\Auth;

use App\Domain\Shared\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showForm(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return $this->redirectByRole(Auth::guard('web')->user());
        }

        return view('employee.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        $user = Auth::guard('web')->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            return back()->withErrors(['email' => 'Your account has been deactivated. Contact your admin.']);
        }

        $request->session()->regenerate();

        $token = $user->createToken('employee-web')->plainTextToken;
        session(['api_token' => $token]);

        return $this->redirectByRole($user);
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $user?->tokens()->where('name', 'employee-web')->delete();
        $request->session()->forget('api_token');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('employee.login');
    }

    private function redirectByRole(mixed $user): RedirectResponse
    {
        $registrar = app(PermissionRegistrar::class);

        // Check super-admin (team 0)
        $registrar->setPermissionsTeamId(0);
        $isSuperAdmin = $user->hasRole(RoleEnum::SUPER_ADMIN->value);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        if ($isSuperAdmin) {
            $registrar->setPermissionsTeamId(0);
            return redirect()->route('admin.dashboard');
        }

        // Check factory-scoped admin roles
        $teamId = $user->factory_id ?? 0;
        $registrar->setPermissionsTeamId($teamId);

        $adminRoles = [
            RoleEnum::FACTORY_ADMIN->value,
            RoleEnum::PRODUCTION_MANAGER->value,
            RoleEnum::SUPERVISOR->value,
        ];

        foreach ($adminRoles as $role) {
            if ($user->hasRole($role)) {
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $registrar->setPermissionsTeamId($teamId);
                return redirect()->route('admin.dashboard');
            }
        }

        $user->unsetRelation('roles')->unsetRelation('permissions');
        $registrar->setPermissionsTeamId($teamId);

        return redirect()->route('employee.dashboard');
    }
}
