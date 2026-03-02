<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Production;

use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\Shift;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionPlanController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $machines = Machine::where('status', 'active')
            ->ordered()
            ->get(['id', 'name', 'code', 'type']);

        $shifts = Shift::where('is_active', true)
            ->orderBy('start_time')
            ->get(['id', 'name', 'start_time', 'end_time', 'duration_min']);

        $parts = Part::where('status', 'active')
            ->orderBy('part_number')
            ->get(['id', 'name', 'part_number', 'cycle_time_std']);

        $factories = $user->factory_id === null
            ? Factory::where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.production.plans.index', [
            'apiToken'  => session('api_token'),
            'factoryId' => $user->factory_id,
            'factories' => $factories,
            'machines'  => $machines,
            'shifts'    => $shifts,
            'parts'     => $parts,
        ]);
    }
}
