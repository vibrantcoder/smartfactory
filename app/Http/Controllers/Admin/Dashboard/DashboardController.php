<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dashboard;

use App\Domain\Factory\Models\Factory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Super-admin (factory_id = null): show a factory selector
        if ($user->factory_id === null) {
            $factories = Factory::orderBy('name')->get(['id', 'name', 'status']);

            $selectedFactoryId = $request->integer('factory_id')
                ?: $factories->first()?->id;

            $selectedFactory = $factories->firstWhere('id', $selectedFactoryId);

            return view('admin.dashboard.index', [
                'factories'   => $factories,
                'factoryId'   => $selectedFactoryId,
                'factoryName' => $selectedFactory?->name ?? 'All Factories',
                'apiToken'    => session('api_token'),
            ]);
        }

        // Factory-scoped user: use their factory directly
        return view('admin.dashboard.index', [
            'factories'   => collect(),
            'factoryId'   => $user->factory_id,
            'factoryName' => $user->factory?->name ?? 'My Factory',
            'apiToken'    => session('api_token'),
        ]);
    }
}
