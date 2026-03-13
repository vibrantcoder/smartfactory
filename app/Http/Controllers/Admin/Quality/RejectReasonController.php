<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Quality;

use App\Http\Controllers\Concerns\ResolvesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RejectReasonController extends Controller
{
    use ResolvesFactory;

    public function index(Request $request): View
    {
        $user = $request->user();

        ['factoryId' => $factoryId, 'factories' => $factories] = $this->resolveFactories($user);

        $reasons = DB::table('reject_reasons')
            ->when($factoryId, fn ($q) => $q->where('factory_id', $factoryId))
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        return view('admin.quality.reject-reasons.index', [
            'apiToken'  => session('api_token'),
            'reasons'   => $reasons,
            'factories' => $factories,
            'factoryId' => $factoryId,
        ]);
    }
}
