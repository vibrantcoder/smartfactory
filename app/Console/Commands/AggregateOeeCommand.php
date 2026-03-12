<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Analytics\Services\OeeAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * iot:aggregate-oee
 *
 * Reads raw iot_logs and upserts pre-calculated OEE rows into
 * machine_oee_shifts for fast dashboard reads.
 *
 * Usage:
 *   php artisan iot:aggregate-oee                         # all factories, today
 *   php artisan iot:aggregate-oee --date=2026-02-28       # all factories, specific date
 *   php artisan iot:aggregate-oee --factory=1             # one factory, today
 *   php artisan iot:aggregate-oee --factory=1 --date=2026-02-28
 *   php artisan iot:aggregate-oee --backfill=30           # last 30 days, all factories
 *   php artisan iot:aggregate-oee --factory=1 --backfill=30
 */
class AggregateOeeCommand extends Command
{
    protected $signature = 'iot:aggregate-oee
        {--factory=  : Aggregate only this factory ID}
        {--date=     : Date to aggregate (Y-m-d, default: today)}
        {--backfill= : Backfill the last N days (overrides --date)}';

    protected $description = 'Aggregate IoT OEE data into the machine_oee_shifts summary table';

    public function handle(OeeAggregationService $service): int
    {
        $factoryId = $this->option('factory')
            ? (int) $this->option('factory')
            : null;

        // Backfill mode: iterate over the last N days
        if ($this->option('backfill') !== null) {
            $days  = max(1, (int) $this->option('backfill'));
            $total = 0;

            $this->info("Backfilling OEE for " . ($factoryId ? "factory #{$factoryId}" : 'all factories')
                . " — last {$days} days …");

            $bar = $this->output->createProgressBar($days);
            $bar->start();

            for ($i = $days - 1; $i >= 0; $i--) {
                $date  = Carbon::today()->subDays($i);
                $rows  = $factoryId
                    ? $service->aggregateFactory($factoryId, $date)
                    : $service->aggregateAll($date);
                $total += $rows;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Done. Wrote/updated {$total} rows across {$days} days.");

            return Command::SUCCESS;
        }

        // Single-date mode
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $this->info("Aggregating OEE for " . ($factoryId ? "factory #{$factoryId}" : 'all factories')
            . " on {$date->format('Y-m-d')} …");

        $start = microtime(true);

        $rows = $factoryId
            ? $service->aggregateFactory($factoryId, $date)
            : $service->aggregateAll($date);

        $elapsed = round(microtime(true) - $start, 2);

        $this->info("Done. Wrote/updated {$rows} rows in {$elapsed}s.");

        return Command::SUCCESS;
    }
}
