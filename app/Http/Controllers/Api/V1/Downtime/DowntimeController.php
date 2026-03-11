<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Downtime;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DowntimeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('downtimes')
            ->when($request->user()->factory_id, fn($q, $fid) => $q->where('factory_id', $fid))
            ->when($request->machine_id, fn($q) => $q->where('machine_id', $request->machine_id))
            ->orderByDesc('started_at');

        $rows = $query->paginate($request->integer('per_page', 20));

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id'          => 'required|exists:machines,id',
            'downtime_reason_id'  => 'nullable|exists:downtime_reasons,id',
            'production_plan_id'  => 'nullable|exists:production_plans,id',
            'started_at'          => 'required|date',
            'ended_at'            => 'nullable|date|after:started_at',
            'description'         => 'nullable|string',
        ]);

        // Super-admin has no factory_id; resolve from the machine instead.
        $data['factory_id'] = $request->user()->factory_id
            ?? DB::table('machines')->where('id', $data['machine_id'])->value('factory_id');

        if ($data['factory_id'] === null) {
            return response()->json(['message' => 'Could not determine factory for this machine.'], 422);
        }

        if (isset($data['ended_at'])) {
            $start = \Carbon\Carbon::parse($data['started_at']);
            $end   = \Carbon\Carbon::parse($data['ended_at']);
            $data['duration_minutes'] = (int) $start->diffInMinutes($end);
        }

        $id = DB::table('downtimes')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return response()->json(DB::table('downtimes')->find($id), 201);
    }

    public function show(int $downtime): JsonResponse
    {
        return response()->json(DB::table('downtimes')->find($downtime));
    }

    public function update(Request $request, int $downtime): JsonResponse
    {
        $data = $request->validate([
            'ended_at'           => 'nullable|date',
            'downtime_reason_id' => 'nullable|exists:downtime_reasons,id',
            'description'        => 'nullable|string',
        ]);

        $row = DB::table('downtimes')->find($downtime);
        if ($row && isset($data['ended_at'])) {
            $start = \Carbon\Carbon::parse($row->started_at);
            $end   = \Carbon\Carbon::parse($data['ended_at']);
            $data['duration_minutes'] = (int) $start->diffInMinutes($end);
        }

        DB::table('downtimes')->where('id', $downtime)->update(array_merge($data, ['updated_at' => now()]));

        return response()->json(DB::table('downtimes')->find($downtime));
    }

    public function destroy(int $downtime): JsonResponse
    {
        DB::table('downtimes')->delete($downtime);

        return response()->json(null, 204);
    }
}
