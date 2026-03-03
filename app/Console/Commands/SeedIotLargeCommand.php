<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Models\Shift;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * iot:seed-large
 *
 * Generates realistic large-scale IoT telemetry for 50+ machines.
 *
 * Strategy for 50 machines × 1 rec/sec:
 *   - Use --interval=5 for demo (same OEE accuracy, 5× less rows)
 *   - Timestamp-first loop: build ALL machine rows per tick → flush at CHUNK_SIZE
 *   - Bulk INSERT with 2 000 rows/batch → ~1 DB round-trip per 40 machines/tick
 *   - Only generates data during active shifts (06:00–22:00); fast-forwards night
 *
 * Volume estimate (default --machines=50 --days=2 --interval=5):
 *   50 × (2 days × 16 shift-hours × 3600 / 5) = ~1 152 000 rows
 *   Runtime on XAMPP: ~3–8 minutes depending on disk speed
 *
 * Usage:
 *   php artisan iot:seed-large                      # 50 machines, 2 days, 5s
 *   php artisan iot:seed-large --machines=20        # 20 machines
 *   php artisan iot:seed-large --days=7             # 7 days history
 *   php artisan iot:seed-large --interval=1         # 1-second resolution (large!)
 *   php artisan iot:seed-large --fresh              # wipe existing logs first
 *
 * After seeding:
 *   php artisan iot:aggregate-oee                   # populate OEE summary table
 */
class SeedIotLargeCommand extends Command
{
    protected $signature = 'iot:seed-large
        {--machines=50  : How many machines to create / seed (1–200)}
        {--days=2       : Days of history to generate (1–30)}
        {--interval=5   : Seconds between each log record (1–60)}
        {--fresh        : Delete existing iot_logs for seeded machines before inserting}';

    protected $description = 'Seed large-scale IoT telemetry for 50+ machines (demo)';

    /** Rows per INSERT batch — keeps each query fast and memory flat. */
    private const CHUNK_SIZE = 2_000;

    /**
     * Per-machine alarm state machines.
     * machineId → ['alarm_ticks' => int, 'alarm_code' => int]
     */
    private array $machineStates = [];

