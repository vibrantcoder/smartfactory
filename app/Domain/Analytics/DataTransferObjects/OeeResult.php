<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DataTransferObjects;

/**
 * OeeResult — Immutable OEE calculation output for one machine × shift × date.
 *
 * OEE = Availability × Performance × Quality
 *
 *   Availability = (planned_minutes - alarm_minutes) / planned_minutes
 *   Performance  = (total_parts × cycle_time_std_sec) / available_seconds
 *                  (null when no production plan / no cycle_time_std)
 *   Quality      = good_parts / total_parts
 *   OEE          = A × P × Q  (null when Performance is unknown)
 *
 * Data source: iot_logs.part_count is treated as a PULSE signal.
 *   SUM(part_count)  = total parts produced in window.
 *   SUM(part_reject) = total rejects in window.
 */
final class OeeResult
{
    public function __construct(
        // ── Time ─────────────────────────────────────────────
        public readonly int $plannedMinutes,
        public readonly int $alarmMinutes,
        public readonly int $availableMinutes,

        // ── Parts ────────────────────────────────────────────
        public readonly int $totalParts,
        public readonly int $rejectParts,
        public readonly int $goodParts,
        public readonly int $plannedQty,          // from production_plans (0 if no plan)

        // ── OEE components (%) ───────────────────────────────
        public readonly float      $availabilityPct,
        public readonly float|null $performancePct,  // null if no cycle_time_std
        public readonly float      $qualityPct,
        public readonly float|null $oeePct,          // null if no cycle_time_std

        // ── Attainment ───────────────────────────────────────
        public readonly float|null $attainmentPct,   // good_parts / planned_qty (null if no plan)

        // ── Metadata ─────────────────────────────────────────
        public readonly int $logCount,
        public readonly int $logIntervalSeconds,
    ) {}

    public function toArray(): array
    {
        return [
            'planned_minutes'      => $this->plannedMinutes,
            'alarm_minutes'        => $this->alarmMinutes,
            'available_minutes'    => $this->availableMinutes,
            'total_parts'          => $this->totalParts,
            'reject_parts'         => $this->rejectParts,
            'good_parts'           => $this->goodParts,
            'planned_qty'          => $this->plannedQty,
            'attainment_pct'       => $this->attainmentPct,
            'availability_pct'     => $this->availabilityPct,
            'performance_pct'      => $this->performancePct,
            'quality_pct'          => $this->qualityPct,
            'oee_pct'              => $this->oeePct,
            'log_count'            => $this->logCount,
            'log_interval_seconds' => $this->logIntervalSeconds,
        ];
    }
}
