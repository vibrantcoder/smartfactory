<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * IotDemoDataSeeder
 *
 * Seeds realistic IoT telemetry for machine_id=1 (CNC Lathe A):
 *   - March 11 Night Shift  : 22:00 → 06:00 Mar 12 (480 min)
 *   - March 12 Morning Shift: 06:00 → 14:00 Mar 12 (480 min)
 *
 * One record every 60 seconds, following a realistic duty cycle:
 *   - 68% Running  (cycle_state=1, auto_mode=1, alarm_code=0) → part pulses every ~3 min
 *   - 22% Idle     (cycle_state=0, auto_mode=1, alarm_code=0)
 *   - 10% Alarm    (alarm_code>0, cycle_state=0)
 *   - Reject rate ~3%
 *
 * Run: php artisan db:seed --class=IotDemoDataSeeder
 */
class IotDemoDataSeeder extends Seeder
{
    private const MACHINE_ID  = 1;
    private const FACTORY_ID  = 1;
    private const SLAVE_ID    = 1;
    private const SLAVE_NAME  = 'CNC-01';
    private const INTERVAL_S  = 60; // one record per minute

    public function run(): void
    {
        // Clear existing demo data for machine 1 on these two dates so re-running is safe
        DB::table('iot_logs')
            ->where('machine_id', self::MACHINE_ID)
            ->whereDate('logged_at', '2026-03-11')
            ->where('logged_at', '>=', '2026-03-11 22:00:00')
            ->delete();

        DB::table('iot_logs')
            ->where('machine_id', self::MACHINE_ID)
            ->whereDate('logged_at', '2026-03-12')
            ->delete();

        $rows = [];

        // ── Night shift: Mar 11 22:00 → Mar 12 06:00 ─────────────────
        $rows = array_merge($rows, $this->generateShiftRows(
            start:     '2026-03-11 22:00:00',
            minutes:   480,
            profile:   $this->nightShiftProfile(),
        ));

        // ── Morning shift: Mar 12 06:00 → Mar 12 14:00 ───────────────
        $rows = array_merge($rows, $this->generateShiftRows(
            start:     '2026-03-12 06:00:00',
            minutes:   480,
            profile:   $this->morningShiftProfile(),
        ));

        // Batch insert in chunks of 500
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('iot_logs')->insert($chunk);
        }

        $this->command->info('IoT demo data seeded: ' . count($rows) . ' records for machine ' . self::MACHINE_ID);
    }

    /**
     * Generate one row per INTERVAL_S seconds for the given window.
     * $profile is an array of [state, duration_min] pairs that repeat.
     */
    private function generateShiftRows(string $start, int $minutes, array $profile): array
    {
        $rows        = [];
        $ts          = strtotime($start);
        $endTs       = $ts + $minutes * 60;
        $totalMins   = $minutes;
        $min         = 0;
        $profIdx     = 0;
        $profElapsed = 0;
        $partCycle   = 0;     // minutes since last part
        $partInterval= 3;     // one part every N running minutes

        while ($ts < $endTs) {
            // Current profile segment
            $seg = $profile[$profIdx];
            $state         = $seg[0];
            $segDurationMin= $seg[1];

            $isRunning  = $state === 'running';
            $isAlarm    = $state === 'alarm';
            $alarmCode  = $isAlarm ? (5 + ($profIdx % 3)) : 0;   // codes 5,6,7 rotating
            $cycleState = $isRunning ? 1 : 0;
            $autoMode   = $isAlarm ? 0 : 1;

            // Part pulse: 1 every $partInterval minutes while running
            $partCount = 0;
            if ($isRunning) {
                $partCycle++;
                if ($partCycle >= $partInterval) {
                    $partCount = 1;
                    $partCycle = 0;
                }
            } else {
                $partCycle = 0;
            }

            // Random reject (3% chance when a part is produced)
            $partReject = ($partCount > 0 && mt_rand(1, 100) <= 3) ? 1 : 0;

            $now = date('Y-m-d H:i:s', $ts);
            $rows[] = [
                'machine_id'  => self::MACHINE_ID,
                'factory_id'  => self::FACTORY_ID,
                'alarm_code'  => $alarmCode,
                'auto_mode'   => $autoMode,
                'cycle_state' => $cycleState,
                'part_count'  => $partCount,
                'part_reject' => $partReject,
                'slave_id'    => self::SLAVE_ID,
                'slave_name'  => self::SLAVE_NAME,
                'logged_at'   => $now,
                'created_at'  => $now,
            ];

            // Advance time by interval
            $ts          += self::INTERVAL_S;
            $min++;
            $profElapsed += self::INTERVAL_S / 60;

            // Move to next profile segment when current duration is exhausted
            if ($profElapsed >= $segDurationMin) {
                $profElapsed = 0;
                $profIdx     = ($profIdx + 1) % count($profile);
            }
        }

        return $rows;
    }

    /**
     * Night shift duty cycle (68% run / 22% idle / 10% alarm).
     * Pattern repeats: 90 min run → 20 min idle → 10 min alarm → 60 min run → 20 min idle → 10 min alarm → 60 min run → 30 min idle → 10 min alarm → 60 min run → 20 min idle → 10 min alarm → 30 min run → 10 min idle → rest run
     */
    private function nightShiftProfile(): array
    {
        return [
            ['running', 90],
            ['idle',    20],
            ['alarm',    8],
            ['running', 60],
            ['idle',    15],
            ['alarm',    7],
            ['running', 75],
            ['idle',    20],
            ['alarm',    8],
            ['running', 60],
            ['idle',    15],
            ['alarm',    7],
            ['running', 45],
            ['idle',    10],
            ['running', 30],
        ];
    }

    /**
     * Morning shift duty cycle — slightly better performance, fewer alarms.
     */
    private function morningShiftProfile(): array
    {
        return [
            ['running', 100],
            ['idle',     15],
            ['alarm',     5],
            ['running',  80],
            ['idle',     10],
            ['running',  70],
            ['idle',     20],
            ['alarm',    10],
            ['running',  90],
            ['idle',     15],
            ['alarm',     5],
            ['running',  60],
        ];
    }
}