    /**
     * Eight machine-type templates that cycle across the 50 machines.
     * Each template defines realistic manufacturing characteristics.
     */
    private array $typePool = [
        [
            'type'        => 'CNC Lathe',
            'cycle_sec'   => 12,    // one part every 12 s
            'reject_rate' => 0.025, // 2.5% scrap
            'alarm_prob'  => 0.0007,
            'alarm_len'   => [12, 48],
            'alarm_codes' => [1, 2],
        ],
        [
            'type'        => 'Milling Center',
            'cycle_sec'   => 18,
            'reject_rate' => 0.018,
            'alarm_prob'  => 0.0005,
            'alarm_len'   => [6, 30],
            'alarm_codes' => [3],
        ],
        [
            'type'        => 'Welding Robot',
            'cycle_sec'   => 15,
            'reject_rate' => 0.030,
            'alarm_prob'  => 0.0009,
            'alarm_len'   => [6, 30],
            'alarm_codes' => [6, 7],
        ],
        [
            'type'        => 'Assembly Station',
            'cycle_sec'   => 30,
            'reject_rate' => 0.010,
            'alarm_prob'  => 0.0004,
            'alarm_len'   => [12, 60],
            'alarm_codes' => [4],
        ],
        [
            'type'        => 'Quality Inspection',
            'cycle_sec'   => 5,
            'reject_rate' => 0.045,
            'alarm_prob'  => 0.001,
            'alarm_len'   => [6, 24],
            'alarm_codes' => [2, 5],
        ],
        [
            'type'        => 'Surface Grinder',
            'cycle_sec'   => 8,
            'reject_rate' => 0.020,
            'alarm_prob'  => 0.0006,
            'alarm_len'   => [6, 36],
            'alarm_codes' => [1],
        ],
        [
            'type'        => 'Press Machine',
            'cycle_sec'   => 10,
            'reject_rate' => 0.022,
            'alarm_prob'  => 0.0006,
            'alarm_len'   => [6, 36],
            'alarm_codes' => [1, 3],
        ],
        [
            'type'        => 'Drilling Machine',
            'cycle_sec'   => 7,
            'reject_rate' => 0.015,
            'alarm_prob'  => 0.0005,
            'alarm_len'   => [6, 24],
            'alarm_codes' => [2],
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    //  Entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $machineCount = max(1, min(200, (int) $this->option('machines')));
        $days         = max(1, min(30,  (int) $this->option('days')));
        $interval     = max(1, min(60,  (int) $this->option('interval')));
        $fresh        = (bool) $this->option('fresh');

        // ── Find demo factory ────────────────────────────────────────────────
        $factory = Factory::where('code', 'FAC-DEMO')->first();
        if (! $factory) {
            $this->error('Demo factory (FAC-DEMO) not found. Run DemoSeeder first:');
            $this->line('  php artisan db:seed --class=DemoSeeder');
            return self::FAILURE;
        }

        $shiftHoursPerDay     = 16; // 2 shifts × 8h (06:00–22:00)
        $estimatedRowsPerMach = (int) (($days * $shiftHoursPerDay * 3600) / $interval);
        $estimatedTotal       = $estimatedRowsPerMach * $machineCount;

        $this->info('SmartFactory Large-Scale IoT Seeder');
        $this->line(sprintf('  Factory  : %s', $factory->name));
        $this->line(sprintf('  Machines : %d', $machineCount));
        $this->line(sprintf('  Days     : %d', $days));
        $this->line(sprintf('  Interval : %ds per record', $interval));
        $this->line(sprintf('  Estimate : ~%s rows total', number_format($estimatedTotal)));
        $this->newLine();

        // ── 1. Provision machines ────────────────────────────────────────────
        $this->info('1/4  Provisioning machines...');
        $machines = $this->ensureMachines($factory->id, $machineCount);
        $this->line(sprintf('     → %d machines ready.', $machineCount));

        // ── 2. Provision production plans ────────────────────────────────────
        $this->info('2/4  Creating production plans...');
        $planCount = $this->ensurePlans($factory->id, $machines, $days);
        $this->line(sprintf('     → %d plans created/verified.', $planCount));

        // ── 3. Optionally wipe old logs ──────────────────────────────────────
        if ($fresh) {
            $this->info('3/4  Clearing existing IoT logs...');
            $mIds    = array_column($machines, 'id');
            $deleted = DB::table('iot_logs')->whereIn('machine_id', $mIds)->delete();
            $this->line(sprintf('     → %s records removed.', number_format($deleted)));
        } else {
            $this->line('3/4  Skipping log truncation (use --fresh to wipe first).');
        }

        // ── 4. Initialise alarm state machines ───────────────────────────────
        foreach ($machines as $m) {
            $this->machineStates[$m['id']] = ['alarm_ticks' => 0, 'alarm_code' => 0];
        }

        // ── 5. Generate records ──────────────────────────────────────────────
        $this->info('4/4  Generating records...');

        $shifts = [
            ['start' => '06:00', 'end' => '14:00'],
            ['start' => '14:00', 'end' => '22:00'],
        ];

        $end     = Carbon::now();
        $start   = $end->copy()->subDays($days);
        $current = $start->copy();

        // Estimate total shift ticks for the progress bar
        $shiftSec       = $days * $shiftHoursPerDay * 3600;
        $estimatedTicks = (int) ($shiftSec / $interval);

        $bar = $this->output->createProgressBar($estimatedTicks);
        $bar->setFormat(
            ' %current%/%max% ticks [%bar%] %percent:3s%% '.
            '| %elapsed:6s% elapsed | ETA: %estimated:-6s%'
        );
        $bar->start();

        $buffer    = [];
        $totalRows = 0;

        // Timestamp-first loop: for each tick, emit ONE row per machine.
        // This maximises bulk-insert density (CHUNK_SIZE / machineCount ticks per batch).
        while ($current <= $end) {
            if (! $this->inShift($current, $shifts)) {
                // Skip non-shift seconds in 30-second jumps
                $current->addSeconds(30);
                continue;
            }

            $ts = $current->format('Y-m-d H:i:s');

            foreach ($machines as $m) {
                $buffer[] = $this->buildRow($m, $ts, $interval);

                if (count($buffer) >= self::CHUNK_SIZE) {
                    DB::table('iot_logs')->insert($buffer);
                    $totalRows += count($buffer);
                    $buffer     = [];
                }
            }

            $bar->advance();
            $current->addSeconds($interval);
        }

        // Flush remaining rows
        if ($buffer) {
            DB::table('iot_logs')->insert($buffer);
            $totalRows += count($buffer);
            $buffer     = [];
        }

        // Insert one fresh "now" record per machine so the dashboard shows live status
        $nowTs = $end->format('Y-m-d H:i:s');
        foreach ($machines as $m) {
            DB::table('iot_logs')->insert($this->buildLiveRow($m, $nowTs));
            $totalRows++;
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf('Done! %s records inserted.', number_format($totalRows)));
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  php artisan iot:aggregate-oee        ← populate OEE summary table');
        $this->line('  Open /admin/iot                      ← view the dashboard');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Machine provisioning
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create or find MCH-001 … MCH-0NN, cycling through 8 machine types.
     *
     * @return array<int, array{
     *   id: int, code: string, factory_id: int,
     *   slave_id: string, slave_name: string,
     *   cycle_sec: int, reject_rate: float,
     *   alarm_prob: float, alarm_len: array, alarm_codes: array
     * }>
     */
    private function ensureMachines(int $factoryId, int $count): array
    {
        $result    = [];
        $typeCount = count($this->typePool);

        for ($i = 1; $i <= $count; $i++) {
            $num  = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $code = "MCH-{$num}";
            $tpl  = $this->typePool[($i - 1) % $typeCount];

            $machine = Machine::firstOrCreate(
                ['code' => $code],
                [
                    'factory_id' => $factoryId,
                    'name'       => "{$tpl['type']} {$num}",
                    'type'       => $tpl['type'],
                    'model'      => 'SIM-' . strtoupper(substr($tpl['type'], 0, 3)) . $num,
                    'status'     => 'active',
                ]
            );

            $result[] = [
                'id'          => $machine->id,
                'code'        => $machine->code,
                'factory_id'  => $machine->factory_id,
                'slave_id'    => (string) (100 + $i),
                'slave_name'  => strtoupper(substr($tpl['type'], 0, 4)) . '-' . $num,
                'cycle_sec'   => $tpl['cycle_sec'],
                'reject_rate' => $tpl['reject_rate'],
                'alarm_prob'  => $tpl['alarm_prob'],
                'alarm_len'   => $tpl['alarm_len'],
                'alarm_codes' => $tpl['alarm_codes'],
            ];
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Production plan provisioning
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create one production plan per machine per date (Morning Shift).
     * Planned qty = 90% of theoretical capacity for 8 hours.
     */
    private function ensurePlans(int $factoryId, array $machines, int $days): int
    {
        $shift = Shift::where('factory_id', $factoryId)
            ->where('name', 'Morning Shift')
            ->first();

        $part = Part::where('factory_id', $factoryId)->first();

        if (! $shift || ! $part) {
            $this->warn('  No Morning Shift or Part found — skipping plan creation.');
            return 0;
        }

        $dates = [];
        for ($d = 0; $d < $days; $d++) {
            $dates[] = today()->subDays($d)->toDateString();
        }

        $created = 0;

        foreach ($machines as $m) {
            // 90% of theoretical: 8h × 3600s / cycle_sec × 0.90
            $plannedQty = (int) ((8 * 3600 / $m['cycle_sec']) * 0.90);

            foreach ($dates as $date) {
                $exists = ProductionPlan::where([
                    'factory_id'   => $factoryId,
                    'machine_id'   => $m['id'],
                    'shift_id'     => $shift->id,
                    'planned_date' => $date,
                ])->exists();

                if (! $exists) {
                    ProductionPlan::create([
                        'factory_id'   => $factoryId,
                        'machine_id'   => $m['id'],
                        'shift_id'     => $shift->id,
                        'planned_date' => $date,
                        'part_id'      => $part->id,
                        'planned_qty'  => $plannedQty,
                        'status'       => 'in_progress',
                        'notes'        => 'Large-scale IoT demo plan',
                    ]);
                    $created++;
                }
            }
        }

        return $created;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Row generation
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
     * Build one IoT log row using binary-pulse encoding + alarm state machine.
     *
     * Binary pulse format:
     *   part_count  = 0 | 1   (1 = one part completed in this scan window)
     *   part_reject = 0 | 1   (1 = that part was rejected)
     *
     * P(part in this tick) = interval_sec / cycle_sec
     *   → SUM(part_count) over any window = total parts produced
     *
     * @param array  $m        Machine descriptor from ensureMachines()
     * @param string $ts       MySQL datetime string 'Y-m-d H:i:s'
     * @param int    $interval Seconds per tick
     */
    private function buildRow(array $m, string $ts, int $interval): array
    {
        $state = &$this->machineStates[$m['id']];

        // ── Alarm state machine ──────────────────────────────────────────────
        if ($state['alarm_ticks'] > 0) {
            $state['alarm_ticks']--;
            $alarmCode = $state['alarm_code'];
            if ($state['alarm_ticks'] === 0) {
                $state['alarm_code'] = 0;
            }
        } elseif ((mt_rand() / mt_getrandmax()) < $m['alarm_prob']) {
            $state['alarm_ticks'] = mt_rand($m['alarm_len'][0], $m['alarm_len'][1]);
            $state['alarm_code']  = $m['alarm_codes'][array_rand($m['alarm_codes'])];
            $alarmCode = $state['alarm_code'];
        } else {
            $alarmCode = 0;
        }

        // ── Machine output ───────────────────────────────────────────────────
        if ($alarmCode > 0) {
            // Fault: machine stopped, no parts produced
            return [
                'machine_id'  => $m['id'],
                'factory_id'  => $m['factory_id'],
                'alarm_code'  => $alarmCode,
                'auto_mode'   => 1,
                'cycle_state' => 0,
                'part_count'  => 0,
                'part_reject' => 0,
                'slave_id'    => $m['slave_id'],
                'slave_name'  => $m['slave_name'],
                'logged_at'   => $ts,
                'created_at'  => $ts,
            ];
        }

        // Normal operation: stochastic part-completion pulse
        $partCount  = (mt_rand() / mt_getrandmax()) < ($interval / $m['cycle_sec']) ? 1 : 0;
        $partReject = ($partCount === 1 && (mt_rand() / mt_getrandmax()) < $m['reject_rate']) ? 1 : 0;

        return [
            'machine_id'  => $m['id'],
            'factory_id'  => $m['factory_id'],
            'alarm_code'  => 0,
            'auto_mode'   => 1,
            'cycle_state' => 1,
            'part_count'  => $partCount,
            'part_reject' => $partReject,
            'slave_id'    => $m['slave_id'],
            'slave_name'  => $m['slave_name'],
            'logged_at'   => $ts,
            'created_at'  => $ts,
        ];
    }

    /**
     * Build a "live" row that forces the machine into RUNNING state.
     * Inserted once at $now so the IoT dashboard shows a current green status.
     */
    private function buildLiveRow(array $m, string $ts): array
    {
        return [
            'machine_id'  => $m['id'],
            'factory_id'  => $m['factory_id'],
            'alarm_code'  => 0,
            'auto_mode'   => 1,
            'cycle_state' => 1,
            'part_count'  => 1,
            'part_reject' => 0,
            'slave_id'    => $m['slave_id'],
            'slave_name'  => $m['slave_name'],
            'logged_at'   => $ts,
            'created_at'  => $ts,
        ];
    }
}
