<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Production;

use App\Http\Controllers\Concerns\ResolvesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ResolvesFactory;

    public function index(Request $request): mixed
    {
        $user = $request->user();

        ['factoryId' => $factoryId, 'factories' => $factories] = $this->resolveFactories($user);

        return view('admin.customers.index', [
            'apiToken'  => session('api_token'),
            'factoryId' => $factoryId,
            'factories' => $factories,
        ]);
    }
}
