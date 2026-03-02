<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Domain\Factory\Models\Factory;
use App\Domain\Factory\Models\FactorySettings;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Services\ProductionCalculatorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProductionAnalysisController
 *
 * Read-only analytical endpoints — no mutations, no form requests.
 * All responses are hand-built arrays (no Resource class needed for
 * calculation endpoints since shapes vary per action).
 *
 * ─────────────────────────────────────────────────────────────────
 * ROUTE REGISTRATION (routes/api_v1.php):
 * ─────────────────────────────────────────────────────────────────
 *
 *   Route::middleware(['auth:sanctum', 'factory.scope', 'factory.member'])
 *        ->prefix('v1')
 *        ->group(function () {
 *
 *            // Full plan analysis — efficiency + targets + actuals
 *            Route::get(
 *                'production-plans/{plan}/analysis',
 *                [ProductionAnalysisController::class, 'planAnalysis']
 *            )->name('production-plans.analysis');
 *
 *            // Part production targets across all shifts
 *            Route::get(
 *                'parts/{part}/targets',
 *                [ProductionAnalysisController::class, 'partTargets']
 *            )->name('parts.targets');
 *
 *            // Factory-level daily capacity and targets
 *            Route::get(
 *                'factories/{factory}/daily-targets',
 *                [ProductionAnalysisController::class, 'factoryDailyTargets']
 *            )->name('factories.daily-targets');
 *        });
 *
 * ─────────────────────────────────────────────────────────────────
 */
class ProductionAnalysisController extends Controller
{
    public function __construct(
        private readonly ProductionCalculatorService $calculator,
    ) {}

    // ══════════════════════════════════════════════════════════════
    //  1. PLAN ANALYSIS
    //     GET /api/v1/production-plans/{plan}/analysis
    // ══════════════════════════════════════════════════════════════

