<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Factory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FactoryAdminController
 *
 * Serves the factory management page (web admin).
 * All CRUD is handled client-side via Alpine.js → /api/v1/factories.
 */
class FactoryAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Domain\Factory\Models\Factory::class);

        return view('admin.factories.index', [
            'apiToken' => session('api_token'),
        ]);
    }
}
