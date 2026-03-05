<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Machine;

use App\Domain\Factory\Models\Factory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MachineController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $factories = $user->factory_id === null
            ? Factory::where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.machines.index', [
            'apiToken'  => session('api_token'),
            'factoryId' => $user->factory_id,
            'factories' => $factories,
        ]);
    }
}
