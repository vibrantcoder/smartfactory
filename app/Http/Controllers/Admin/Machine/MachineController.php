<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Machine;

use App\Http\Controllers\Concerns\ResolvesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MachineController extends Controller
{
    use ResolvesFactory;

    public function index(Request $request): View
    {
        $user = $request->user();

        ['factoryId' => $factoryId, 'factories' => $factories] = $this->resolveFactories($user);

        return view('admin.machines.index', [
            'apiToken'  => session('api_token'),
            'factoryId' => $factoryId,
            'factories' => $factories,
        ]);
    }
}
