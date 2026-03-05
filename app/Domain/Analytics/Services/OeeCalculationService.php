<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\DataTransferObjects\OeeResult;
use App\Domain\Factory\Models\FactorySettings;
use App\Domain\Machine\Models\IotLog;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\ProductionActual;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * OeeCalculationService
 *
 * Calculates OEE (Overall Equipment Effectiveness) from raw IoT pulse data.
 *
 * INPUT  — iot_logs.part_count is a PULSE signal:
 *   Each record with part_count = 1 means ONE part completed at that log interval.
 *   SUM(part_count) over any window = total parts produced in that window.
 *   SUM(part_reject) = total rejects in that window.
 *   Records with alarm_code > 0 = machine was in fault during that interval.
 *
 * OEE FORMULA:
 *   Availability = (planned_min - alarm_min) / planned_min × 100
 *   Performance  = (total_parts × cycle_time_std_sec) / (available_sec) × 100
 *                  Capped at 100 % (machine cannot run faster than design)
 *   Quality      = good_parts / total_parts × 100  (100 % when 0 parts)
 *   OEE          = Availability × Performance × Quality / 10 000
 *
 * Performance requires cycle_time_std from the production plan's part.
 * When no plan exists, Performance and OEE are returned as null.
 */
class OeeCalculationService
{
    /**
     * Calculate OEE for one machine × shift × date.
     *
     * @param Machine    $machine
     * @param Shift      $shift
     * @param Carbon     $date               The calendar date of the shift start
     * @param int|null   $plannedQty         From production_plan (null → no plan)
     * @param float|null $cycleTimeStdSeconds Ideal cycle time per part in seconds (null → no plan)
     */
    public function calculateForShift(
        Machine $machine,
        Shift   $shift,
        Carbon  $date,
        ?int    $plannedQty          = null,
        ?float  $cycleTimeStdSeconds = null,
        ?int    $planId              = null,   // pass to use production_actuals for Quality
    ): OeeResult {
        // ── Time window ──────────────────────────────────────────────
        [$windowStart, $windowEnd] = $this->shiftWindow($shift, $date);

        // ── Factory log interval ─────────────────────────────────────
        $settings       = FactorySettings::resolveFor($machine->factory_id);
        $logIntervalSec = max(1, (int) ($settings->log_interval_seconds ?? 5));

        // ── Aggregate iot_logs in one query ──────────────────────────
        $stats = IotLog::where('machine_id', $machine->id)
            ->where('logged_at', '>=', $windowStart)
            ->where('logged_at', '<',  $windowEnd)
            ->selectRaw(
                'COUNT(*)                                      AS log_count,
                 SUM(CASE WHEN alarm_code > 0 THEN 1 ELSE 0 END) AS alarm_records,
                 COALESCE(SUM(part_count),  0)                AS total_parts,
                 COALESCE(SUM(part_reject), 0)                AS total_rejects'
            )
            ->first();

        $logCount     = (int) ($stats->log_count ?? 0);
        $alarmRecords = (int) ($stats->alarm_records ?? 0);
        $totalParts   = (int) ($stats->total_parts ?? 0);
        $rejectParts  = (int) ($stats->total_rejects ?? 0);
        $goodParts    = max(0, $totalParts - $rejectParts);

        // ── Override Quality with manually recorded production_actuals ────
        // Manual entries (entered by operator) are more accurate than IoT pulses.
        // Use them when at least one actual record exists for this plan.
        if ($planId !== null) {
            $actuals = ProductionActual::where('production_plan_id', $planId)
                ->selectRaw('COALESCE(SUM(actual_qty), 0) as total_actual, COALESCE(SUM(good_qty), 0) as total_good')
                ->first();
            if ($actuals && (int) $actuals->total_actual > 0) {
                $totalParts  = (int) $actuals->total_actual;
                $goodParts   = (int) $actuals->total_good;
                $rejectParts = max(0, $totalParts - $goodParts);
            }
        }

        // ── Availability ─────────────────────────────────────────────
        // planned_min = duration_min − break_min  (break is not productive time)
        $plannedMinutes = $shift->planned_min;   // accessor on Shift model
        // Each alarm record represents one log interval of downtime
        $alarmMinutes   = (int) ceil($alarmRecords * $logIntervalSec / 60);
        $alarmMinutes   = min($alarmMinutes, $plannedMinutes);
        $availMinutes   = max(0, $plannedMinutes - $alarmMinutes);
        $availabilityPct = $plannedMinutes > 0
            ? round($availMinutes / $plannedMinutes * 100, 2)
            : 0.0;

        // ── Performance (requires ideal cycle time) ──────────────────
        $performancePct = null;
        if ($cycleTimeStdSeconds !== null && $cycleTimeStdSeconds > 0 && $availMinutes > 0) {
            $idealSecondsNeeded = $totalParts * $cycleTimeStdSeconds;
            $availableSeconds   = $availMinutes * 60;
            $performancePct     = min(100.0, round($idealSecondsNeeded / $availableSeconds * 100, 2));
        }

        // ── Quality ──────────────────────────────────────────────────
        $qualityPct = $totalParts > 0
            ? round($goodParts / $totalParts * 100, 2)
            : 100.0;

        // ── OEE ──────────────────────────────────────────────────────
        $oeePct = $performancePct !== null
            ? round($availabilityPct * $performancePct * $qualityPct / 10000, 2)
            : null;

        // ── Attainment vs plan ───────────────────────────────────────
        $attainmentPct = ($plannedQty !== null && $plannedQty > 0)
            ? min(999.99, round($goodParts / $plannedQty * 100, 2))
            : null;

        return new OeeResult(
            plannedMinutes:      $plannedMinutes,
            alarmMinutes:        $alarmMinutes,
            availableMinutes:    $availMinutes,
            totalParts:          $totalParts,
            rejectParts:         $rejectParts,
            goodParts:           $goodParts,
            plannedQty:          $plannedQty ?? 0,
            availabilityPct:     $availabilityPct,
            performancePct:      $performancePct,
            qualityPct:          $qualityPct,
            oeePct:              $oeePct,
            attainmentPct:       $attainmentPct,
            logCount:            $logCount,
            logIntervalSeconds:  $logIntervalSec,
        );
    }

