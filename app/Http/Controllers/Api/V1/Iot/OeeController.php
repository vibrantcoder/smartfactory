<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Iot;

use App\Domain\Analytics\Models\MachineOeeShift;
use App\Domain\Analytics\Services\OeeCalculationService;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Models\Shift;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * OeeController
 *
 * REST endpoints for OEE data derived from iot_logs pulse signals.
 *
 * ROUTES (all under [auth:sanctum, factory.scope, factory.member]):
 *   GET /api/v1/iot/oee                       → factoryOee()
 *   GET /api/v1/iot/machines/{machine}/oee    → machineOee()
 *
 * Read strategy (fastest first):
 *   1. machine_oee_shifts summary table  — written by scheduler every 5 min
 *   2. Live calculation from iot_logs    — fallback when no aggregated row exists
 *      (e.g. just after midnight, or on first deploy before scheduler has run)
 *
 * OEE = Availability × Performance × Quality
 *   Source: iot_logs (raw 5-second pulse data)
 *   Performance requires a production plan with part.cycle_time_std.
 */
class OeeController extends Controller
{
    public function __construct(
        private readonly OeeCalculationService $oeeService,
    ) {}

    // ── machineOee ────────────────────────────────────────────────────

    /**
     * GET /api/v1/iot/machines/{machine}/oee
     *
     * Query params:
     *   date     = Y-m-d  (default: today)
     *   shift_id = int    (optional — if omitted returns all active shifts)
     *   live     = 1      (optional — bypass cache, always recalculate from raw logs)
     *
     * Returns OEE for one machine on one date, one shift or all active shifts.
     */
    public function machineOee(Request $request, Machine $machine): JsonResponse
    {
        $this->authorize('view', $machine);

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : Carbon::today();

        $useLive = (bool) $request->query('live', false);

        $machineData = [
            'id'   => $machine->id,
            'name' => $machine->name,
            'code' => $machine->code,
            'type' => $machine->type,
        ];

        // ── Single shift ────────────────────────────────────────────
        if ($request->filled('shift_id')) {
            $shift = Shift::findOrFail($request->integer('shift_id'));

            $plan = ProductionPlan::where('machine_id', $machine->id)
                ->where('planned_date', $date->format('Y-m-d'))
                ->where('shift_id', $shift->id)
                ->with(['part'])
                ->first();

            $oeeArray = $useLive
                ? $this->liveOeeArray($machine, $shift, $date, $plan)
                : $this->cachedOeeArray($machine, $shift, $date, $plan);

            return response()->json([
                'machine'          => $machineData,
                'date'             => $date->format('Y-m-d'),
                'shift'            => $this->shiftData($shift),
                'production_plan'  => $this->planData($plan),
                'oee'              => $oeeArray,
                'source'           => $useLive ? 'live' : ($oeeArray['_source'] ?? 'live'),
            ]);
        }

        // ── All active shifts ───────────────────────────────────────
        $shifts = Shift::where('factory_id', $machine->factory_id)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        $plans = ProductionPlan::where('machine_id', $machine->id)
            ->where('planned_date', $date->format('Y-m-d'))
            ->whereIn('shift_id', $shifts->pluck('id'))
            ->with(['part'])
            ->get()
            ->keyBy('shift_id');

        $shiftRows = $shifts->map(function (Shift $shift) use ($machine, $date, $plans, $useLive) {
            $plan     = $plans->get($shift->id);
            $oeeArray = $useLive
                ? $this->liveOeeArray($machine, $shift, $date, $plan)
                : $this->cachedOeeArray($machine, $shift, $date, $plan);

            return [
                'shift'           => $this->shiftData($shift),
                'production_plan' => $this->planData($plan),
                'oee'             => $oeeArray,
                'source'          => $useLive ? 'live' : ($oeeArray['_source'] ?? 'live'),
            ];
        })->values();

        return response()->json([
            'machine' => $machineData,
            'date'    => $date->format('Y-m-d'),
            'shifts'  => $shiftRows,
        ]);
    }

    // ── factoryOee ────────────────────────────────────────────────────

