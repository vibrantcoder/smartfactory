<?php

declare(strict_types=1);

namespace App\Domain\Production\ValueObjects;

/**
 * ProductionEfficiencyResult
 *
 * Immutable result of ProductionCalculatorService::calculateProductionEfficiency().
 *
 * efficiency_pct = (actual_good_qty / planned_qty) × 100
 * variance_qty   = actual_good_qty − planned_qty   (negative = shortfall)
 * variance_pct   = variance_qty / planned_qty × 100
 *
 * STATUS RULES:
 *   'not_started'  → actual_good_qty == 0
 *   'exceeded'     → efficiency_pct > 100.0
 *   'on_target'    → efficiency_pct >= oee_threshold (default 85%)
 *   'below_target' → efficiency_pct > 0 && < oee_threshold
 *   'missed'       → plan completed but efficiency_pct < oee_threshold
 *
 * EXAMPLE:
 *   planned = 100, actual_good = 87, oee_threshold = 85%
 *   efficiency_pct = 87.0%, variance_qty = −13, variance_pct = −13%
 *   status = 'on_target' (87 >= 85)
 */
readonly class ProductionEfficiencyResult
{
    public const STATUS_NOT_STARTED  = 'not_started';
    public const STATUS_EXCEEDED     = 'exceeded';
    public const STATUS_ON_TARGET    = 'on_target';
    public const STATUS_BELOW_TARGET = 'below_target';

    public function __construct(
        public int    $plannedQty,
        public int    $actualGoodQty,

        /** efficiency_pct = actualGoodQty / plannedQty × 100 */
        public float  $efficiencyPct,

        /** actualGoodQty − plannedQty; negative means shortfall */
        public int    $varianceQty,

        /** (actualGoodQty − plannedQty) / plannedQty × 100 */
        public float  $variancePct,

        /** 'not_started' | 'exceeded' | 'on_target' | 'below_target' */
        public string $status,

        /** Whether efficiency meets the OEE target threshold */
        public bool   $isOnTarget,

        /** The OEE target threshold used for status classification */
        public float  $targetThresholdPct,
    ) {}

    public function toArray(): array
    {
        return [
            'planned_qty'          => $this->plannedQty,
            'actual_good_qty'      => $this->actualGoodQty,
            'efficiency_pct'       => $this->efficiencyPct,
            'variance_qty'         => $this->varianceQty,
            'variance_pct'         => $this->variancePct,
            'status'               => $this->status,
            'is_on_target'         => $this->isOnTarget,
            'target_threshold_pct' => $this->targetThresholdPct,
        ];
    }
}
