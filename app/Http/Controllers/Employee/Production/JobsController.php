<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee\Production;

use App\Domain\Production\Models\ProductionPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $plans = ProductionPlan::with([
                'part:id,name,part_number,cycle_time_std',
                'shift:id,name,start_time,end_time',
                'actuals',
            ])
            ->where('machine_id', $user->machine_id)
            ->whereDate('planned_date', '>=', now()->subDays(7)->toDateString())
            ->whereDate('planned_date', '<=', now()->addDays(14)->toDateString())
            ->orderByDesc('planned_date')
            ->orderByRaw("FIELD(status, 'in_progress', 'scheduled', 'draft', 'completed', 'cancelled')")
            ->paginate(20);

        return view('employee.production.jobs', [
            'plans'     => $plans,
            'machineId' => $user->machine_id,
            'apiToken'  => session('api_token'),
        ]);
    }
}
