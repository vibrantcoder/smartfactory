<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Analytics\Services\OeeAggregationService;
use App\Domain\Factory\Models\Factory;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Shift;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * iot:shift-end-oee
 *
 * Runs every minute via the scheduler.
 * Detects any shift whose end time was exactly ~10 minutes ago and
 * triggers OEE aggregation for that factory + date so the summary
 * table is ready shortly after each shift closes.
 *
 * Night shifts (crosses_midnight = true) are handled: their end time
 * falls on the next calendar day, but OEE is stored under the shift
 * START date (today).
 *
 * Trigger window: [shift_end + 9 min, shift_end + 11 min] (2-minute
 * tolerance to survive scheduler jitter).
 */
class ShiftEndOeeCommand extends Command
{
    protected $signature   = 'iot:shift-end-oee';
    protected $description = 'Aggregate OEE for any shift that ended ~10 minutes ago';

    private const TRIGGER_AFTER_MIN  = 10;  // wait exactly 10 min after shift end
    private const TRIGGER_WINDOW_MIN = 2;   // 2-min window to absorb scheduler jitter

    public function handle(OeeAggregationService $service): int
    {
        $now = Carbon::now();

        // All active factories
        $factories = Factory::where('status', 'active')->get(['id']);

        $triggered = 0;

        foreach ($factories as $factory) {
            $shifts = Shift::withoutGlobalScopes()
                ->where('factory_id', $factory->id)
                ->where('is_active', true)
                ->get();

            foreach ($shifts as $shift) {
                [$shiftDate, $shiftEnd] = $this->resolveShiftEnd($shift, $now);

                if ($shiftEnd === null) {
                    continue;
                }

                // Minutes since this shift ended
                $minutesSinceEnd = $shiftEnd->diffInMinutes($now, true); // absolute

                // Only trigger if shift ended between TRIGGER_AFTER_MIN
                // and TRIGGER_AFTER_MIN + TRIGGER_WINDOW_MIN minutes ago.
                if (
                    $minutesSinceEnd >= self::TRIGGER_AFTER_MIN &&
                    $minutesSinceEnd < self::TRIGGER_AFTER_MIN + self::TRIGGER_WINDOW_MIN
                ) {
                    $this->info("Shift [{$shift->name}] factory #{$factory->id} ended at "
                        . $shiftEnd->format('H:i') . " — aggregating OEE for {$shiftDate->format('Y-m-d')}");

                    $rows = $service->aggregateFactory($factory->id, $shiftDate);

                    $this->info("  → {$rows} row(s) written.");

                    // ── Purge raw iot_logs for this completed shift window ────────
                    // OEE + chart snapshot are now stored; raw rows are no longer needed.
                    $shiftStart = Carbon::parse($shiftDate->format('Y-m-d') . ' ' . $shift->start_time);

                    $machineIds = Machine::where('factory_id', $factory->id)
                        ->where('status', '!=', 'retired')
                        ->pluck('id');

                    $deleted = 0;
                    if ($machineIds->isNotEmpty()) {
                        $deleted = DB::table('iot_logs')
                            ->whereIn('machine_id', $machineIds)
                            ->where('logged_at', '>=', $shiftStart)
                            ->where('logged_at', '<',  $shiftEnd)
                            ->delete();
                    }

                    $this->info("  → {$deleted} raw IoT log row(s) purged for shift window "
                        . $shiftStart->format('H:i') . '–' . $shiftEnd->format('H:i') . '.');

                    Log::info('iot:shift-end-oee purged raw logs', [
                        'factory_id'  => $factory->id,
                        'shift'       => $shift->name,
                        'oee_date'    => $shiftDate->format('Y-m-d'),
                        'window_from' => $shiftStart->toDateTimeString(),
                        'window_to'   => $shiftEnd->toDateTimeString(),
                        'rows_deleted'=> $deleted,
                    ]);

                    $triggered++;
                }
            }
        }

        if ($triggered === 0) {
            $this->line('No shifts ended ~10 minutes ago. Nothing to aggregate.');
        }

        return Command::SUCCESS;
    }

    /**
     * Returns [shiftDate (Carbon date for oee_date), shiftEnd (Carbon datetime)]
     * for the most recent occurrence of this shift relative to now.
     *
     * For normal shifts: shiftEnd = today @ end_time
     *   If that is in the future → try yesterday @ end_time
     *
     * For night shifts (crosses_midnight): shiftEnd = tomorrow @ end_time
     *   because the shift started today and ends tomorrow morning.
     *   If now is past that end → use today's end time as candidate.
     */
    private function resolveShiftEnd(Shift $shift, Carbon $now): array
    {
        $crossesMidnight = (bool) ($shift->crosses_midnight ?? false);

        if ($crossesMidnight) {
            // Night shift: started yesterday, ends today
            // shiftDate = yesterday (the start date = oee_date)
            // shiftEnd  = today @ end_time
            $candidate = Carbon::parse($now->format('Y-m-d') . ' ' . $shift->end_time);
            $shiftDate = $now->copy()->subDay()->startOfDay();

            // Also check: started today, ends tomorrow
            $tomorrowCandidate = Carbon::parse(
                $now->copy()->addDay()->format('Y-m-d') . ' ' . $shift->end_time
            );

            // Pick whichever candidate is closest to "10 minutes ago"
            $target = $now->copy()->subMinutes(self::TRIGGER_AFTER_MIN);
            if (abs($candidate->diffInMinutes($target, true)) <=
                abs($tomorrowCandidate->diffInMinutes($target, true))) {
                return [$shiftDate, $candidate];
            }

            return [$now->copy()->startOfDay(), $tomorrowCandidate];
        }

        // Normal shift: try today first, then yesterday
        $todayEnd = Carbon::parse($now->format('Y-m-d') . ' ' . $shift->end_time);

        if ($todayEnd->lte($now)) {
            // Shift end already passed today → use today
            return [$now->copy()->startOfDay(), $todayEnd];
        }

        // Shift end is in the future today → try yesterday
        $yesterdayEnd = $todayEnd->copy()->subDay();
        return [$now->copy()->subDay()->startOfDay(), $yesterdayEnd];
    }
}
