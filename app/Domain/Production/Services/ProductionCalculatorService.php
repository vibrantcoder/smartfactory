<?php

declare(strict_types=1);

namespace App\Domain\Production\Services;

use App\Domain\Factory\Models\FactorySettings;
use App\Domain\Production\Models\PartProcess;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Models\Shift;
use App\Domain\Production\ValueObjects\ActualProductionResult;
use App\Domain\Production\ValueObjects\DailyTargetResult;
use App\Domain\Production\ValueObjects\PlanAnalysisResult;
use App\Domain\Production\ValueObjects\ProductionEfficiencyResult;
use App\Domain\Production\ValueObjects\ShiftTargetResult;
use Illuminate\Support\Collection;

/**
 * ProductionCalculatorService
 *
 * Pure production mathematics — no HTTP, no authentication, no DB writes.
 * Every core method accepts scalar or typed arguments so it is 100% testable
 * without a database connection.
 *
 * ┌──────────────────────────────────────────────────────────────┐
 * │ METHOD MAP                                                    │
 * │                                                              │
 * │  calculateTotalCycleTime()   ← sum of routing step times     │
 * │  calculateDailyTarget()      ← units/day at given efficiency │
 * │  calculateShiftTarget()      ← units/shift                   │
 * │  calculateActualProduction() ← aggregate production_actuals  │
 * │  calculateProductionEfficiency() ← attainment %             │
 * │                                                              │
 * │  HELPERS:                                                    │
 * │  dailyCapacityMinutes()      ← sum active shifts             │
 * │  efficiencyFactorFromSettings() ← oee_target / 100          │
 * │                                                              │
 * │  COMPOSITE:                                                  │
 * │  analyzeProductionPlan()     ← all of the above in one call  │
 * └──────────────────────────────────────────────────────────────┘
 *
 * UNIT CONVENTION: all time values are in MINUTES throughout this service.
 */
class ProductionCalculatorService
{
    // ══════════════════════════════════════════════════════════
    //  1. CALCULATE TOTAL CYCLE TIME
    // ══════════════════════════════════════════════════════════

    /**
     * Sum effective cycle times across all routing steps.
     *
     * REQUIRES: $processes must be a Collection<PartProcess> with the
     *           processMaster relation already loaded (no lazy-load here).
     *
     * Each step's effective time resolves as:
     *   part_processes.standard_cycle_time   (override — if set)
     *   ?? process_masters.standard_time     (library default)
     *   ?? 0.0                               (unset — step has no time cost)
     *
     * @param  Collection<PartProcess> $processes
     * @return float  Total minutes
     */
    public function calculateTotalCycleTime(Collection $processes): float
    {
        if ($processes->isEmpty()) {
            return 0.0;
        }

        return (float) $processes->sum(
            fn(PartProcess $step) => $step->effectiveCycleTime()
        );
    }

    // ══════════════════════════════════════════════════════════
    //  2. CALCULATE DAILY TARGET
    // ══════════════════════════════════════════════════════════

    /**
     * Calculate how many units can be produced in a full working day.
     *
     * FORMULA:
     *   effective_minutes   = daily_capacity_min × efficiency_factor
     *   target_qty          = ⌊ effective_minutes / cycle_time_min ⌋
     *   theoretical_max_qty = ⌊ daily_capacity_min / cycle_time_min ⌋  (100% OEE)
     *   capacity_gap_qty    = theoretical_max_qty − target_qty
     *
     * @param  float $cycleTimeMinutes      Total minutes to produce one unit
     *                                      (from calculateTotalCycleTime or part.total_cycle_time)
     * @param  int   $dailyCapacityMinutes  Total planned minutes in a work day.
     *                                      Use FactorySettings::working_hours_per_day × 60
     *                                      OR dailyCapacityMinutes() helper from active shifts.
     * @param  float $efficiencyFactor      0.0–1.0. Apply OEE target as planning assumption.
     *                                      Use efficiencyFactorFromSettings() helper.
     *                                      Default 1.0 = no efficiency loss assumed.
     * @return DailyTargetResult
     */
    public function calculateDailyTarget(
        float $cycleTimeMinutes,
        int   $dailyCapacityMinutes,
        float $efficiencyFactor = 1.0,
    ): DailyTargetResult {
        // Clamp efficiency to valid range [0.0, 1.0]
        $efficiencyFactor = max(0.0, min(1.0, $efficiencyFactor));
        $effectiveMinutes = (int) floor($dailyCapacityMinutes * $efficiencyFactor);

        // Cannot calculate if cycle time is zero or unset
        if ($cycleTimeMinutes <= 0.0) {
            return new DailyTargetResult(
                cycleTimeMinutes:     0.0,
                dailyCapacityMinutes: $dailyCapacityMinutes,
                efficiencyFactor:     $efficiencyFactor,
                effectiveMinutes:     $effectiveMinutes,
                targetQty:            0,
                theoreticalMaxQty:    0,
                capacityGapQty:       0,
                isCycleTimeSet:       false,
                dailyCapacityHours:   round($dailyCapacityMinutes / 60, 2),
            );
        }

        $targetQty         = (int) floor($effectiveMinutes / $cycleTimeMinutes);
        $theoreticalMaxQty = (int) floor($dailyCapacityMinutes / $cycleTimeMinutes);
        $capacityGapQty    = max(0, $theoreticalMaxQty - $targetQty);

        return new DailyTargetResult(
            cycleTimeMinutes:     $cycleTimeMinutes,
            dailyCapacityMinutes: $dailyCapacityMinutes,
            efficiencyFactor:     $efficiencyFactor,
            effectiveMinutes:     $effectiveMinutes,
            targetQty:            $targetQty,
            theoreticalMaxQty:    $theoreticalMaxQty,
            capacityGapQty:       $capacityGapQty,
            isCycleTimeSet:       true,
            dailyCapacityHours:   round($dailyCapacityMinutes / 60, 2),
        );
    }

