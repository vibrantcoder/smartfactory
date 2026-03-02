<?php

declare(strict_types=1);

namespace App\Domain\Production\ValueObjects;

/**
 * ActualProductionResult
 *
 * Immutable aggregation of production_actuals for a ProductionPlan.
 *
 * good_qty = actual_qty - defect_qty (GENERATED column in DB, summed here).
 * defect_rate = defect_qty / actual_qty × 100 (quality loss %).
 * yield_rate  = good_qty  / actual_qty × 100 (= 100 - defect_rate).
 */
readonly class ActualProductionResult
{
    public function __construct(
        /** Raw units produced (including defects) */
        public int   $totalActualQty,

        /** Units rejected as defective */
        public int   $totalDefectQty,

        /** Good units delivered: actual - defect */
        public int   $totalGoodQty,

        /** Number of batch/recording entries in production_actuals */
        public int   $batchCount,

        /** Defect rate as percentage of total produced */
        public float $defectRatePct,

        /** Yield rate: good units as % of total produced */
        public float $yieldRatePct,

        /** Whether any actual production has been recorded */
        public bool  $hasProduction,
    ) {}

    public function toArray(): array
    {
        return [
            'total_actual_qty' => $this->totalActualQty,
            'total_defect_qty' => $this->totalDefectQty,
            'total_good_qty'   => $this->totalGoodQty,
            'batch_count'      => $this->batchCount,
            'defect_rate_pct'  => $this->defectRatePct,
            'yield_rate_pct'   => $this->yieldRatePct,
            'has_production'   => $this->hasProduction,
        ];
    }
}
