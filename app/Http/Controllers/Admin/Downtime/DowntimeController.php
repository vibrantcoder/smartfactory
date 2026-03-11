<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Downtime;

use App\Domain\Machine\Models\Machine;
use App\Http\Controllers\Concerns\ResolvesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DowntimeController extends Controller
{
    use ResolvesFactory;

    public function index(Request $request): View
    {
        $user = $request->user();

        $machines = Machine::where('status', '!=', 'retired')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        ['factoryId' => $factoryId, 'factories' => $factories] = $this->resolveFactories($user);

        $reasons = DB::table('downtime_reasons')
            ->when($factoryId, fn ($q) => $q->where('factory_id', $factoryId))
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        return view('admin.downtimes.index', [
            'apiToken'  => session('api_token'),
            'machines'  => $machines,
            'reasons'   => $reasons,
            'factories' => $factories,
            'factoryId' => $factoryId,
        ]);
    }
}
