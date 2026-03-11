<?php

declare(strict_types=1);

namespace App\Domain\Production\Services;

use App\Domain\Production\Models\PartProcess;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Models\Shift;
use App\Domain\Production\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ProductionSchedulingService
 *
 * Auto-schedules a WorkOrder into ProductionPlan records spread across calendar
 * days, respecting existing per-shift machine load.
 *
 * ALGORITHM:
 *   1. Resolve cycle time from part routing or part std time.
 *   2. Load all requested shifts with their planned_min capacity.
 *   3. For each date starting at $startDate:
 *      For each selected shift (in order):
 *        a. Query already-used minutes for this machine × date × shift.
 *        b. Free minutes = shift.planned_min − used_min.
 *        c. Schedule as many units as fit; create one ProductionPlan per slot.
 *      Advance to next calendar day until all qty is placed.
 *   4. All inserts wrapped in a single DB transaction.
 *
 * UNITS: all time values are in MINUTES.
 */
class ProductionSchedulingService
{
    /**
     * Schedule a work order across days using one or more shifts on one machine.
     *
     * @param  WorkOrder $wo            Work order to schedule
     * @param  int       $machineId     Target machine
     * @param  array     $shiftIds      One or more shift IDs (ordered)
     * @param  string    $startDate     First candidate date (Y-m-d)
     * @param  int       $factoryId     Factory owning the plans
     * @param  int|null  $partProcessId Optional routing step for cycle time
     * @param  int|null  $planQty       Partial qty (null = full WO qty)
     * @return ProductionPlan[]
     *
     * @throws \DomainException
     */
    public function schedule(
        WorkOrder $wo,
        int       $machineId,
        array     $shiftIds,
        string    $startDate,
        int       $factoryId,
        ?int      $partProcessId = null,
        ?int      $planQty = null,
        array     $weekOffDays = [],        // 0=Sun … 6=Sat
        array     $holidayDates = [],       // ['Y-m-d', ...]
        bool      $allowWeekOffHoliday = false,
    ): array {
        // ── 1. Load shifts and their capacities ───────────────
        $shifts = Shift::whereIn('id', $shiftIds)
            ->orderByRaw('FIELD(id, ' . implode(',', $shiftIds) . ')')
            ->get();

        if ($shifts->isEmpty()) {
            throw new \DomainException('No valid shifts selected.');
        }

        foreach ($shifts as $shift) {
            if ($shift->planned_min <= 0) {
                throw new \DomainException(
                    "Shift \"{$shift->name}\" has no available working time after breaks."
                );
            }
        }

        // ── 2. Resolve cycle time (minutes per unit) ───────────
        $partProcess  = null;
        $cycleTimeMin = 0.0;

        if ($partProcessId !== null) {
            $partProcess  = PartProcess::with('processMaster')->find($partProcessId);
            $cycleTimeMin = $partProcess ? $partProcess->effectiveCycleTime() : 0.0;
        }

        $wo->loadMissing('part');
        $part = $wo->part;

        if ($cycleTimeMin <= 0.0) {
            $cycleTimeMin = $this->resolveCycleTimeMinutes($part);
        }

        $processLabel = $partProcess
            ? ($partProcess->processMaster?->name ?? "Process #{$partProcessId}")
            : "Part \"{$part->name}\"";

        if ($cycleTimeMin <= 0.0) {
            throw new \DomainException(
                "{$processLabel} has no cycle time defined. " .
                "Set the process cycle time or part routing before scheduling."
            );
        }

        // ── 3. Validate at least one shift can fit one unit ───
        $maxShiftCapacity = $shifts->max('planned_min');
        if ($maxShiftCapacity < $cycleTimeMin) {
            throw new \DomainException(
                "No selected shift has enough capacity (" . round($cycleTimeMin, 2) . " min/unit required)."
            );
        }

        // ── 4. Distribute across days × shifts ────────────────
        $plans     = [];
        $remaining = $planQty !== null
            ? max(1, $planQty)
            : (int) $wo->total_planned_qty;
        $date    = Carbon::parse($startDate);
        $maxDays = 730;

        DB::transaction(function () use (
            &$plans, &$remaining, $date, $maxDays,
            $machineId, $shifts, $cycleTimeMin,
            $wo, $factoryId, $partProcessId,
            $weekOffDays, $holidayDates, $allowWeekOffHoliday,
        ) {
            $day = 0;
            while ($remaining > 0 && $day < $maxDays) {
                $day++;

                // Skip week-off and holiday dates unless override is set
                if (!$allowWeekOffHoliday) {
                    $dow        = (int) $date->dayOfWeek; // 0=Sun, 6=Sat
                    $dateStr    = $date->toDateString();
                    $isWeekOff  = in_array($dow, $weekOffDays, true);
                    $isHoliday  = in_array($dateStr, $holidayDates, true);
                    if ($isWeekOff || $isHoliday) {
                        $date->addDay();
                        continue;
                    }
                }

                $scheduledThisDay = false;

                foreach ($shifts as $shift) {
                    if ($remaining <= 0) break;

                    $usedMin  = $this->getMachineShiftUsedMinutes($machineId, $date->toDateString(), $shift->id);
                    $freeMin  = max(0.0, (float) $shift->planned_min - $usedMin);

                    if ($freeMin < $cycleTimeMin) {
                        continue; // this shift is full today, try next
                    }

                    $qtyThisSlot = min($remaining, (int) floor($freeMin / $cycleTimeMin));

                    $plan = ProductionPlan::create([
                        'factory_id'      => $factoryId,
                        'machine_id'      => $machineId,
                        'part_id'         => $wo->part_id,
                        'part_process_id' => $partProcessId,
                        'work_order_id'   => $wo->id,
                        'shift_id'        => $shift->id,
                        'planned_date'    => $date->toDateString(),
                        'planned_qty'     => $qtyThisSlot,
                        'status'          => 'draft',
                    ]);

                    $plans[]   = $plan;
                    $remaining -= $qtyThisSlot;
                    $scheduledThisDay = true;
                }

                $date->addDay();
            }
        });

        return $plans;
    }

