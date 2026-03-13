<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Production;

use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\Shift;
use App\Http\Controllers\Concerns\ResolvesFactory;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionPlanController extends Controller
{
    use ResolvesFactory;

    public function index(Request $request): View
    {
        $user = $request->user();

        ['factoryId' => $factoryId, 'factories' => $factories] = $this->resolveFactories($user);

        $machines = Machine::withoutGlobalScopes()
            ->where('status', 'active')
            ->when($factoryId, fn ($q) => $q->where('factory_id', $factoryId))
            ->ordered()
            ->get(['id', 'name', 'code', 'type', 'factory_id']);

        $shifts = Shift::withoutGlobalScopes()
            ->where('is_active', true)
            ->when($factoryId, fn ($q) => $q->where('factory_id', $factoryId))
            ->orderBy('start_time')
            ->get(['id', 'name', 'start_time', 'end_time', 'duration_min', 'factory_id']);

        $parts = Part::where('status', 'active')
            ->when($factoryId, fn ($q) => $q->where('factory_id', $factoryId))
            ->with(['processes' => fn ($q) => $q->orderBy('sequence_order')->with('processMaster:id,name')])
            ->orderBy('part_number')
            ->get(['id', 'name', 'part_number', 'cycle_time_std', 'total_cycle_time', 'factory_id']);

        // Load week-off days and holidays for the current factory
        $weekOffDays = [];
        $holidays    = [];
        if ($factoryId) {
            $factory = Factory::with('holidays')->find($factoryId);
            if ($factory) {
                $weekOffDays = $factory->week_off_days ?? [];
                $holidays    = $factory->holidays
                    ->map(fn ($h) => [
                        'date' => $h->holiday_date instanceof \Carbon\Carbon
                            ? $h->holiday_date->format('Y-m-d')
                            : (string) $h->holiday_date,
                        'name' => $h->name,
                    ])
                    ->values()
                    ->all();
            }
        }

        // Load operators (users with operator/viewer roles in the factory)
        $operators = User::withoutGlobalScopes()
            ->when($factoryId, fn ($q) => $q->where('factory_id', $factoryId))
            ->whereNotNull('factory_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'machine_id', 'factory_id']);

        return view('admin.production.plans.index', [
            'apiToken'    => session('api_token'),
            'factoryId'   => $factoryId,
            'factories'   => $factories,
            'machines'    => $machines,
            'shifts'      => $shifts,
            'parts'       => $parts,
            'operators'   => $operators,
            'weekOffDays' => $weekOffDays,
            'holidays'    => $holidays,
        ]);
    }
}