    // ══════════════════════════════════════════════════════════
    //  3. CALCULATE SHIFT TARGET
    // ══════════════════════════════════════════════════════════

    /**
     * Calculate how many units can be produced within a single shift.
     *
     * Uses Shift::duration_min as the available production window.
     * The same OEE efficiency factor is applied as for daily targets.
     *
     * FORMULA:
     *   effective_minutes   = shift.duration_min × efficiency_factor
     *   target_qty          = ⌊ effective_minutes / cycle_time_min ⌋
     *
     * @param  float $cycleTimeMinutes  Total minutes per unit
     * @param  Shift $shift             The shift providing the time window
     * @param  float $efficiencyFactor  0.0–1.0. Typically oee_target_pct / 100.
     * @return ShiftTargetResult
     */
    public function calculateShiftTarget(
        float $cycleTimeMinutes,
        Shift $shift,
        float $efficiencyFactor = 1.0,
    ): ShiftTargetResult {
        $efficiencyFactor = max(0.0, min(1.0, $efficiencyFactor));
        $effectiveMinutes = (int) floor($shift->duration_min * $efficiencyFactor);

        if ($cycleTimeMinutes <= 0.0) {
            return new ShiftTargetResult(
                shiftId:             $shift->id,
                shiftName:           $shift->name,
                cycleTimeMinutes:    0.0,
                shiftDurationMinutes:$shift->duration_min,
                efficiencyFactor:    $efficiencyFactor,
                effectiveMinutes:    $effectiveMinutes,
                targetQty:           0,
                theoreticalMaxQty:   0,
                capacityGapQty:      0,
                isCycleTimeSet:      false,
            );
        }

        $targetQty         = (int) floor($effectiveMinutes / $cycleTimeMinutes);
        $theoreticalMaxQty = (int) floor($shift->duration_min / $cycleTimeMinutes);
        $capacityGapQty    = max(0, $theoreticalMaxQty - $targetQty);

        return new ShiftTargetResult(
            shiftId:             $shift->id,
            shiftName:           $shift->name,
            cycleTimeMinutes:    $cycleTimeMinutes,
            shiftDurationMinutes:$shift->duration_min,
            efficiencyFactor:    $efficiencyFactor,
            effectiveMinutes:    $effectiveMinutes,
            targetQty:           $targetQty,
            theoreticalMaxQty:   $theoreticalMaxQty,
            capacityGapQty:      $capacityGapQty,
            isCycleTimeSet:      true,
        );
    }

    // ══════════════════════════════════════════════════════════
    //  4. CALCULATE ACTUAL PRODUCTION
    // ══════════════════════════════════════════════════════════