    /**
     * GET /api/v1/iot/oee
     *
     * Query params:
     *   date       = Y-m-d  (default: today)
     *   factory_id = int    (super-admin only; factory users use their own)
     *   live       = 1      (bypass cache)
     *
     * Returns OEE for ALL active machines × ALL active shifts for one date.
     * Reads from machine_oee_shifts summary table first (fast path).
     */
    public function factoryOee(Request $request): JsonResponse
    {
        $user      = $request->user();
        $factoryId = $user->factory_id ?? $request->integer('factory_id');

        if (!$factoryId) {
            return response()->json(['message' => 'factory_id is required for super-admin.'], 422);
        }

        $date    = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : Carbon::today();
        $useLive = (bool) $request->query('live', false);

        // Cache for 5 minutes (matches aggregator schedule). Bypass with ?live=1.
        $cacheKey = "factory_oee_{$factoryId}_{$date->format('Y-m-d')}";
        if (!$useLive && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $machines = Machine::where('factory_id', $factoryId)
            ->where('status', '!=', 'retired')
            ->orderBy('name')
            ->get();

        $shifts = Shift::where('factory_id', $factoryId)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        // Pre-load cached OEE rows in one query
        $cached = MachineOeeShift::where('factory_id', $factoryId)
            ->where('oee_date', $date->format('Y-m-d'))
            ->whereIn('machine_id', $machines->pluck('id'))
            ->whereIn('shift_id', $shifts->pluck('id'))
            ->get()
            ->groupBy('machine_id');

        // Pre-load production plans in one query
        $plans = ProductionPlan::where('planned_date', $date->format('Y-m-d'))
            ->whereIn('machine_id', $machines->pluck('id'))
            ->whereIn('shift_id', $shifts->pluck('id'))
            ->with(['part'])
            ->get()
            ->groupBy('machine_id');

        $result = $machines->map(function (Machine $machine) use (
            $shifts, $date, $cached, $plans, $useLive
        ) {
            $machineCached = $cached->get($machine->id, collect())->keyBy('shift_id');
            $machinePlans  = $plans->get($machine->id, collect())->keyBy('shift_id');

            $shiftRows = $shifts->map(function (Shift $shift) use (
                $machine, $date, $machineCached, $machinePlans, $useLive
            ) {
                $plan     = $machinePlans->get($shift->id);
                $oeeArray = $useLive
                    ? $this->liveOeeArray($machine, $shift, $date, $plan)
                    : $this->cachedOeeArrayFromRow($machineCached->get($shift->id), $machine, $shift, $date, $plan);

                return [
                    'shift_id'        => $shift->id,
                    'shift_name'      => $shift->name,
                    'start_time'      => $shift->start_time,
                    'end_time'        => $shift->end_time,
                    'duration_min'    => $shift->duration_min,
                    'production_plan' => $this->planData($plan),
                    'source'          => $useLive ? 'live' : ($oeeArray['_source'] ?? 'live'),
                    ...$this->stripMeta($oeeArray),
                ];
            })->values();

            return [
                'machine' => [
                    'id'   => $machine->id,
                    'name' => $machine->name,
                    'code' => $machine->code,
                    'type' => $machine->type,
                ],
                'shifts' => $shiftRows,
            ];
        });

        $payload = [
            'factory_id' => $factoryId,
            'date'       => $date->format('Y-m-d'),
            'machines'   => $result,
        ];

        // Cache for 5 minutes (only today's data — historical dates cached longer)
        $ttl = $date->isToday() ? 300 : 3600;
        if (!$useLive) {
            Cache::put($cacheKey, $payload, $ttl);
        }

        return response()->json($payload);
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * Return OEE array from the summary table row.
     * If no cached row exists, falls back to live calculation.
     */
    private function cachedOeeArray(
        Machine $machine,
        Shift $shift,
        Carbon $date,
        ?ProductionPlan $plan
    ): array {
        $row = MachineOeeShift::where('machine_id', $machine->id)
            ->where('shift_id', $shift->id)
            ->where('oee_date', $date->format('Y-m-d'))
            ->first();

        return $this->cachedOeeArrayFromRow($row, $machine, $shift, $date, $plan);
    }

    private function cachedOeeArrayFromRow(
        ?MachineOeeShift $row,
        Machine $machine,
        Shift $shift,
        Carbon $date,
        ?ProductionPlan $plan
    ): array {
        if ($row !== null) {
            return array_merge($this->rowToOeeArray($row), ['_source' => 'cache']);
        }

        // Fallback: live calculation
        $oee = $this->oeeService->calculateForShift(
            $machine, $shift, $date,
            $plan?->planned_qty,
            $plan?->part?->cycle_time_std !== null ? (float) $plan->part->cycle_time_std : null,
        );

        return array_merge($oee->toArray(), ['_source' => 'live']);
    }

    private function liveOeeArray(
        Machine $machine,
        Shift $shift,
        Carbon $date,
        ?ProductionPlan $plan
    ): array {
        $oee = $this->oeeService->calculateForShift(
            $machine, $shift, $date,
            $plan?->planned_qty,
            $plan?->part?->cycle_time_std !== null ? (float) $plan->part->cycle_time_std : null,
        );

        return array_merge($oee->toArray(), ['_source' => 'live']);
    }

    private function rowToOeeArray(MachineOeeShift $row): array
    {
        return [
            'planned_minutes'      => $row->planned_minutes,
            'alarm_minutes'        => $row->alarm_minutes,
            'available_minutes'    => $row->available_minutes,
            'total_parts'          => $row->total_parts,
            'reject_parts'         => $row->reject_parts,
            'good_parts'           => $row->good_parts,
            'planned_qty'          => $row->planned_qty,
            'attainment_pct'       => $row->attainment_pct,
            'availability_pct'     => $row->availability_pct,
            'performance_pct'      => $row->performance_pct,
            'quality_pct'          => $row->quality_pct,
            'oee_pct'              => $row->oee_pct,
            'log_count'            => $row->log_count,
            'log_interval_seconds' => $row->log_interval_seconds,
            'calculated_at'        => $row->calculated_at?->toIso8601String(),
        ];
    }

    /** Remove internal _source meta key before spreading into response. */
    private function stripMeta(array $oeeArray): array
    {
        unset($oeeArray['_source']);
        return $oeeArray;
    }

    private function shiftData(Shift $shift): array
    {
        return [
            'id'           => $shift->id,
            'name'         => $shift->name,
            'start_time'   => $shift->start_time,
            'end_time'     => $shift->end_time,
            'duration_min' => $shift->duration_min,
        ];
    }

    private function planData(?ProductionPlan $plan): ?array
    {
        if (!$plan) {
            return null;
        }
        return [
            'id'                  => $plan->id,
            'planned_qty'         => $plan->planned_qty,
            'status'              => $plan->status,
            'part_number'         => $plan->part?->part_number,
            'part_name'           => $plan->part?->name,
            'cycle_time_std_sec'  => $plan->part?->cycle_time_std,
        ];
    }
}
