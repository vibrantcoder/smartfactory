<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Production;

use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Customer;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\Shift;
use App\Domain\Production\Models\WorkOrder;
use App\Http\Controllers\Concerns\ResolvesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkOrderWebController extends Controller
{
    use ResolvesFactory;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', WorkOrder::class);

        $user = $request->user();

        ['factoryId' => $factoryId, 'factories' => $factories] = $this->resolveFactories($user);

        $customers = Customer::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'factory_id']);

        $parts = Part::where('status', 'active')
            ->with(['processes' => fn($q) => $q
                ->with('processMaster:id,name,code,standard_time')
                ->orderBy('sequence_order')])
            ->orderBy('part_number')
            ->get(['id', 'name', 'part_number', 'customer_id', 'factory_id', 'unit', 'cycle_time_std', 'total_cycle_time'])
            ->map(fn($part) => [
                'id'               => $part->id,
                'name'             => $part->name,
                'part_number'      => $part->part_number,
                'customer_id'      => $part->customer_id,
                'factory_id'       => $part->factory_id,
                'unit'             => $part->unit,
                'cycle_time_std'   => $part->cycle_time_std,
                'total_cycle_time' => $part->total_cycle_time,
                'processes'        => $part->processes->map(fn($pp) => [
                    'id'                   => $pp->id,
                    'sequence_order'       => $pp->sequence_order,
                    'process_master_name'  => $pp->processMaster?->name ?? '(unnamed)',
                    'process_master_code'  => $pp->processMaster?->code ?? '',
                    'effective_cycle_time' => $pp->effectiveCycleTime(), // minutes
                ])->values(),
            ]);

        $machines = Machine::withoutGlobalScopes()
            ->where('status', 'active')
            ->when($factoryId, fn($q) => $q->where('factory_id', $factoryId))
            ->orderBy('name')
            ->get(['id', 'name', 'factory_id']);

        $shifts = Shift::withoutGlobalScopes()
            ->where('is_active', true)
            ->when($factoryId, fn($q) => $q->where('factory_id', $factoryId))
            ->orderBy('name')
            ->get(['id', 'name', 'duration_min', 'break_min', 'factory_id']);

        // Load factory week-off + holidays for calendar coloring & scheduling guard
        $factory     = $factoryId ? Factory::find($factoryId) : null;
        $weekOffDays = $factory ? ($factory->week_off_days ?? []) : [];
        $holidays    = $factory
            ? $factory->holidays()->orderBy('holiday_date')->get()
                ->map(fn($h) => [
                    'date' => $h->holiday_date instanceof \Carbon\Carbon
                        ? $h->holiday_date->format('Y-m-d')
                        : (string) $h->holiday_date,
                    'name' => $h->name,
                ])->values()->toArray()
            : [];

        return view('admin.production.work-orders.index', [
            'apiToken'    => session('api_token'),
            'factoryId'   => $factoryId,
            'factories'   => $factories,
            'customers'   => $customers,
            'parts'       => $parts,
            'machines'    => $machines,
            'shifts'      => $shifts,
            'weekOffDays' => $weekOffDays,
            'holidays'    => $holidays,
        ]);
    }
}
