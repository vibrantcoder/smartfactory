<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Http\Controllers\Controller;
use App\Domain\Production\Models\ProductionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductionPlan::query()
            ->with(['machine', 'part', 'shift', 'partProcess.processMaster', 'operator:id,name,email,machine_id'])
            ->withSum('actuals as actual_qty_sum', 'actual_qty')
            ->withSum('actuals as good_qty_sum', 'good_qty');

        if ($request->filled('from_date')) {
            $query->whereDate('planned_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('planned_date', '<=', $request->input('to_date'));
        }
        if ($request->filled('machine_id')) {
            $query->where('machine_id', $request->integer('machine_id'));
        }
        if ($request->filled('factory_id')) {
            $query->where('factory_id', $request->integer('factory_id'));
        }
        if ($request->filled('part_id')) {
            $query->where('part_id', $request->integer('part_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $plans = $query
            ->orderBy('planned_date')
            ->orderBy('shift_id')
            ->paginate($request->integer('per_page', 200));

        // Attach IoT actual parts from machine_oee_shifts (past dates)
        // or live from iot_logs (today) to each plan as iot_parts.
        if ($plans->isNotEmpty()) {
            $today = now()->toDateString();

            // --- Past dates: batch-load from machine_oee_shifts ---
            $oeeRows = DB::table('machine_oee_shifts')
                ->whereIn('machine_id', $plans->pluck('machine_id')->unique()->values())
                ->whereIn('shift_id',   $plans->pluck('shift_id')->unique()->values())
                ->whereIn('oee_date',   $plans->pluck('planned_date')->map(fn($d) => substr($d, 0, 10))->unique()->values())
                ->get(['machine_id', 'shift_id', 'oee_date', 'chart_data'])
                ->keyBy(fn($r) => "{$r->machine_id}:{$r->shift_id}:{$r->oee_date}");

            // --- Today: live sum per machine+shift using shift time window ---
            $liveByMachineShift = [];
            $todayPlans = $plans->filter(fn($p) => substr($p->planned_date, 0, 10) === $today);
            if ($todayPlans->isNotEmpty()) {
                $shiftIds = $todayPlans->pluck('shift_id')->unique()->values();
                $shiftTimes = DB::table('shifts')
                    ->whereIn('id', $shiftIds)
                    ->get(['id', 'start_time', 'end_time'])
                    ->keyBy('id');

                foreach ($todayPlans as $tp) {
                    $shiftObj = $shiftTimes->get($tp->shift_id);
                    if (!$shiftObj) continue;
                    $key = "{$tp->machine_id}:{$tp->shift_id}";
                    if (isset($liveByMachineShift[$key])) continue;

                    $start = $today . ' ' . $shiftObj->start_time;
                    $end   = $today . ' ' . $shiftObj->end_time;
                    // Night shift crosses midnight: end_time < start_time
                    $query = DB::table('iot_logs')->where('machine_id', $tp->machine_id);
                    if ($shiftObj->end_time > $shiftObj->start_time) {
                        $query->whereBetween('logged_at', [$start, $end]);
                    } else {
                        $query->where(fn($q) => $q
                            ->where('logged_at', '>=', $start)
                            ->orWhere('logged_at', '<', $today . ' ' . $shiftObj->end_time)
                        );
                    }
                    $liveByMachineShift[$key] = (int) $query->sum('part_count');
                }
            }

            $plans->getCollection()->transform(function ($plan) use ($oeeRows, $liveByMachineShift, $today) {
                $date = substr($plan->planned_date, 0, 10);

                if ($date === $today) {
                    $key = "{$plan->machine_id}:{$plan->shift_id}";
                    $plan->iot_parts = $liveByMachineShift[$key] ?? 0;
                } else {
                    $key = "{$plan->machine_id}:{$plan->shift_id}:{$date}";
                    $row = $oeeRows->get($key);
                    if ($row && $row->chart_data) {
                        $chart = json_decode($row->chart_data, true);
                        $plan->iot_parts = (int) ($chart['summary']['total_parts'] ?? 0);
                    } else {
                        $plan->iot_parts = 0;
                    }
                }
                return $plan;
            });
        }

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id'      => 'required|exists:machines,id',
            'part_id'         => 'required|exists:parts,id',
            'part_process_id' => 'nullable|exists:part_processes,id',
            'shift_id'        => 'required|exists:shifts,id',
            'operator_id'     => 'nullable|exists:users,id',
            'planned_date'    => 'required|date',
            'planned_qty'     => 'required|integer|min:1',
            'factory_id'      => 'sometimes|exists:factories,id',
            'notes'           => 'nullable|string',
        ]);

        $data['factory_id'] = $request->user()->factory_id
            ?? $request->integer('factory_id');

        $plan = ProductionPlan::create($data);

        return response()->json($plan->load(['machine', 'part', 'shift', 'partProcess.processMaster', 'operator:id,name,email,machine_id']), 201);
    }

    public function show(ProductionPlan $productionPlan): JsonResponse
    {
        return response()->json($productionPlan->load(['machine', 'part', 'shift', 'partProcess.processMaster', 'actuals']));
    }

    public function update(Request $request, ProductionPlan $productionPlan): JsonResponse
    {
        $data = $request->validate([
            'machine_id'      => 'sometimes|exists:machines,id',
            'part_id'         => 'sometimes|exists:parts,id',
            'part_process_id' => 'nullable|exists:part_processes,id',
            'shift_id'        => 'sometimes|exists:shifts,id',
            'operator_id'     => 'nullable|exists:users,id',
            'planned_date'    => 'sometimes|date',
            'planned_qty'     => 'sometimes|integer|min:1',
            'status'          => 'sometimes|in:draft,scheduled,in_progress,completed,cancelled',
            'notes'           => 'nullable|string',
        ]);

        $productionPlan->update($data);

        return response()->json($productionPlan->fresh(['machine', 'part', 'shift', 'partProcess.processMaster', 'operator:id,name,email,machine_id']));
    }

    public function destroy(ProductionPlan $productionPlan): JsonResponse
    {
        $productionPlan->delete();

        return response()->json(null, 204);
    }
}
