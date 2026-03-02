<?php

declare(strict_types=1);

namespace App\Domain\Production\ValueObjects;

/**
 * ShiftTargetResult
 *
 * Immutable result of ProductionCalculatorService::calculateShiftTarget().
 *
 * FORMULA:
 *   effective_minutes = shift.duration_min × efficiency_factor
 *   target_qty        = floor(effective_minutes / cycle_time_min)
 *
 * EXAMPLE:
 *   shift = Morning (480 min), cycle_time = 12 min, efficiency = 0.90
 *   effective_minutes   = 480 × 0.90 = 432 min
 *   target_qty          = floor(432 / 12) = 36 pcs
 *   theoretical_max_qty = floor(480 / 12) = 40 pcs
 */
readonly class ShiftTargetResult
{
    public function __construct(
        /** Shift id */
        public int    $shiftId,

        /** Shift name for display */
        public string $shiftName,

        /** Minutes to produce one unit */
        public float  $cycleTimeMinutes,

        /** Shift's planned operating minutes */
        public int    $shiftDurationMinutes,

        /** OEE efficiency factor applied */
        public float  $efficiencyFactor,

        /** Available minutes after efficiency adjustment */
        public int    $effectiveMinutes,

        /** Planned shift output at given efficiency */
        public int    $targetQty,

        /** Max output at 100% OEE */
        public int    $theoreticalMaxQty,

        /** Qty lost to planned efficiency losses */
        public int    $capacityGapQty,

        /** Whether cycle time was set */
        public bool   $isCycleTimeSet,
    ) {}

    public function toArray(): array
    {
        return [
            'shift_id'               => $this->shiftId,
            'shift_name'             => $this->shiftName,
            'cycle_time_minutes'     => $this->cycleTimeMinutes,
            'shift_duration_minutes' => $this->shiftDurationMinutes,
            'efficiency_factor'      => $this->efficiencyFactor,
            'efficiency_pct'         => round($this->efficiencyFactor * 100, 1),
            'effective_minutes'      => $this->effectiveMinutes,
            'target_qty'             => $this->targetQty,
            'theoretical_max_qty'    => $this->theoreticalMaxQty,
            'capacity_gap_qty'       => $this->capacityGapQty,
            'is_cycle_time_set'      => $this->isCycleTimeSet,
        ];
    }
}
