<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Employee;

use App\Domain\Machine\Models\Machine;
use App\Domain\Shared\Enums\Permission;
use App\Domain\Shared\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $authUser = $request->user();

        // Load all factory employees (exclude self)
        $employees = User::query()
            ->with(['roles', 'machine:id,name,code'])
            ->where('factory_id', $authUser->factory_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'factory_id', 'machine_id', 'is_active'])
            ->map(function (User $u) use ($authUser) {
                $role = $u->roles->first();
                return [
                    'id'           => $u->id,
                    'name'         => $u->name,
                    'email'        => $u->email,
                    'machine_id'   => $u->machine_id,
                    'machine_name' => $u->machine
                        ? $u->machine->name . ' (' . $u->machine->code . ')'
                        : null,
                    'is_active'    => $u->is_active,
                    'is_self'      => $u->id === $authUser->id,
                    'role'         => $role?->name,
                    'role_label'   => $role ? RoleEnum::from($role->name)->label() : null,
                ];
            });

        // Active + maintenance machines in this factory (for machine assignment dropdown)
        $machines = Machine::where('status', '!=', 'retired')
            ->ordered()
            ->get(['id', 'name', 'code']);

        // Build permission groups for JS
        $permGroups = collect(Permission::groupedMatrix())->map(fn ($group) => [
            'label'       => $group['label'],
            'permissions' => collect($group['permissions'])->map(fn (Permission $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ])->values()->toArray(),
        ])->values()->toArray();

        return view('admin.employees.index', [
            'employees'        => $employees,
            'machines'         => $machines,
            'permissionGroups' => $permGroups,
        ]);
    }
}
