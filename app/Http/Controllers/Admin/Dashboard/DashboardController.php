<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dashboard;

use App\Http\Controllers\Concerns\ResolvesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesFactory;

    public function index(Request $request): View
    {
        $user = $request->user();

        ['factoryId' => $factoryId, 'factories' => $factories] =
            $this->resolveFactories($user, $request->integer('factory_id') ?: null);

        $factoryName = $factories->firstWhere('id', $factoryId)?->name
            ?? $user->factory?->name
            ?? 'Factory';

        return view('admin.dashboard.index', [
            'apiToken'    => session('api_token'),
            'factoryId'   => $factoryId,
            'factoryName' => $factoryName,
            'factories'   => $factories,
        ]);
    }
}