    /**
     * Full production plan analysis: targets + actuals + efficiency.
     *
     * Pre-loads all required relations in one eager-load call to avoid
     * N+1 queries inside the service.
     *
     * EXAMPLE RESPONSE:
     * {
     *   "plan": { "id": 42, "status": "in_progress", "planned_qty": 100, ... },
     *   "cycle_time_minutes": 12.5,
     *   "shift_target": {
     *     "shift_id": 1, "shift_name": "Morning Shift",
     *     "shift_duration_minutes": 480, "efficiency_pct": 85.0,
     *     "effective_minutes": 408, "target_qty": 32, "theoretical_max_qty": 38
     *   },
     *   "actual_production": {
     *     "total_actual_qty": 65, "total_defect_qty": 3,
     *     "total_good_qty": 62, "batch_count": 5,
     *     "defect_rate_pct": 4.62, "yield_rate_pct": 95.38
     *   },
     *   "efficiency": {
     *     "planned_qty": 100, "actual_good_qty": 62,
     *     "efficiency_pct": 62.0, "variance_qty": -38,
     *     "status": "below_target", "is_on_target": false
     *   },
     *   "remaining_qty": 38,
     *   "estimated_remaining_minutes": 475.0
     * }
     */
    public function planAnalysis(ProductionPlan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        // ── Eager load all relations needed by the service ────
        $plan->loadMissing([
            'part.processes.processMaster',  // for calculateTotalCycleTime()
            'shift',                          // for calculateShiftTarget()
            'machine:id,name,code,type',      // for display metadata
        ]);

        // ── Resolve factory settings ──────────────────────────
        // FactorySettings::resolveFor() is safe — creates with defaults if missing.
        $settings = FactorySettings::resolveFor($plan->factory_id);

        // ── Run composite analysis ────────────────────────────
        $analysis = $this->calculator->analyzeProductionPlan($plan, $settings);

        return response()->json([
            'data' => $analysis->toArray(),
            'meta' => [
                'oee_target_pct'       => (float) $settings->oee_target_pct,
                'working_hours_per_day'=> (float) $settings->working_hours_per_day,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  2. PART TARGETS
    //     GET /api/v1/parts/{part}/targets
    // ══════════════════════════════════════════════════════════════

    /**
     * Production targets for a specific part across all factory shifts.
     *
     * Shows how many units of this part can be produced per shift and per day,
     * using the part's routing cycle time and the factory's OEE target.
     *
     * EXAMPLE RESPONSE:
     * {
     *   "part": { "id": 7, "part_number": "BKT-A001", "name": "Bracket A", "unit": "pcs" },
     *   "cycle_time_minutes": 12.5,
     *   "daily_target": {
     *     "daily_capacity_minutes": 1440, "daily_capacity_hours": 24.0,
     *     "efficiency_pct": 85.0, "effective_minutes": 1224,
     *     "target_qty": 97, "theoretical_max_qty": 115, "capacity_gap_qty": 18
     *   },
     *   "shift_targets": [
     *     { "shift_id": 1, "shift_name": "Morning", "target_qty": 32, ... },
     *     { "shift_id": 2, "shift_name": "Afternoon", "target_qty": 32, ... },
     *     { "shift_id": 3, "shift_name": "Night", "target_qty": 32, ... }
     *   ]
     * }
     */
    public function partTargets(Request $request, Part $part): JsonResponse
    {
        $this->authorize('view', $part);

        $user = $request->user();

        // Load part routing
        $part->loadMissing('processes.processMaster');

        // Resolve factory context
        $settings = FactorySettings::resolveFor($user->factory_id);

        // Load factory shifts for capacity calculation
        $shifts = $part->factory?->shifts()->where('is_active', true)->get()
                  ?? collect();

        // ── calculateTotalCycleTime ───────────────────────────
        $cycleTimeMinutes = $part->total_cycle_time > 0
            ? (float) $part->total_cycle_time
            : $this->calculator->calculateTotalCycleTime($part->processes);

        // ── calculateDailyTarget ──────────────────────────────
        $dailyCapacity = $this->calculator->dailyCapacityMinutes($shifts, $settings);
        $effFactor     = $this->calculator->efficiencyFactorFromSettings($settings);

        $dailyTarget = $this->calculator->calculateDailyTarget(
            cycleTimeMinutes:     $cycleTimeMinutes,
            dailyCapacityMinutes: $dailyCapacity,
            efficiencyFactor:     $effFactor,
        );

        // ── calculateShiftTarget for each active shift ─────────
        $shiftTargets = $this->calculator->calculateAllShiftTargets(
            cycleTimeMinutes: $cycleTimeMinutes,
            shifts:           $shifts,
            settings:         $settings,
        );

        return response()->json([
            'data' => [
                'part' => [
                    'id'                => $part->id,
                    'part_number'       => $part->part_number,
                    'name'              => $part->name,
                    'unit'              => $part->unit,
                    'cycle_time_std'    => (float) $part->cycle_time_std,
                    'total_cycle_time'  => $cycleTimeMinutes,
                    'routing_steps'     => $part->processes->count(),
                ],
                'daily_target'  => $dailyTarget->toArray(),
                'shift_targets' => array_values(
                    array_map(fn($r) => $r->toArray(), $shiftTargets)
                ),
            ],
            'meta' => [
                'oee_target_pct'       => (float) $settings->oee_target_pct,
                'working_hours_per_day'=> (float) $settings->working_hours_per_day,
                'daily_capacity_minutes'=> $dailyCapacity,
                'efficiency_factor'    => $effFactor,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  3. FACTORY DAILY TARGETS
    //     GET /api/v1/factories/{factory}/daily-targets?date=YYYY-MM-DD
    // ══════════════════════════════════════════════════════════════

    /**
     * Daily capacity breakdown for the factory dashboard.
     *
     * Aggregates all SCHEDULED production plans for the requested date,
     * shows per-shift capacity, and compares planned vs target for each plan.
     *
     * QUERY PARAM: ?date=YYYY-MM-DD (defaults to today)
     *
     * EXAMPLE RESPONSE:
     * {
     *   "date": "2026-02-25",
     *   "daily_capacity_minutes": 1440,
     *   "daily_capacity_hours": 24.0,
     *   "efficiency_factor": 0.85,
     *   "plans": [
     *     {
     *       "plan_id": 42, "part_number": "BKT-A001", "machine_name": "Laser-01",
     *       "shift_name": "Morning", "planned_qty": 100,
     *       "cycle_time_minutes": 12.5,
     *       "shift_target_qty": 32,
     *       "actual_good_qty": 62,
     *       "efficiency_pct": 62.0,
     *       "status": "below_target"
     *     }
     *   ],
     *   "summary": {
     *     "total_plans": 8,
     *     "total_planned_qty": 850,
     *     "total_good_qty": 672,
     *     "overall_efficiency_pct": 79.1,
     *     "on_target_count": 5,
     *     "below_target_count": 3
     *   }
     * }
     */
    public function factoryDailyTargets(Request $request, Factory $factory): JsonResponse
    {
        $this->authorize('view', $factory);

        $date     = $request->date('date', 'Y-m-d') ?? now()->toDateString();
        $settings = FactorySettings::resolveFor($factory->id);

        // Load active shifts
        $shifts = $factory->shifts()->where('is_active', true)->get(['id', 'name', 'duration_min', 'is_active']);

        $dailyCapacity = $this->calculator->dailyCapacityMinutes($shifts, $settings);
        $effFactor     = $this->calculator->efficiencyFactorFromSettings($settings);

        // Load all plans for this date (exclude cancelled)
        $plans = ProductionPlan::query()
            ->forFactory($factory->id)
            ->whereDate('planned_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->with([
                'part:id,part_number,name,total_cycle_time',
                'machine:id,name',
                'shift:id,name,duration_min,is_active',
            ])
            ->get();

        // ── Per-plan analysis ──────────────────────────────────
        $planSummaries = $plans->map(function (ProductionPlan $plan) use ($effFactor): array {
            // ── calculateTotalCycleTime (from stored total) ────
            $cycleTime = (float) ($plan->part?->total_cycle_time ?? 0);

            // ── calculateShiftTarget ───────────────────────────
            $shiftTarget = $plan->shift
                ? $this->calculator->calculateShiftTarget($cycleTime, $plan->shift, $effFactor)
                : null;

            // ── calculateActualProduction ──────────────────────
            $actual = $this->calculator->calculateActualProduction($plan);

            // ── calculateProductionEfficiency ──────────────────
            $efficiency = $this->calculator->calculateProductionEfficiency(
                plannedQty:         $plan->planned_qty,
                actualGoodQty:      $actual->totalGoodQty,
                targetThresholdPct: 85.0,
            );

            return [
                'plan_id'              => $plan->id,
                'status'               => $plan->status,
                'part_number'          => $plan->part?->part_number ?? '—',
                'machine_name'         => $plan->machine?->name ?? '—',
                'shift_name'           => $plan->shift?->name ?? '—',
                'planned_qty'          => $plan->planned_qty,
                'cycle_time_minutes'   => $cycleTime,
                'shift_target_qty'     => $shiftTarget?->targetQty ?? null,
                'actual_good_qty'      => $actual->totalGoodQty,
                'defect_qty'           => $actual->totalDefectQty,
                'efficiency_pct'       => $efficiency->efficiencyPct,
                'efficiency_status'    => $efficiency->status,
                'is_on_target'         => $efficiency->isOnTarget,
                'remaining_qty'        => max(0, $plan->planned_qty - $actual->totalGoodQty),
            ];
        });

        // ── Factory-wide summary ───────────────────────────────
        $totalPlanned  = $planSummaries->sum('planned_qty');
        $totalGood     = $planSummaries->sum('actual_good_qty');
        $onTargetCount = $planSummaries->where('is_on_target', true)->count();

        $overallEfficiency = $totalPlanned > 0
            ? round($totalGood / $totalPlanned * 100, 2)
            : 0.0;

        return response()->json([
            'data' => [
                'date'                   => $date,
                'daily_capacity_minutes' => $dailyCapacity,
                'daily_capacity_hours'   => round($dailyCapacity / 60, 2),
                'efficiency_factor'      => $effFactor,
                'plans'                  => $planSummaries->values(),
                'summary'                => [
                    'total_plans'             => $plans->count(),
                    'total_planned_qty'       => $totalPlanned,
                    'total_good_qty'          => $totalGood,
                    'overall_efficiency_pct'  => $overallEfficiency,
                    'on_target_count'         => $onTargetCount,
                    'below_target_count'      => $plans->count() - $onTargetCount,
                ],
            ],
            'meta' => [
                'oee_target_pct'        => (float) $settings->oee_target_pct,
                'working_hours_per_day' => (float) $settings->working_hours_per_day,
            ],
        ]);
    }
}
