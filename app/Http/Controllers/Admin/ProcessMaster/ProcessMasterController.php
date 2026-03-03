<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\ProcessMaster;

use App\Domain\Production\Models\ProcessMaster;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcessMasterController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProcessMaster::class);

        return view('admin.process-masters.index', [
            'apiToken' => session('api_token'),
        ]);
    }
}
