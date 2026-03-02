<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Models\Shift;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * IotLogSeeder
 *
 * Generates 48 hours of realistic binary-pulse IoT telemetry for 6 demo machines.
 *
 * Binary pulse format (as used by the OEE engine):
 *   part_count  = 0 | 1   →  1 = one part completed in this scan cycle
 *   part_reject = 0 | 1   →  1 = that part was rejected
 *
 * Every record = one PLC scan at LOG_INTERVAL_SEC (5 s) during active shifts.
 *
 * Machine live statuses seeded:
 *   MCH-001  →  RUNNING   (green)
 *   MCH-002  →  IDLE      (yellow)
 *   MCH-003  →  ALARM     (red, blinking)
 *   MCH-004  →  RUNNING   (green)
 *   MCH-005  →  IDLE      (yellow)
 *   MCH-006  →  STANDBY   (blue)
 *
 * Usage:
 *   php artisan db:seed --class=IotLogSeeder
 *
 * After seeding run OEE aggregation:
 *   php artisan iot:aggregate-oee
 */
class IotLogSeeder extends Seeder
{
    /** Seconds between each PLC scan / log record */
    private const LOG_INTERVAL_SEC = 5;

    /** Per-machine alarm state machine (persists across buildRow calls) */
    private array $machineStates = [];

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $factory = Factory::where('code', 'FAC-DEMO')->first();
        if (! $factory) {
            $this->command->error('Demo factory not found. Run DemoSeeder first:');
            $this->command->line('  php artisan db:seed --class=DemoSeeder');
            return;
        }

        // ── 1. Provision all 6 demo machines ───────────────────────────────
        $this->command->info('Provisioning demo machines...');
        $machines = $this->ensureMachines($factory->id);

        // ── 2. Provision production plans for OEE performance metric ───────
        $this->command->info('Provisioning production plans (today + yesterday)...');
        $this->ensurePlans($factory->id, $machines);

        // ── 3. Wipe existing demo logs for a clean slate ────────────────────
        $mIds = array_column($machines, 'id');
        $this->command->info('Clearing existing IoT logs for demo machines...');
        $deleted = DB::table('iot_logs')->whereIn('machine_id', $mIds)->delete();
        $this->command->line("  → {$deleted} old records removed.");

        // ── 4. Generate binary-pulse telemetry for the past 48 h ───────────
        $end   = Carbon::now();
        $start = $end->copy()->subHours(48);

        // Shift windows (24-h, local server time)
        $shifts = [
            ['start' => '06:00', 'end' => '14:00'],  // Morning
            ['start' => '14:00', 'end' => '22:00'],  // Afternoon
        ];

        $grandTotal = 0;

        $this->command->newLine();
        $this->command->info('Inserting binary-pulse IoT records...');

        foreach ($machines as $m) {
            $cfg = $this->getConfig($m['code']);

            // Initialise alarm state machine for this machine
            $this->machineStates[$m['id']] = ['alarm_ticks' => 0, 'alarm_code' => 0];

            $this->command->line(sprintf(
                '  [%s]  %-22s  cycle=%ds  reject=%.1f%%  →  %s',
                $m['code'], $m['name'],
                $cfg['cycle_sec'],
                $cfg['reject_rate'] * 100,
                strtoupper($cfg['live_status'])
            ));

            $rows    = [];
            $count   = 0;
            $current = $start->copy();

            while ($current <= $end) {
                if (! $this->inShift($current, $shifts)) {
                    // Skip non-shift time in 30-s steps (no logs outside shifts)
                    $current->addSeconds(30);
                    continue;
                }

                $rows[] = $this->buildRow($m, $cfg, $current);
                $count++;

                // Flush every 500 rows to keep memory flat
                if (count($rows) >= 500) {
                    DB::table('iot_logs')->insert($rows);
                    $rows = [];
                }

                $current->addSeconds(self::LOG_INTERVAL_SEC);
            }

            // Insert tail
            if ($rows) {
                DB::table('iot_logs')->insert($rows);
            }

            // ── Force a fresh "now" record so dashboard shows live status ──
            if ($cfg['live_status'] !== 'offline') {
                $this->insertLive($m, $cfg, $end);
                $count++;
            }
            // 'offline' → no recent record → status derived as offline (>5 min gap)

            $this->command->line("       ✓ {$count} records");
            $grandTotal += $count;
        }

