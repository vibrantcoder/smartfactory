<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee\Dashboard;

use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Models\Shift;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $machine = Machine::withoutGlobalScopes()
            ->where('id', $user->machine_id)
            ->first(['id', 'name', 'code', 'type', 'status', 'factory_id']);

        // Today's and next 2 days' plans for this machine
        $plans = ProductionPlan::with([
                'part:id,name,part_number,cycle_time_std',
                'shift:id,name,start_time,end_time',
                'actuals',
            ])
            ->where('machine_id', $user->machine_id)
            ->whereDate('planned_date', '>=', now()->toDateString())
            ->whereDate('planned_date', '<=', now()->addDays(2)->toDateString())
            ->whereIn('status', ['draft', 'scheduled', 'in_progress'])
            ->orderByRaw("FIELD(status, 'in_progress', 'scheduled', 'draft')")
            ->orderBy('planned_date')
            ->get();

        $shifts = Shift::withoutGlobalScopes()
            ->where('factory_id', $user->factory_id)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get(['id', 'name', 'start_time', 'end_time']);

        return view('employee.dashboard.index', [
            'machine'    => $machine,
            'plans'      => $plans,
            'shifts'     => $shifts,
            'apiToken'   => session('api_token'),
            'factoryId'  => $user->factory_id,
            'machineId'  => $user->machine_id,
            'userName'   => $user->name,
        ]);
    }

    public function noMachine(): View
    {
        return view('employee.dashboard.no-machine');
    }
}
