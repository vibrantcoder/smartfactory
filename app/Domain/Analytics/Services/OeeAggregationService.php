<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\MachineOeeShift;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * OeeAggregationService
 *
 * Reads raw iot_logs via OeeCalculationService and upserts results into
 * the machine_oee_shifts summary table.
 *
 * Designed to run every 5 minutes via the Laravel scheduler.
 * Safe to run multiple times (upsert is idempotent).
 *
 * Usage:
 *   app(OeeAggregationService::class)->aggregateFactory($factoryId, Carbon::today());
 *   app(OeeAggregationService::class)->aggregateAll(Carbon::today());
 */
class OeeAggregationService
{
    public function __construct(
        private readonly OeeCalculationService $oeeCalculationService,
    ) {}

    /**
     * Aggregate OEE for all active machines in one factory on one date.
     * Returns count of rows written.
     */
    public function aggregateFactory(int $factoryId, Carbon $date): int
    {
        $machines = Machine::where('factory_id', $factoryId)
            ->where('status', '!=', 'retired')
            ->orderBy('name')
            ->get();

        $shifts = Shift::where('factory_id', $factoryId)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        if ($machines->isEmpty() || $shifts->isEmpty()) {
            return 0;
        }

        $rows    = 0;
        $dateStr = $date->format('Y-m-d');

        foreach ($machines as $machine) {
            foreach ($shifts as $shift) {
                $this->upsertShift($machine, $shift, $date, $dateStr);
                $rows++;
            }
        }

        Log::info("OEE aggregation complete", [
            'factory_id' => $factoryId,
            'date'       => $dateStr,
            'rows'       => $rows,
        ]);

        return $rows;
    }

    /**
     * Aggregate OEE for all non-retired factories on one date.
     * Returns total rows written.
     */
    public function aggregateAll(Carbon $date): int
    {
        $factoryIds = \App\Domain\Factory\Models\Factory::where('status', '!=', 'inactive')
            ->pluck('id');

        $total = 0;
        foreach ($factoryIds as $factoryId) {
            $total += $this->aggregateFactory($factoryId, $date);
        }

        return $total;
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function upsertShift(Machine $machine, Shift $shift, Carbon $date, string $dateStr): void
    {
        try {
            $shiftRows = $this->oeeCalculationService->calculateAllShifts($machine, $date);

            // Find the result for this specific shift
            $matchingRow = $shiftRows->first(fn($r) => $r['shift']->id === $shift->id);

            if ($matchingRow === null) {
                return;
            }

            /** @var \App\Domain\Analytics\DataTransferObjects\OeeResult $oee */
            $oee  = $matchingRow['oee'];
            $plan = $matchingRow['plan'];

            MachineOeeShift::updateOrCreate(
                [
                    'machine_id' => $machine->id,
                    'shift_id'   => $shift->id,
                    'oee_date'   => $dateStr,
                ],
                [
                    'factory_id'           => $machine->factory_id,
                    'planned_qty'          => $oee->plannedQty,
                    'total_parts'          => $oee->totalParts,
                    'good_parts'           => $oee->goodParts,
                    'reject_parts'         => $oee->rejectParts,
                    'planned_minutes'      => $oee->plannedMinutes,
                    'alarm_minutes'        => $oee->alarmMinutes,
                    'available_minutes'    => $oee->availableMinutes,
                    'availability_pct'     => $oee->availabilityPct,
                    'performance_pct'      => $oee->performancePct,
                    'quality_pct'          => $oee->qualityPct,
                    'oee_pct'              => $oee->oeePct,
                    'attainment_pct'       => $oee->attainmentPct,
                    'log_count'            => $oee->logCount,
                    'log_interval_seconds' => $oee->logIntervalSeconds,
                    'calculated_at'        => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error("OEE aggregation failed for machine {$machine->id} shift {$shift->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
