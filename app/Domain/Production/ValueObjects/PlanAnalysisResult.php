<?php

declare(strict_types=1);

namespace App\Domain\Production\ValueObjects;

use App\Domain\Production\Models\ProductionPlan;

/**
 * PlanAnalysisResult
 *
 * Composite result of ProductionCalculatorService::analyzeProductionPlan().
 * Aggregates all five calculation results into a single response object.
 *
 * Used by ProductionAnalysisController::planAnalysis() to return a complete
 * plan dashboard snapshot in one API call.
 */
readonly class PlanAnalysisResult
{
    public function __construct(
        /** Plan metadata */
        public int    $planId,
        public string $planStatus,
        public string $plannedDate,
        public int    $plannedQty,
        public string $partNumber,
        public string $partName,
        public string $shiftName,
        public string $machineName,

        /** Total routing cycle time for the part */
        public float  $totalCycleTimeMinutes,

        /** Shift-level capacity and target */
        public ShiftTargetResult $shiftTarget,

        /** Actual production aggregated from production_actuals */
        public ActualProductionResult $actualProduction,

        /** Efficiency: actual good / planned × 100 */
        public ProductionEfficiencyResult $efficiency,

        /** Remaining qty to reach planned target */
        public int    $remainingQty,

        /** Estimated minutes remaining at current cycle time */
        public float  $estimatedRemainingMinutes,
    ) {}

    /**
     * Build from the component value objects.
     * Called by ProductionCalculatorService::analyzeProductionPlan().
     */
    public static function build(
        ProductionPlan           $plan,
        float                    $totalCycleTimeMinutes,
        ShiftTargetResult        $shiftTarget,
        ActualProductionResult   $actual,
        ProductionEfficiencyResult $efficiency,
    ): self {
        $remainingQty = max(0, $plan->planned_qty - $actual->totalGoodQty);
        $estimatedRemainingMinutes = $totalCycleTimeMinutes > 0
            ? round($remainingQty * $totalCycleTimeMinutes, 2)
            : 0.0;

        return new self(
            planId:                    $plan->id,
            planStatus:                $plan->status,
            plannedDate:               $plan->planned_date->toDateString(),
            plannedQty:                $plan->planned_qty,
            partNumber:                $plan->part?->part_number ?? '—',
            partName:                  $plan->part?->name ?? '—',
            shiftName:                 $plan->shift?->name ?? '—',
            machineName:               $plan->machine?->name ?? '—',
            totalCycleTimeMinutes:     $totalCycleTimeMinutes,
            shiftTarget:               $shiftTarget,
            actualProduction:          $actual,
            efficiency:                $efficiency,
            remainingQty:              $remainingQty,
            estimatedRemainingMinutes: $estimatedRemainingMinutes,
        );
    }

    public function toArray(): array
    {
        return [
            'plan' => [
                'id'           => $this->planId,
                'status'       => $this->planStatus,
                'planned_date' => $this->plannedDate,
                'planned_qty'  => $this->plannedQty,
                'part_number'  => $this->partNumber,
                'part_name'    => $this->partName,
                'shift_name'   => $this->shiftName,
                'machine_name' => $this->machineName,
            ],
            'cycle_time_minutes'         => $this->totalCycleTimeMinutes,
            'shift_target'               => $this->shiftTarget->toArray(),
            'actual_production'          => $this->actualProduction->toArray(),
            'efficiency'                 => $this->efficiency->toArray(),
            'remaining_qty'              => $this->remainingQty,
            'estimated_remaining_minutes'=> $this->estimatedRemainingMinutes,
        ];
    }
}
