<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Downtime;

use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DowntimeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $machines = Machine::where('status', '!=', 'retired')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        $reasons = DB::table('downtime_reasons')
            ->when($user->factory_id, fn ($q) => $q->where('factory_id', $user->factory_id))
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        $factories = $user->factory_id === null
            ? Factory::where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.downtimes.index', [
            'apiToken'  => session('api_token'),
            'machines'  => $machines,
            'reasons'   => $reasons,
            'factories' => $factories,
            'factoryId' => $user->factory_id,
        ]);
    }
}
