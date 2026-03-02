<?php

declare(strict_types=1);

namespace App\Domain\Production\ValueObjects;

/**
 * DailyTargetResult
 *
 * Immutable result of ProductionCalculatorService::calculateDailyTarget().
 *
 * FORMULA:
 *   effective_minutes = daily_capacity_min × efficiency_factor   (OEE-adjusted capacity)
 *   target_qty        = floor(effective_minutes / cycle_time_min) (realistic target)
 *   theoretical_max   = floor(daily_capacity_min / cycle_time_min) (100% OEE, no losses)
 *
 * EXAMPLE:
 *   daily_capacity = 480 min (8h), cycle_time = 12 min, efficiency = 0.85
 *   effective_minutes   = 480 × 0.85 = 408 min
 *   target_qty          = floor(408 / 12) = 34 pcs
 *   theoretical_max_qty = floor(480 / 12) = 40 pcs
 *   capacity_gap_qty    = 40 − 34 = 6 pcs (lost to OEE inefficiency)
 */
readonly class DailyTargetResult
{
    public function __construct(
        /** Minutes to produce one unit (sum of all routing steps) */
        public float $cycleTimeMinutes,

        /** Total planned production minutes in a day (from factory settings or sum of shifts) */
        public int   $dailyCapacityMinutes,

        /** OEE efficiency factor applied (0.0–1.0). Typically oee_target_pct / 100 */
        public float $efficiencyFactor,

        /** Adjusted available minutes after applying efficiency factor */
        public int   $effectiveMinutes,

        /** Planned daily output assuming given efficiency factor */
        public int   $targetQty,

        /** Max output at 100% OEE — the theoretical ceiling */
        public int   $theoreticalMaxQty,

        /** Qty lost to planned efficiency losses (theoretical_max - target) */
        public int   $capacityGapQty,

        /** Whether cycle time was set (false = cannot calculate targets) */
        public bool  $isCycleTimeSet,

        /** Daily capacity expressed in hours for display */
        public float $dailyCapacityHours,
    ) {}

    public function toArray(): array
    {
        return [
            'cycle_time_minutes'    => $this->cycleTimeMinutes,
            'daily_capacity_minutes'=> $this->dailyCapacityMinutes,
            'daily_capacity_hours'  => $this->dailyCapacityHours,
            'efficiency_factor'     => $this->efficiencyFactor,
            'efficiency_pct'        => round($this->efficiencyFactor * 100, 1),
            'effective_minutes'     => $this->effectiveMinutes,
            'target_qty'            => $this->targetQty,
            'theoretical_max_qty'   => $this->theoreticalMaxQty,
            'capacity_gap_qty'      => $this->capacityGapQty,
            'is_cycle_time_set'     => $this->isCycleTimeSet,
        ];
    }
}
