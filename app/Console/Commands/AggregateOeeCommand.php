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
 *   php artisan iot:aggregate-oee                        # all factories, today
 *   php artisan iot:aggregate-oee --date=2026-02-28      # all factories, specific date
 *   php artisan iot:aggregate-oee --factory=1            # one factory, today
 *   php artisan iot:aggregate-oee --factory=1 --date=2026-02-28
 */
class AggregateOeeCommand extends Command
{
    protected $signature = 'iot:aggregate-oee
        {--factory= : Aggregate only this factory ID}
        {--date=    : Date to aggregate (Y-m-d, default: today)}';

    protected $description = 'Aggregate IoT OEE data into the machine_oee_shifts summary table';

    public function handle(OeeAggregationService $service): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $factoryId = $this->option('factory')
            ? (int) $this->option('factory')
            : null;

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
