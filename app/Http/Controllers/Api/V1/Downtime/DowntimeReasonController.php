<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Downtime;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DowntimeReasonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = DB::table('downtime_reasons')
            ->where('factory_id', $request->user()->factory_id)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'code'     => 'required|string|max:20|unique:downtime_reasons,code',
            'category' => 'required|in:planned,unplanned,maintenance',
        ]);

        $data['factory_id'] = $request->user()->factory_id;

        $id = DB::table('downtime_reasons')->insertGetId(array_merge($data, [
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return response()->json(DB::table('downtime_reasons')->find($id), 201);
    }

    public function show(int $downtimeReason): JsonResponse
    {
        return response()->json(DB::table('downtime_reasons')->find($downtimeReason));
    }

    public function update(Request $request, int $downtimeReason): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'category'  => 'sometimes|in:planned,unplanned,maintenance',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::table('downtime_reasons')->where('id', $downtimeReason)
            ->update(array_merge($data, ['updated_at' => now()]));

        return response()->json(DB::table('downtime_reasons')->find($downtimeReason));
    }

    public function destroy(int $downtimeReason): JsonResponse
    {
        DB::table('downtime_reasons')->delete($downtimeReason);

        return response()->json(null, 204);
    }
}