    /**
     * Aggregate production actuals for a production plan.
     *
     * Runs a single aggregate SQL query against production_actuals —
     * does NOT iterate rows in PHP. Good for plans with many batches.
     *
     * Returns zero values (not null) when no actuals exist yet,
     * so callers don't need to null-guard before calculations.
     *
     * @param  ProductionPlan $plan
     * @return ActualProductionResult
     */
    public function calculateActualProduction(ProductionPlan $plan): ActualProductionResult
    {
        // One aggregate query — SUM + COUNT, no row hydration
        $agg = $plan->actuals()
            ->selectRaw('
                COALESCE(SUM(actual_qty), 0) AS total_actual_qty,
                COALESCE(SUM(defect_qty), 0) AS total_defect_qty,
                COALESCE(SUM(good_qty),   0) AS total_good_qty,
                COUNT(*)                      AS batch_count
            ')
            ->first();

        $actualQty = (int) ($agg->total_actual_qty ?? 0);
        $defectQty = (int) ($agg->total_defect_qty ?? 0);
        $goodQty   = (int) ($agg->total_good_qty   ?? 0);
        $batches   = (int) ($agg->batch_count       ?? 0);

        $defectRate = $actualQty > 0
            ? round($defectQty / $actualQty * 100, 2)
            : 0.0;

        $yieldRate = $actualQty > 0
            ? round($goodQty / $actualQty * 100, 2)
            : 0.0;

        return new ActualProductionResult(
            totalActualQty: $actualQty,
            totalDefectQty: $defectQty,
            totalGoodQty:   $goodQty,
            batchCount:     $batches,
            defectRatePct:  $defectRate,
            yieldRatePct:   $yieldRate,
            hasProduction:  $batches > 0,
        );
    }

    // ══════════════════════════════════════════════════════════
    //  5. CALCULATE PRODUCTION EFFICIENCY
    // ══════════════════════════════════════════════════════════

    /**
     * Calculate production efficiency (attainment %) and classify status.
     *
     * FORMULA:
     *   efficiency_pct = (actual_good_qty / planned_qty) × 100
     *   variance_qty   = actual_good_qty − planned_qty   (negative = shortfall)
     *   variance_pct   = variance_qty / planned_qty × 100
     *
     * STATUS CLASSIFICATION (compared against $targetThresholdPct):
     *   'not_started'   actual_good_qty == 0
     *   'exceeded'      efficiency_pct > 100.0
     *   'on_target'     efficiency_pct >= targetThresholdPct
     *   'below_target'  efficiency_pct < targetThresholdPct (but > 0)
     *
     * @param  int   $plannedQty          From production_plans.planned_qty
     * @param  int   $actualGoodQty       From SUM(production_actuals.good_qty)
     * @param  float $targetThresholdPct  Threshold for 'on_target' status.
     *                                    Use FactorySettings::oee_target_pct.
     *                                    Default 85.0 (industry standard OEE threshold).
     * @return ProductionEfficiencyResult
     */
    public function calculateProductionEfficiency(
        int   $plannedQty,
        int   $actualGoodQty,
        float $targetThresholdPct = 85.0,
    ): ProductionEfficiencyResult {
        // Guard against zero planned qty (division by zero)
        if ($plannedQty <= 0) {
            return new ProductionEfficiencyResult(
                plannedQty:          0,
                actualGoodQty:       $actualGoodQty,
                efficiencyPct:       0.0,
                varianceQty:         $actualGoodQty,
                variancePct:         0.0,
                status:              ProductionEfficiencyResult::STATUS_NOT_STARTED,
                isOnTarget:          false,
                targetThresholdPct:  $targetThresholdPct,
            );
        }

        if ($actualGoodQty === 0) {
            return new ProductionEfficiencyResult(
                plannedQty:          $plannedQty,
                actualGoodQty:       0,
                efficiencyPct:       0.0,
                varianceQty:         -$plannedQty,
                variancePct:         -100.0,
                status:              ProductionEfficiencyResult::STATUS_NOT_STARTED,
                isOnTarget:          false,
                targetThresholdPct:  $targetThresholdPct,
            );
        }

        $efficiencyPct = round($actualGoodQty / $plannedQty * 100, 2);
        $varianceQty   = $actualGoodQty - $plannedQty;
        $variancePct   = round($varianceQty / $plannedQty * 100, 2);

        $status = match(true) {
            $efficiencyPct > 100.0                         => ProductionEfficiencyResult::STATUS_EXCEEDED,
            $efficiencyPct >= $targetThresholdPct          => ProductionEfficiencyResult::STATUS_ON_TARGET,
            default                                        => ProductionEfficiencyResult::STATUS_BELOW_TARGET,
        };

        $isOnTarget = in_array(
            $status,
            [ProductionEfficiencyResult::STATUS_ON_TARGET, ProductionEfficiencyResult::STATUS_EXCEEDED],
            true
        );

        return new ProductionEfficiencyResult(
            plannedQty:         $plannedQty,
            actualGoodQty:      $actualGoodQty,
            efficiencyPct:      $efficiencyPct,
            varianceQty:        $varianceQty,
            variancePct:        $variancePct,
            status:             $status,
            isOnTarget:         $isOnTarget,
            targetThresholdPct: $targetThresholdPct,
        );
    }

    // ══════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════

    /**
     * Compute total daily capacity (minutes) from a factory's active shifts.
     *
     * USE CASE: When the factory runs multiple shifts in a day, sum all active
     * shifts' duration_min to get the full daily capacity.
     *
     * EXAMPLE: Morning (480 min) + Afternoon (480 min) + Night (480 min) = 1440 min/day
     *
     * Falls back to FactorySettings::working_hours_per_day × 60 when no shifts
     * are loaded, so callers don't need to null-guard.
     *
     * @param  Collection<Shift> $shifts          Factory's active shift collection
     * @param  FactorySettings|null $fallbackSettings  Used when $shifts is empty
     * @return int  Total minutes
     */
    public function dailyCapacityMinutes(
        Collection      $shifts,
        ?FactorySettings $fallbackSettings = null,
    ): int {
        $activeShifts = $shifts->where('is_active', true);

        if ($activeShifts->isNotEmpty()) {
            return (int) $activeShifts->sum('duration_min');
        }

        // Fallback to factory settings when shifts are not configured
        if ($fallbackSettings !== null) {
            return (int) round((float) $fallbackSettings->working_hours_per_day * 60);
        }

        // Last resort: industry standard single-shift workday
        return 480; // 8 hours × 60 minutes
    }

    /**
     * Extract efficiency factor from FactorySettings as 0.0–1.0 float.
     *
     * Maps oee_target_pct (e.g. 85.00) → 0.85.
     * This is used as the planning assumption for target calculations:
     * "If we achieve our OEE target, how many units can we make?"
     *
     * @param  FactorySettings $settings
     * @return float  0.0–1.0
     */
    public function efficiencyFactorFromSettings(FactorySettings $settings): float
    {
        return max(0.0, min(1.0, (float) $settings->oee_target_pct / 100.0));
    }

    // ══════════════════════════════════════════════════════════
    //  COMPOSITE: FULL PLAN ANALYSIS
    // ══════════════════════════════════════════════════════════

    /**
     * Full production plan analysis — calls all five calculation methods.
     *
     * REQUIRES these relations loaded on $plan before calling:
     *   - part.processes.processMaster  (for cycle time)
     *   - shift                         (for shift target)
     *   - machine                       (for display metadata)
     *
     * Makes exactly ONE database query (aggregate on production_actuals).
     * All other calculations are pure math on already-loaded data.
     *
     * @param  ProductionPlan  $plan
     * @param  FactorySettings $settings
     * @return PlanAnalysisResult
     */
    public function analyzeProductionPlan(
        ProductionPlan  $plan,
        FactorySettings $settings,
    ): PlanAnalysisResult {
        // ── 1. Total cycle time ────────────────────────────────
        $processes          = $plan->part?->processes ?? collect();
        $totalCycleTime     = $this->calculateTotalCycleTime($processes);

        // Use stored total_cycle_time if processes not loaded (performance shortcut)
        if ($processes->isEmpty() && $plan->part?->total_cycle_time > 0) {
            $totalCycleTime = (float) $plan->part->total_cycle_time;
        }

        // ── 2. Shift target ────────────────────────────────────
        $efficiencyFactor = $this->efficiencyFactorFromSettings($settings);
        $shiftTarget      = $this->calculateShiftTarget(
            cycleTimeMinutes: $totalCycleTime,
            shift:            $plan->shift,
            efficiencyFactor: $efficiencyFactor,
        );

        // ── 3. Actual production ───────────────────────────────
        $actual = $this->calculateActualProduction($plan);

        // ── 4. Production efficiency ───────────────────────────
        $efficiency = $this->calculateProductionEfficiency(
            plannedQty:         $plan->planned_qty,
            actualGoodQty:      $actual->totalGoodQty,
            targetThresholdPct: (float) $settings->oee_target_pct,
        );

        // ── 5. Build composite result ──────────────────────────
        return PlanAnalysisResult::build(
            plan:               $plan,
            totalCycleTimeMinutes: $totalCycleTime,
            shiftTarget:        $shiftTarget,
            actual:             $actual,
            efficiency:         $efficiency,
        );
    }

    /**
     * Calculate targets for all active shifts of a factory at once.
     *
     * Used by the daily planning dashboard to show the target breakdown per shift.
     * Returns an array keyed by shift id.
     *
     * @param  float             $cycleTimeMinutes
     * @param  Collection<Shift> $shifts            Active shifts only
     * @param  FactorySettings   $settings
     * @return array<int, ShiftTargetResult>  Keyed by shift id
     */
    public function calculateAllShiftTargets(
        float          $cycleTimeMinutes,
        Collection     $shifts,
        FactorySettings $settings,
    ): array {
        $efficiencyFactor = $this->efficiencyFactorFromSettings($settings);

        return $shifts
            ->where('is_active', true)
            ->mapWithKeys(fn(Shift $shift) => [
                $shift->id => $this->calculateShiftTarget(
                    $cycleTimeMinutes,
                    $shift,
                    $efficiencyFactor,
                ),
            ])
            ->all();
    }
}