    /**
     * Calculate OEE for one machine across ALL active shifts for a given date.
     *
     * Returns a Collection of arrays:
     *   ['shift' => Shift, 'plan' => ?ProductionPlan, 'oee' => OeeResult]
     */
    public function calculateAllShifts(Machine $machine, Carbon $date): Collection
    {
        $shifts = Shift::where('factory_id', $machine->factory_id)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        // Pre-load plans for this machine + date in one query
        $plans = ProductionPlan::where('machine_id', $machine->id)
            ->where('planned_date', $date->format('Y-m-d'))
            ->whereIn('shift_id', $shifts->pluck('id'))
            ->with(['part'])
            ->get()
            ->keyBy('shift_id');

        return $shifts->map(function (Shift $shift) use ($machine, $date, $plans) {
            /** @var ProductionPlan|null $plan */
            $plan = $plans->get($shift->id);

            return [
                'shift' => $shift,
                'plan'  => $plan,
                'oee'   => $this->calculateForShift(
                    $machine,
                    $shift,
                    $date,
                    $plan?->planned_qty,
                    $plan?->part?->cycle_time_std !== null ? (float) $plan->part->cycle_time_std : null,
                    $plan?->id,
                ),
            ];
        });
    }

    /**
     * Calculate OEE for ALL active machines in a factory for a given date.
     *
     * Returns a Collection of arrays:
     *   ['machine' => Machine, 'shifts' => Collection<['shift', 'plan', 'oee']>]
     */
    public function calculateFactoryDay(int $factoryId, Carbon $date): Collection
    {
        $machines = Machine::where('factory_id', $factoryId)
            ->where('status', '!=', 'retired')
            ->orderBy('name')
            ->get();

        return $machines->map(fn(Machine $machine) => [
            'machine' => $machine,
            'shifts'  => $this->calculateAllShifts($machine, $date),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * Build [start, end] Carbon timestamps for a shift on a given date.
     * Handles night shifts that cross midnight.
     */
    private function shiftWindow(Shift $shift, Carbon $date): array
    {
        $dateStr = $date->format('Y-m-d');
        $start   = Carbon::parse("{$dateStr} {$shift->start_time}");

        $crossesMidnight = (bool) ($shift->crosses_midnight ?? false);
        $end = $crossesMidnight
            ? Carbon::parse($date->copy()->addDay()->format('Y-m-d') . " {$shift->end_time}")
            : Carbon::parse("{$dateStr} {$shift->end_time}");

        return [$start, $end];
    }
}
