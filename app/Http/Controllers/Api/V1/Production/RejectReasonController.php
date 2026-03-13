<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RejectReasonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $factoryId = $user->factory_id
            ?? ($request->filled('factory_id') ? $request->integer('factory_id') : null);

        $rows = DB::table('reject_reasons')
            ->when($factoryId, fn ($q) => $q->where('factory_id', $factoryId))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'code'     => 'required|string|max:20',
            'category' => 'required|in:cosmetic,dimensional,functional,material,assembly,other',
        ]);

        $data['factory_id'] = $request->user()->factory_id;

        $id = DB::table('reject_reasons')->insertGetId(array_merge($data, [
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return response()->json(DB::table('reject_reasons')->find($id), 201);
    }

    public function update(Request $request, int $rejectReason): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'code'      => 'sometimes|string|max:20',
            'category'  => 'sometimes|in:cosmetic,dimensional,functional,material,assembly,other',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::table('reject_reasons')->where('id', $rejectReason)
            ->update(array_merge($data, ['updated_at' => now()]));

        return response()->json(DB::table('reject_reasons')->find($rejectReason));
    }

    public function destroy(int $rejectReason): JsonResponse
    {
        DB::table('reject_reasons')->delete($rejectReason);

        return response()->json(null, 204);
    }
}