    /**
     * Used minutes for a machine on a date across ALL shifts.
     * Used by MachineLoadController and availability check.
     */
    public function getMachineUsedMinutes(int $machineId, string $date): float
    {
        return $this->queryUsedMinutes($machineId, $date, null);
    }

    /**
     * Used minutes for a machine on a date for a SPECIFIC shift.
     * Used by the multi-shift scheduling loop.
     */
    public function getMachineShiftUsedMinutes(int $machineId, string $date, int $shiftId): float
    {
        return $this->queryUsedMinutes($machineId, $date, $shiftId);
    }

    private function queryUsedMinutes(int $machineId, string $date, ?int $shiftId): float
    {
        $query = DB::table('production_plans')
            ->join('parts', 'parts.id', '=', 'production_plans.part_id')
            ->leftJoin('part_processes', 'part_processes.id', '=', 'production_plans.part_process_id')
            ->leftJoin('process_masters', 'process_masters.id', '=', 'part_processes.process_master_id')
            ->where('production_plans.machine_id', $machineId)
            ->whereDate('production_plans.planned_date', $date)
            ->whereNotIn('production_plans.status', ['cancelled']);

        if ($shiftId !== null) {
            $query->where('production_plans.shift_id', $shiftId);
        }

        $result = $query->selectRaw('
            COALESCE(SUM(
                production_plans.planned_qty *
                COALESCE(
                    part_processes.standard_cycle_time,
                    process_masters.standard_time,
                    NULLIF(parts.total_cycle_time, 0),
                    parts.cycle_time_std / 60,
                    0
                )
            ), 0) AS used_minutes
        ')->value('used_minutes');

        return (float) ($result ?? 0.0);
    }

    private function resolveCycleTimeMinutes(\App\Domain\Production\Models\Part $part): float
    {
        if (!empty($part->total_cycle_time) && (float) $part->total_cycle_time > 0) {
            return (float) $part->total_cycle_time;
        }

        if (!empty($part->cycle_time_std) && (float) $part->cycle_time_std > 0) {
            return (float) $part->cycle_time_std / 60.0;
        }

        return 0.0;
    }
}
