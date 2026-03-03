<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Iot;

use App\Domain\Machine\Models\IotLog;
use App\Domain\Machine\Models\Machine;
use App\Domain\Machine\Repositories\Contracts\MachineRepositoryInterface;
use App\Domain\Production\Models\Shift;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * IotController
 *
 * Handles all IoT telemetry endpoints:
 *   POST /api/iot/ingest            — public, device-token or demo auth
 *   GET  /api/v1/iot/status         — latest snapshot per machine (Sanctum)
 *   GET  /api/v1/iot/machines/{id}/chart  — 24h hourly breakdown (Sanctum)
 *   GET  /api/v1/iot/machines/{id}/export — CSV download (Sanctum)
 */
class IotController extends Controller
{
    public function __construct(
        private readonly MachineRepositoryInterface $machineRepository,
    ) {}

    // ── Ingest ────────────────────────────────────────────────

    /**
     * POST /api/iot/ingest
     *
     * Public endpoint authenticated by X-Device-Token header (SHA-256 of plaintext).
     * Fallback for demo: machine_id in body, or slavename matches machine code.
     *
     * Expected payload:
     * {
     *   "alarm_code": 0, "auto_mode": 0, "cycle_state": 0,
     *   "part_count": 0, "part_reject": 0,
     *   "slaveID": "130", "slavename": "CNC1",
     *   "status": "current", "timestamp": 1763026487,
     *   "received_at": "2025-11-13 09:34:47"
     * }
     */
    public function ingest(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $machine = $this->resolveDevice($request, $payload);

        if ($machine === null) {
            return response()->json(['error' => 'Machine not found or not authorized'], 404);
        }

        $loggedAt = isset($payload['timestamp'])
            ? Carbon::createFromTimestamp((int) $payload['timestamp'])
            : Carbon::parse($payload['received_at'] ?? now());

        IotLog::create([
            'machine_id'  => $machine->id,
            'factory_id'  => $machine->factory_id,
            'alarm_code'  => (int) ($payload['alarm_code'] ?? 0),
            'auto_mode'   => (int) ($payload['auto_mode'] ?? 0),
            'cycle_state' => (int) ($payload['cycle_state'] ?? 0),
            'part_count'  => (int) ($payload['part_count'] ?? 0),
            'part_reject' => (int) ($payload['part_reject'] ?? 0),
            'slave_id'    => $payload['slaveID'] ?? $payload['slave_id'] ?? null,
            'slave_name'  => $payload['slavename'] ?? $payload['slave_name'] ?? null,
            'logged_at'   => $loggedAt,
            'created_at'  => now(),
        ]);

        return response()->json(['ok' => true], 201);
    }

    // ── Batch ingest ──────────────────────────────────────────

    /**
     * POST /api/iot/ingest/batch
     *
     * Accepts an array of telemetry payloads in one HTTP request.
     * Each payload follows the same format as /api/iot/ingest.
     * Uses a single bulk INSERT — far more efficient for 50+ machines at 1 rec/sec.
     *
     * Body: [ { ...payload1 }, { ...payload2 }, ... ]  (max 500 records)
     */
    public function ingestBatch(Request $request): JsonResponse
    {
        $payloads = $request->json()->all();

        if (!is_array($payloads) || empty($payloads)) {
            return response()->json(['error' => 'Expected a non-empty JSON array'], 422);
        }

        if (count($payloads) > 500) {
            return response()->json(['error' => 'Batch limit is 500 records per request'], 422);
        }

        $rows    = [];
        $now     = now();
        $skipped = 0;

        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                $skipped++;
                continue;
            }

            $machine = $this->resolveDevice($request, $payload);

            if ($machine === null) {
                $skipped++;
                continue;
            }

            $loggedAt = isset($payload['timestamp'])
                ? Carbon::createFromTimestamp((int) $payload['timestamp'])
                : Carbon::parse($payload['received_at'] ?? $now);

