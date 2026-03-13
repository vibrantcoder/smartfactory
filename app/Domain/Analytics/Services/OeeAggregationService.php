<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\MachineOeeShift;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        // Bust the API response cache so the dashboard immediately sees fresh data
        Cache::forget("factory_oee_{$factoryId}_{$dateStr}");

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
            $oee = $matchingRow['oee'];

            // Compute the shift time window for chart data
            $since = Carbon::parse($dateStr . ' ' . $shift->start_time);
            $until = Carbon::parse($dateStr . ' ' . $shift->end_time);
            if ($shift->crosses_midnight || $until->lte($since)) {
                $until->addDay();
            }

            // Check if an existing row has real data (log_count > 0).
            // If the new calculation has no logs (raw data was purged), preserve
            // the stored OEE metrics and chart_data rather than overwriting with zeros.
            $existingRow = MachineOeeShift::where('machine_id', $machine->id)
                ->where('shift_id',   $shift->id)
                ->where('oee_date',   $dateStr)
                ->first();

            $hasNoNewLogs   = $oee->logCount === 0;
            $hasStoredData  = $existingRow !== null && $existingRow->log_count > 0;

            if ($hasNoNewLogs && $hasStoredData) {
                // Raw logs purged — keep the previously stored snapshot intact.
                // Only refresh calculated_at so operators can see it was checked.
                $existingRow->touch();
                return;
            }

            $chartData = $this->computeChartData($machine->id, $since, $until);

            // Preserve existing chart_data if new calculation yields no chart points
            // (e.g. logs partially purged but OEE metric row still has data).
            if (empty($chartData['labels']) && $existingRow?->chart_data !== null) {
                $chartData = $existingRow->chart_data;
            }

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
                    'chart_data'           => $chartData,
                    'calculated_at'        => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error("OEE aggregation failed for machine {$machine->id} shift {$shift->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Compute the hourly chart snapshot for one machine over a shift window.
     *
     * Mirrors the logic in IotController::machineChart so the stored JSON
     * can be served as a drop-in replacement once raw iot_logs are purged.
     */
    private function computeChartData(int $machineId, Carbon $since, Carbon $until): array
    {
        // Seed the LAG window with the row just before the shift starts
        $seed   = DB::selectOne(
            "SELECT part_count, part_reject FROM iot_logs
              WHERE machine_id = ? AND logged_at < ?
              ORDER BY logged_at DESC LIMIT 1",
            [$machineId, $since]
        );
        $seedPc = (int) ($seed?->part_count  ?? 0);
        $seedPr = (int) ($seed?->part_reject ?? 0);

        $rows = collect(DB::select("
            SELECT
                DATE_FORMAT(logged_at, '%Y-%m-%d %H:00:00')                                      AS hour,
                SUM(CASE WHEN part_count  = 1 AND prev_pc = 0 THEN 1 ELSE 0 END)                AS parts_sum,
                SUM(CASE WHEN part_reject = 1 AND prev_pr = 0 THEN 1 ELSE 0 END)                AS rejects_sum,
                SUM(CASE WHEN alarm_code > 0 AND cycle_state = 0 THEN 1 ELSE 0 END)             AS alarm_events,
                SUM(CASE WHEN cycle_state = 1                    THEN 1 ELSE 0 END)             AS run_ticks_hr,
                SUM(CASE WHEN cycle_state = 0 AND alarm_code = 0 THEN 1 ELSE 0 END)             AS idle_ticks_hr,
                COUNT(*)                                                                          AS samples
            FROM (
                SELECT *,
                       COALESCE(LAG(part_count,  1) OVER (ORDER BY logged_at, id), ?) AS prev_pc,
                       COALESCE(LAG(part_reject, 1) OVER (ORDER BY logged_at, id), ?) AS prev_pr
                FROM iot_logs
                WHERE machine_id = ? AND logged_at >= ? AND logged_at < ?
            ) sub
            GROUP BY DATE_FORMAT(logged_at, '%Y-%m-%d %H:00:00')
            ORDER BY hour
        ", [$seedPc, $seedPr, $machineId, $since, $until]));

        $ts = DB::table('iot_logs')
            ->where('machine_id', $machineId)
            ->where('logged_at', '>=', $since)
            ->where('logged_at', '<',  $until)
            ->selectRaw("
                COUNT(*)                                                                        AS total_samples,
                SUM(CASE WHEN cycle_state = 1                    THEN 1 ELSE 0 END)           AS run_ticks,
                SUM(CASE WHEN cycle_state = 0 AND alarm_code = 0 THEN 1 ELSE 0 END)           AS idle_ticks,
                SUM(CASE WHEN alarm_code > 0 AND cycle_state = 0 THEN 1 ELSE 0 END)           AS alarm_ticks,
                TIMESTAMPDIFF(SECOND, MIN(logged_at), MAX(logged_at))                         AS span_seconds
            ")
            ->first();

        $totalSamples = (int) ($ts->total_samples ?? 0);
        $spanSeconds  = (int) ($ts->span_seconds  ?? 0);
        $intervalSec  = $totalSamples > 1 ? round($spanSeconds / ($totalSamples - 1), 1) : 5.0;
        $runTicks     = (int) ($ts->run_ticks   ?? 0);
        $idleTicks    = (int) ($ts->idle_ticks  ?? 0);
        $alarmTicks   = (int) ($ts->alarm_ticks ?? 0);

        $runSec      = (int) round($runTicks   * $intervalSec);
        $idleSec     = (int) round($idleTicks  * $intervalSec);
        $alarmSec    = (int) round($alarmTicks * $intervalSec);
        $uptimeSec   = $runSec + $idleSec;
        $totalIotSec = $uptimeSec + $alarmSec;

        $availabilityPct = $totalIotSec > 0 ? round($uptimeSec / $totalIotSec * 100, 1) : 0.0;
        $spindleUtilPct  = $totalIotSec > 0 ? round($runSec    / $totalIotSec * 100, 1) : 0.0;

        $totalParts      = (int) $rows->sum('parts_sum');
        $totalRejects    = (int) $rows->sum('rejects_sum');
        $totalAlarms     = (int) $rows->sum('alarm_events');
        $partsPerRunHour = $runSec > 0 ? round($totalParts / ($runSec / 3600), 1) : null;

        return [
            'labels'                => $rows->pluck('hour')->map(fn ($h) => substr((string) $h, 0, 16))->all(),
            'parts_per_hour'        => $rows->pluck('parts_sum')->map(fn ($v) => (int) $v)->all(),
            'rejects_per_hour'      => $rows->pluck('rejects_sum')->map(fn ($v) => (int) $v)->all(),
            'alarms_per_hour'       => $rows->pluck('alarm_events')->map(fn ($v) => (int) $v)->all(),
            'spindle_util_per_hour' => $rows->map(fn ($r) =>
                (int) $r->samples > 0 ? round(((int) $r->run_ticks_hr  / (int) $r->samples) * 100, 1) : 0.0
            )->all(),
            'idle_pct_per_hour'     => $rows->map(fn ($r) =>
                (int) $r->samples > 0 ? round(((int) $r->idle_ticks_hr / (int) $r->samples) * 100, 1) : 0.0
            )->all(),
            'alarm_pct_per_hour'    => $rows->map(fn ($r) =>
                (int) $r->samples > 0 ? round(((int) $r->alarm_events  / (int) $r->samples) * 100, 1) : 0.0
            )->all(),
            'summary' => [
                'total_parts'   => $totalParts,
                'total_rejects' => $totalRejects,
                'defect_rate'   => $totalParts > 0 ? round($totalRejects / $totalParts * 100, 2) : 0.0,
                'alarm_events'  => $totalAlarms,
            ],
            'time_stats' => [
                'log_interval_seconds' => $intervalSec,
                'total_samples'        => $totalSamples,
                'run_ticks'            => $runTicks,
                'idle_ticks'           => $idleTicks,
                'alarm_ticks'          => $alarmTicks,
                'run_seconds'          => $runSec,
                'idle_seconds'         => $idleSec,
                'alarm_seconds'        => $alarmSec,
                'availability_pct'     => $availabilityPct,
                'spindle_util_pct'     => $spindleUtilPct,
                'parts_per_run_hour'   => $partsPerRunHour,
            ],
        ];
    }
}
