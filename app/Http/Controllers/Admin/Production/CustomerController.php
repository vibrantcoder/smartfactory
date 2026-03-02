<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Production;

use App\Domain\Factory\Models\Factory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Admin web controller for Customer management.
 * All CRUD is handled client-side via Alpine.js → /api/v1/customers.
 */
class CustomerController extends Controller
{
    public function index(Request $request): mixed
    {
        $user      = $request->user();
        $factories = $user->factory_id === null
            ? Factory::where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.customers.index', [
            'apiToken'  => session('api_token'),
            'factoryId' => $user->factory_id,
            'factories' => $factories,
        ]);
    }
}