            $rows[] = [
                'machine_id'  => $machine->id,
                'factory_id'  => $machine->factory_id,
                'alarm_code'  => (int) ($payload['alarm_code'] ?? 0),
                'auto_mode'   => (int) ($payload['auto_mode'] ?? 0),
                'cycle_state' => (int) ($payload['cycle_state'] ?? 0),
                'part_count'  => (int) ($payload['part_count'] ?? 0),
                'part_reject' => (int) ($payload['part_reject'] ?? 0),
                'slave_id'    => $payload['slaveID'] ?? $payload['slave_id'] ?? null,
                'slave_name'  => $payload['slavename'] ?? $payload['slave_name'] ?? null,
                'logged_at'   => $loggedAt,
                'created_at'  => $now,
            ];
        }

        if (!empty($rows)) {
            // Single bulk INSERT — vastly more efficient than N individual creates
            IotLog::insert($rows);
        }

        return response()->json([
            'ok'      => true,
            'stored'  => count($rows),
            'skipped' => $skipped,
        ], 201);
    }

    // ── Shifts list ───────────────────────────────────────────

    /**
     * GET /api/v1/shifts
     *
     * Returns all active shifts for the authenticated user's factory
     * (or ?factory_id= for super-admin). Used to populate the shift
     * selector on the IoT machine detail dashboard.
     */
    public function shifts(Request $request): JsonResponse
    {
        $user      = $request->user();
        $factoryId = $user->factory_id
            ?? ($request->has('factory_id') ? $request->integer('factory_id') : null);

        $query = Shift::where('is_active', true)->orderBy('start_time');

        if ($factoryId !== null) {
            $query->forFactory($factoryId);
        } else {
            $query->forAnyFactory();
        }

        return response()->json([
            'data' => $query->get(['id', 'name', 'start_time', 'end_time', 'duration_min']),
        ]);
    }

    // ── Status snapshot ───────────────────────────────────────

    /**
     * GET /api/v1/iot/status
     *
     * Returns the latest IoT log snapshot for every machine visible to
     * the authenticated user, with a derived iot_status string.
     *
     * iot_status values:
     *   running  — cycle_state=1 and data fresh
     *   idle     — auto_mode=1, cycle_state=0, data fresh
     *   standby  — auto_mode=0, cycle_state=0, data fresh
     *   alarm    — alarm_code > 0, data fresh
     *   offline  — no data, or last data > 5 minutes ago
     */
    public function status(Request $request): JsonResponse
    {
        $user      = $request->user();
        $factoryId = $user->factory_id
            ?? ($request->has('factory_id') ? $request->integer('factory_id') : null);

        $machineQuery = Machine::query()
            ->ordered()
            ->select(['id', 'name', 'code', 'type', 'status', 'factory_id']);

        if ($factoryId !== null) {
            $machineQuery->forFactory($factoryId);
        } else {
            $machineQuery->forAnyFactory(); // super-admin: see all
        }

        $machines   = $machineQuery->get();
        $machineIds = $machines->pluck('id');

        // Cache status for 30 seconds — matches new polling interval
        $cacheKey = 'iot_status_' . ($factoryId ?? 'all');
        $data = Cache::remember($cacheKey, 30, function () use ($machines, $machineIds) {
            $latestLogs = collect();

            if ($machineIds->isNotEmpty()) {
                // Efficient: one query via self-join on MAX(logged_at) per machine
                $latestTimes = DB::table('iot_logs')
                    ->whereIn('machine_id', $machineIds)
                    ->select('machine_id', DB::raw('MAX(logged_at) as latest_at'))
                    ->groupBy('machine_id');

                $latestLogs = IotLog::joinSub($latestTimes, 'latest', function ($join) {
                    $join->on('iot_logs.machine_id', '=', 'latest.machine_id')
                         ->on('iot_logs.logged_at', '=', 'latest.latest_at');
                })
                ->select('iot_logs.*')
                ->get()
                ->keyBy('machine_id');
            }

            $cutoff = now()->subMinutes(5);

            return $machines->map(function (Machine $machine) use ($latestLogs, $cutoff): array {
                $log = $latestLogs->get($machine->id);
                $iotStatus = $this->deriveStatus($log, $cutoff);

                return [
                    'id'             => $machine->id,
                    'factory_id'     => $machine->factory_id,
                    'name'           => $machine->name,
                    'code'           => $machine->code,
                    'type'           => $machine->type,
                    'machine_status' => $machine->status,
                    'iot_status'     => $iotStatus,
                    'alarm_code'     => $log?->alarm_code ?? 0,
                    'auto_mode'      => (bool) ($log?->auto_mode ?? false),
                    'cycle_state'    => (bool) ($log?->cycle_state ?? false),
                    'part_count'     => $log?->part_count ?? 0,
                    'part_reject'    => $log?->part_reject ?? 0,
                    'slave_name'     => $log?->slave_name,
                    'last_seen'      => $log?->logged_at?->toIso8601String(),
                ];
            });
        });

        return response()->json(['data' => $data]);
    }

    // ── Chart data ────────────────────────────────────────────

    /**
     * GET /api/v1/iot/machines/{machine}/chart?hours=24
     *
     * Returns hourly aggregated telemetry for Chart.js rendering.
     *
     * part_count and part_reject are BINARY PULSE signals (0 or 1 per record).
     * A value of 1 means one part completed / one reject in that scan cycle.
     * We use SUM() — not MAX-MIN — to count pulses correctly.
     */
    public function machineChart(Request $request, Machine $machine): JsonResponse
    {
        $this->authorize('view', $machine);

        // Shift-based window takes priority over hours
        [$since, $until, $hours] = $this->resolveTimeWindow($request);


        $baseQuery = DB::table('iot_logs')
            ->where('machine_id', $machine->id)
            ->where('logged_at', '>=', $since)
            ->where('logged_at', '<', $until);

        $rows = (clone $baseQuery)
            ->selectRaw("
                DATE_FORMAT(logged_at, '%Y-%m-%d %H:00:00')    AS hour,
                COALESCE(SUM(part_count),  0)                  AS parts_sum,
                COALESCE(SUM(part_reject), 0)                  AS rejects_sum,
                SUM(CASE WHEN alarm_code > 0 THEN 1 ELSE 0 END) AS alarm_events,
                COUNT(*)                                        AS samples
            ")
            ->groupByRaw("DATE_FORMAT(logged_at, '%Y-%m-%d %H:00:00')")
            ->orderBy('hour')
            ->get();

        // Time-state breakdown for the whole period
        $ts = (clone $baseQuery)
            ->selectRaw("
                COUNT(*)                                                                                     AS total_samples,
                SUM(CASE WHEN alarm_code = 0 AND cycle_state = 1                            THEN 1 ELSE 0 END) AS run_ticks,
                SUM(CASE WHEN alarm_code = 0 AND cycle_state = 0 AND auto_mode = 1          THEN 1 ELSE 0 END) AS idle_ticks,
                SUM(CASE WHEN alarm_code > 0                                                 THEN 1 ELSE 0 END) AS alarm_ticks,
                TIMESTAMPDIFF(SECOND, MIN(logged_at), MAX(logged_at))                                          AS span_seconds
            ")
            ->first();

        $totalSamples = (int) ($ts->total_samples ?? 0);
        $spanSeconds  = (int) ($ts->span_seconds  ?? 0);
        $intervalSec  = $totalSamples > 1 ? round($spanSeconds / ($totalSamples - 1), 1) : 5.0;
        $runTicks     = (int) ($ts->run_ticks   ?? 0);
        $idleTicks    = (int) ($ts->idle_ticks  ?? 0);
        $alarmTicks   = (int) ($ts->alarm_ticks ?? 0);

        $labels         = $rows->pluck('hour')->map(fn($h) => substr((string) $h, 0, 16))->all();
        $partsPerHour   = $rows->pluck('parts_sum')->map(fn($v) => (int) $v)->all();
        $rejectsPerHour = $rows->pluck('rejects_sum')->map(fn($v) => (int) $v)->all();
        $alarmsPerHour  = $rows->pluck('alarm_events')->map(fn($v) => (int) $v)->all();

        $totalParts   = (int) $rows->sum('parts_sum');
        $totalRejects = (int) $rows->sum('rejects_sum');
        $totalAlarms  = (int) $rows->sum('alarm_events');

        return response()->json([
            'machine' => [
                'id'   => $machine->id,
                'name' => $machine->name,
                'code' => $machine->code,
                'type' => $machine->type,
            ],
            'period_hours'       => $hours,
            'labels'             => $labels,
            'parts_per_hour'     => $partsPerHour,
            'rejects_per_hour'   => $rejectsPerHour,
            'alarms_per_hour'    => $alarmsPerHour,
            'summary' => [
                'total_parts'   => $totalParts,
                'total_rejects' => $totalRejects,
                'defect_rate'   => $totalParts > 0
                    ? round($totalRejects / $totalParts * 100, 2)
                    : 0.0,
                'alarm_events'  => $totalAlarms,
            ],
            'time_stats' => [
                'log_interval_seconds' => $intervalSec,
                'total_samples'        => $totalSamples,
                'run_ticks'            => $runTicks,
                'idle_ticks'           => $idleTicks,
                'alarm_ticks'          => $alarmTicks,
                'run_seconds'          => (int) round($runTicks   * $intervalSec),
                'idle_seconds'         => (int) round($idleTicks  * $intervalSec),
                'alarm_seconds'        => (int) round($alarmTicks * $intervalSec),
            ],
        ]);
    }

    // ── State Timeline ────────────────────────────────────────

    /**
     * GET /api/v1/iot/machines/{machine}/timeline?shift_id=1&date=Y-m-d
     * GET /api/v1/iot/machines/{machine}/timeline?hours=24
     *
     * Returns machine state segments (running / idle / alarm / standby / offline)
     * bucketed into 5-minute intervals and merged into consecutive runs.
     * Used by the horizontal Gantt timeline chart on the IoT dashboard.
     */
    public function machineTimeline(Request $request, Machine $machine): JsonResponse
    {
        $this->authorize('view', $machine);

        [$since, $until] = $this->resolveTimeWindow($request);

        $bucketSec = 300; // 5-minute buckets
        $sinceTs   = $since->timestamp;
        $untilTs   = $until->timestamp;
        $totalMin  = (int) round(($untilTs - $sinceTs) / 60);

        if ($totalMin <= 0) {
            return response()->json([
                'window_from' => $since->format('H:i'),
                'window_to'   => $until->format('H:i'),
                'total_min'   => 0,
                'segments'    => [],
                'summary_min' => (object) [],
            ]);
        }

        // Bucket logs: count state signals per 5-min slot.
        // Use TIMESTAMPDIFF (relative to window start) instead of UNIX_TIMESTAMP so that
        // the bucket number is timezone-independent — UNIX_TIMESTAMP() applies the MySQL
        // server's local timezone which may differ from PHP/app timezone (UTC).
        $sinceStr = $since->format('Y-m-d H:i:s');
        $rows = DB::table('iot_logs')
            ->where('machine_id', $machine->id)
            ->where('logged_at', '>=', $since)
            ->where('logged_at', '<',  $until)
            ->selectRaw("
                FLOOR(TIMESTAMPDIFF(SECOND, '{$sinceStr}', logged_at) / {$bucketSec}) AS bucket_num,
                SUM(CASE WHEN alarm_code  > 0                                          THEN 1 ELSE 0 END) AS alarm_c,
                SUM(CASE WHEN cycle_state = 1                                          THEN 1 ELSE 0 END) AS run_c,
                SUM(CASE WHEN auto_mode  = 1 AND cycle_state = 0 AND alarm_code = 0   THEN 1 ELSE 0 END) AS idle_c
            ")
            ->groupByRaw("FLOOR(TIMESTAMPDIFF(SECOND, '{$sinceStr}', logged_at) / {$bucketSec})")
            ->orderBy('bucket_num')
            ->get();

        // Index by bucket number (0 = first 5-min slot, 1 = next, …)
        $byBucket = $rows->mapWithKeys(fn ($r) => [(string)(int) $r->bucket_num => $r]);

        // Walk every 5-min slot in the window and assign a state
        $totalBuckets = (int) ceil(($untilTs - $sinceTs) / $bucketSec);
        $segments     = [];
        $segState     = null;
        $segFromTs    = $sinceTs;

        for ($bucket = 0; $bucket < $totalBuckets; $bucket++) {
            $ts  = $sinceTs + ($bucket * $bucketSec);
            $row = $byBucket->get((string) $bucket);

            if ($row) {
                if ($row->alarm_c > 0)    $state = 'alarm';
                elseif ($row->run_c > 0)  $state = 'running';
                elseif ($row->idle_c > 0) $state = 'idle';
                else                       $state = 'standby';
            } else {
                $state = 'offline';
            }

            if ($state !== $segState) {
                if ($segState !== null) {
                    $this->pushTimelineSegment(
                        $segments, $segState, $segFromTs,
                        $ts, $since, $sinceTs
                    );
                }
                $segState  = $state;
                $segFromTs = $ts;
            }
        }

        // Flush final segment
        if ($segState !== null) {
            $this->pushTimelineSegment(
                $segments, $segState, $segFromTs, $untilTs, $since, $sinceTs
            );
        }

        // Build summary
        $summary = array_fill_keys(['running', 'idle', 'alarm', 'standby', 'offline'], 0);
        foreach ($segments as $seg) {
            $summary[$seg['state']] += $seg['duration_min'];
        }

        return response()->json([
            'window_from' => $since->format('H:i'),
            'window_to'   => $until->format('H:i'),
            'total_min'   => $totalMin,
            'segments'    => $segments,
            'summary_min' => $summary,
        ]);
    }

    // ── CSV export (API) ──────────────────────────────────────

    /**
     * GET /api/v1/iot/machines/{machine}/export?hours=24
     *
     * Streams raw iot_logs as CSV. Called via the web download route
     * (session auth) to allow direct browser download.
     */
    public function machineExport(Request $request, Machine $machine): StreamedResponse
    {
        $this->authorize('view', $machine);

        [$since, $until] = $this->resolveTimeWindow($request);
        $filename = 'iot-' . $machine->code . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($machine, $since, $until) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'logged_at', 'alarm_code', 'auto_mode', 'cycle_state',
                'part_count', 'part_reject', 'slave_id', 'slave_name',
            ]);

            IotLog::query()
                ->where('machine_id', $machine->id)
                ->where('logged_at', '>=', $since)
                ->where('logged_at', '<',  $until)
                ->orderBy('logged_at')
                ->chunk(1000, function ($logs) use ($handle) {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->logged_at->toDateTimeString(),
                            $log->alarm_code,
                            $log->auto_mode ? 1 : 0,
                            $log->cycle_state ? 1 : 0,
                            $log->part_count,
                            $log->part_reject,
                            $log->slave_id,
                            $log->slave_name,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ── Private helpers ───────────────────────────────────────

    /**
     * Resolve time window from request.
     *
     * Priority:
     *   1. shift_id + date  → shift's start_time to end_time on that date
     *   2. hours            → now() minus N hours
     *
     * Returns [$since, $until, $hours]
     */
    private function resolveTimeWindow(Request $request): array
    {
        $shiftId = $request->query('shift_id');
        $date    = $request->query('date');

        if ($shiftId && $date) {
            $shift = Shift::find((int) $shiftId);
            if ($shift) {
                $since = Carbon::parse($date . ' ' . $shift->start_time);
                $until = Carbon::parse($date . ' ' . $shift->end_time);
                // Overnight shift: end_time is on the next calendar day
                if ($until->lte($since)) {
                    $until->addDay();
                }
                $hours = (int) ceil($since->diffInMinutes($until) / 60);
                return [$since, $until, $hours];
            }
        }

        $hours = (int) $request->query('hours', 24);
        $hours = max(1, min(168, $hours));
        $since = now()->subHours($hours);
        $until = now();

        return [$since, $until, $hours];
    }

    private function pushTimelineSegment(
        array  &$segments,
        string  $state,
        int     $fromTs,
        int     $toTs,
        Carbon  $since,
        int     $sinceTs
    ): void {
        $fromMin = (int) round(($fromTs - $sinceTs) / 60);
        $toMin   = (int) round(($toTs   - $sinceTs) / 60);
        if ($toMin <= $fromMin) {
            return;
        }
        $segments[] = [
            'state'        => $state,
            'from_min'     => $fromMin,
            'to_min'       => $toMin,
            'duration_min' => $toMin - $fromMin,
            'from_label'   => $since->copy()->addMinutes($fromMin)->format('H:i'),
            'to_label'     => $since->copy()->addMinutes($toMin)->format('H:i'),
        ];
    }

    private function resolveDevice(Request $request, array $payload): ?Machine
    {
        // Priority 1: X-Device-Token header (production auth)
        $deviceToken = $request->header('X-Device-Token');
        if ($deviceToken) {
            return $this->machineRepository->findByDeviceToken(hash('sha256', $deviceToken));
        }

        // Priority 2: machine_id in body (demo / dev)
        if (!empty($payload['machine_id'])) {
            return Machine::query()
                ->forAnyFactory()
                ->where('status', '!=', 'retired')
                ->find((int) $payload['machine_id']);
        }

        // Priority 3: slavename → machine code (demo fallback)
        if (!empty($payload['slavename'])) {
            return Machine::query()
                ->forAnyFactory()
                ->where('code', strtoupper($payload['slavename']))
                ->where('status', '!=', 'retired')
                ->first();
        }

        return null;
    }

    private function deriveStatus(?IotLog $log, Carbon $cutoff): string
    {
        if ($log === null) {
            return 'offline';
        }

        if ($log->logged_at->lt($cutoff)) {
            return 'offline';
        }

        if ($log->alarm_code > 0) {
            return 'alarm';
        }

        if ($log->cycle_state) {
            return 'running';
        }

        if ($log->auto_mode) {
            return 'idle';
        }

        return 'standby';
    }
}