        // ── 5. Summary ───────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info("Total IoT log records inserted: {$grandTotal}");
        $this->command->newLine();
        $this->command->info('Next steps:');
        $this->command->line('  1. php artisan iot:aggregate-oee          ← populate OEE summary table');
        $this->command->line('  2. Open /admin/iot and click any machine  ← view the full dashboard');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Machine provisioning
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ensure 6 demo machines exist; return lightweight arrays for fast access.
     *
     * @return array<int,array{id:int,code:string,name:string,factory_id:int}>
     */
    private function ensureMachines(int $factoryId): array
    {
        $defs = [
            ['code' => 'MCH-001', 'name' => 'CNC Lathe A',        'type' => 'CNC Lathe',  'model' => 'HAAS-ST-10'],
            ['code' => 'MCH-002', 'name' => 'Milling Center B',    'type' => 'Milling',    'model' => 'HAAS-VF-2'],
            ['code' => 'MCH-003', 'name' => 'Quality Inspection',  'type' => 'Inspection', 'model' => 'CMM-ZEISS-01'],
            ['code' => 'MCH-004', 'name' => 'Surface Grinder D',   'type' => 'Grinding',   'model' => 'OKAMOTO-618'],
            ['code' => 'MCH-005', 'name' => 'Assembly Station E',  'type' => 'Assembly',   'model' => 'STATION-A1'],
            ['code' => 'MCH-006', 'name' => 'Welding Robot F',     'type' => 'Welding',    'model' => 'FANUC-ARC-M'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $m = Machine::firstOrCreate(
                ['code' => $def['code']],
                [
                    'factory_id' => $factoryId,
                    'name'       => $def['name'],
                    'type'       => $def['type'],
                    'model'      => $def['model'],
                    'status'     => 'active',
                ]
            );
            $result[] = [
                'id'         => $m->id,
                'code'       => $m->code,
                'name'       => $m->name,
                'factory_id' => $m->factory_id,
            ];
        }

        return $result;
    }

    /**
     * Create production plans for every machine × (today + yesterday) × Morning Shift
     * so that the OEE engine can calculate Performance and OEE% (needs cycle_time_std).
     */
    private function ensurePlans(int $factoryId, array $machines): void
    {
        $shift = Shift::where('factory_id', $factoryId)
            ->where('name', 'Morning Shift')
            ->first();

        $part = Part::where('factory_id', $factoryId)->first();

        if (! $shift || ! $part) {
            $this->command->warn('  No shifts or parts found — skip plan creation.');
            return;
        }

        // Realistic planned quantities per machine (8-hour shift at cycle time × ~90% efficiency)
        $plannedQtyByCode = [
            'MCH-001' => 2160,  // 12s cycle → 2400 theoretical, plan 90%
            'MCH-002' => 1440,  // 18s cycle → 1600 theoretical, plan 90%
            'MCH-003' => 5184,  // 5s  cycle → 5760 theoretical, plan 90%
            'MCH-004' => 3240,  // 8s  cycle → 3600 theoretical, plan 90%
            'MCH-005' => 864,   // 30s cycle → 960  theoretical, plan 90%
            'MCH-006' => 1728,  // 15s cycle → 1920 theoretical, plan 90%
        ];

        $dates = [
            today()->toDateString(),
            today()->subDay()->toDateString(),
            today()->subDays(2)->toDateString(),
        ];

        foreach ($machines as $m) {
            $qty = $plannedQtyByCode[$m['code']] ?? 24;
            foreach ($dates as $date) {
                $plan = ProductionPlan::where([
                    'factory_id'   => $factoryId,
                    'machine_id'   => $m['id'],
                    'shift_id'     => $shift->id,
                    'planned_date' => $date,
                ])->first();

                if ($plan) {
                    // Update planned_qty to realistic value
                    $plan->update(['planned_qty' => $qty, 'status' => 'in_progress']);
                } else {
                    ProductionPlan::create([
                        'factory_id'   => $factoryId,
                        'machine_id'   => $m['id'],
                        'shift_id'     => $shift->id,
                        'planned_date' => $date,
                        'part_id'      => $part->id,
                        'planned_qty'  => $qty,
                        'status'       => 'in_progress',
                        'notes'        => 'IoT demo plan',
                    ]);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Machine configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Per-machine characteristics.
     *
     * cycle_sec   → seconds per finished part (governs part_count pulse probability)
     * reject_rate → fraction of completed parts that are rejected (0.02 = 2%)
     * alarm_prob  → probability per log tick that a new alarm episode starts
     * alarm_len   → [min, max] ticks that an alarm episode lasts
     * live_status → status forced for the "now" record: running|idle|alarm|standby|offline
     */
    private function getConfig(string $code): array
    {
        return match ($code) {
            'MCH-001' => [
                'slave_id'    => '130',   'slave_name'  => 'CNC-A',
                'cycle_sec'   => 12,      'reject_rate' => 0.025,
                'alarm_codes' => [1, 2],  'alarm_prob'  => 0.0007,  'alarm_len' => [12, 48],
                'live_status' => 'running',
            ],
            'MCH-002' => [
                'slave_id'    => '131',   'slave_name'  => 'MILL-B',
                'cycle_sec'   => 18,      'reject_rate' => 0.018,
                'alarm_codes' => [3],     'alarm_prob'  => 0.0005,  'alarm_len' => [6,  30],
                'live_status' => 'idle',
            ],
            'MCH-003' => [
                'slave_id'    => '132',   'slave_name'  => 'CMM-C',
                'cycle_sec'   => 5,       'reject_rate' => 0.045,
                'alarm_codes' => [2, 5],  'alarm_prob'  => 0.001,   'alarm_len' => [6,  24],
                'live_status' => 'alarm',
            ],
            'MCH-004' => [
                'slave_id'    => '133',   'slave_name'  => 'GRD-D',
                'cycle_sec'   => 8,       'reject_rate' => 0.020,
                'alarm_codes' => [1],     'alarm_prob'  => 0.0006,  'alarm_len' => [6,  36],
                'live_status' => 'running',
            ],
            'MCH-005' => [
                'slave_id'    => '134',   'slave_name'  => 'ASM-E',
                'cycle_sec'   => 30,      'reject_rate' => 0.010,
                'alarm_codes' => [4],     'alarm_prob'  => 0.0004,  'alarm_len' => [12, 60],
                'live_status' => 'idle',
            ],
            'MCH-006' => [
                'slave_id'    => '135',   'slave_name'  => 'WLD-F',
                'cycle_sec'   => 15,      'reject_rate' => 0.030,
                'alarm_codes' => [6, 7],  'alarm_prob'  => 0.0009,  'alarm_len' => [6,  30],
                'live_status' => 'standby',
            ],
            default => [
                'slave_id'    => '199',   'slave_name'  => 'MCH-X',
                'cycle_sec'   => 10,      'reject_rate' => 0.020,
                'alarm_codes' => [1],     'alarm_prob'  => 0.0005,  'alarm_len' => [6,  30],
                'live_status' => 'running',
            ],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Core row builder (binary-pulse logic)
    // ─────────────────────────────────────────────────────────────────────────

    /** Returns true when $time falls inside any shift window. */
    private function inShift(Carbon $time, array $shifts): bool
    {
        $t = $time->format('H:i');
        foreach ($shifts as $s) {
            if ($t >= $s['start'] && $t < $s['end']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build one IoT log row using a simple alarm state machine.
     *
     * Binary pulse encoding:
     *   part_count  = 0 or 1   (1 = part completed in this 5-second window)
     *   part_reject = 0 or 1   (1 = that part was a reject)
     *
     * SUM(part_count) over a time range = total parts produced.
     * SUM(part_reject)                  = total rejects.
     */
    private function buildRow(array $m, array $cfg, Carbon $time): array
    {
        $state = &$this->machineStates[$m['id']];

        // ── Alarm state machine ──────────────────────────────────────────────
        if ($state['alarm_ticks'] > 0) {
            // Currently in an alarm episode
            $state['alarm_ticks']--;
            $alarmCode = $state['alarm_code'];
            if ($state['alarm_ticks'] === 0) {
                $state['alarm_code'] = 0; // clear after episode ends
            }
        } elseif ((mt_rand() / mt_getrandmax()) < $cfg['alarm_prob']) {
            // Start a new alarm episode
            $state['alarm_ticks'] = mt_rand($cfg['alarm_len'][0], $cfg['alarm_len'][1]);
            $state['alarm_code']  = $cfg['alarm_codes'][array_rand($cfg['alarm_codes'])];
            $alarmCode = $state['alarm_code'];
        } else {
            $alarmCode = 0;
        }

        // ── Derive machine outputs ───────────────────────────────────────────
        if ($alarmCode > 0) {
            // Faulted: auto_mode stays on, cycle stops, no parts
            $autoMode   = 1;
            $cycleState = 0;
            $partCount  = 0;  // binary: 0
            $partReject = 0;  // binary: 0
        } else {
            $autoMode   = 1;
            $cycleState = 1;

            /*
             * Part-completion pulse probability:
             *   P(part in this tick) = LOG_INTERVAL_SEC / cycle_sec
             *
             * Example: cycle_sec=12, interval=5 → P=0.417
             * Over 12 seconds (2-3 ticks) exactly 1 part fires on average.
             * SUM over a shift gives the correct total-parts count.
             */
            $partCount = (mt_rand() / mt_getrandmax()) < (self::LOG_INTERVAL_SEC / $cfg['cycle_sec'])
                ? 1   // binary pulse: part completed
                : 0;

            // Reject pulse only fires when a part was just completed
            $partReject = ($partCount === 1 && (mt_rand() / mt_getrandmax()) < $cfg['reject_rate'])
                ? 1   // binary pulse: part rejected
                : 0;
        }

        $ts = $time->format('Y-m-d H:i:s');

        return [
            'machine_id'  => $m['id'],
            'factory_id'  => $m['factory_id'],
            'alarm_code'  => $alarmCode,   // 0 = OK, >0 = fault code
            'auto_mode'   => $autoMode,    // 0|1 binary
            'cycle_state' => $cycleState,  // 0|1 binary
            'part_count'  => $partCount,   // 0|1 binary pulse
            'part_reject' => $partReject,  // 0|1 binary pulse
            'slave_id'    => $cfg['slave_id'],
            'slave_name'  => $cfg['slave_name'],
            'logged_at'   => $ts,
            'created_at'  => $ts,
        ];
    }

    /**
     * Insert a single "now" record that forces the machine into its desired
     * live status on the IoT dashboard (refreshed every 5 s).
     */
    private function insertLive(array $m, array $cfg, Carbon $now): void
    {
        $s = $cfg['live_status'];

        DB::table('iot_logs')->insert([
            'machine_id'  => $m['id'],
            'factory_id'  => $m['factory_id'],
            'alarm_code'  => $s === 'alarm'   ? ($cfg['alarm_codes'][0] ?? 1) : 0,
            'auto_mode'   => in_array($s, ['running', 'idle', 'alarm'], true) ? 1 : 0,
            'cycle_state' => $s === 'running' ? 1 : 0,
            'part_count'  => $s === 'running' ? 1 : 0,  // binary pulse
            'part_reject' => 0,
            'slave_id'    => $cfg['slave_id'],
            'slave_name'  => $cfg['slave_name'],
            'logged_at'   => $now->format('Y-m-d H:i:s'),
            'created_at'  => $now->format('Y-m-d H:i:s'),
        ]);
    }
}
