<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Production;

use App\Domain\Production\Models\Customer;
use App\Domain\Production\Models\Part;
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
            ->orderBy('part_number')
            ->get(['id', 'name', 'part_number', 'customer_id', 'factory_id', 'unit', 'cycle_time_std']);

        return view('admin.production.work-orders.index', [
            'apiToken'   => session('api_token'),
            'factoryId'  => $factoryId,
            'factories'  => $factories,
            'customers'  => $customers,
            'parts'      => $parts,
        ]);
    }
}
