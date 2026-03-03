<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Analytics\Services\OeeAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * iot:purge-logs
 *
 * Keeps iot_logs lean by deleting raw telemetry older than N days.
 *
 * SAFE WORKFLOW — runs in two phases:
 *   Phase 1: Re-aggregate OEE for every date that is about to be purged.
 *            This guarantees machine_oee_shifts has a complete summary of
 *            the data before it is deleted from iot_logs.
 *   Phase 2: Delete iot_logs rows older than the retention cutoff.
 *
 * History is permanently preserved in machine_oee_shifts (one row per
 * machine × shift × date), which is queryable for trend analysis.
 *
 * Scheduled: daily at 00:30 (after midnight, after the last nightly OEE run).
 *
 * Usage:
 *   php artisan iot:purge-logs                # default: keep 1 day
 *   php artisan iot:purge-logs --days=3       # keep 3 days
 *   php artisan iot:purge-logs --dry-run      # show counts without deleting
 *   php artisan iot:purge-logs --skip-agg     # skip re-aggregation (dangerous)
 */
class PurgeIotLogsCommand extends Command
{
    protected $signature = 'iot:purge-logs
        {--days=1      : Keep this many days of raw logs (default: 1)}
        {--dry-run     : Report what would be deleted without actually deleting}
        {--skip-agg    : Skip OEE re-aggregation before purge (not recommended)}';

    protected $description = 'Purge old iot_logs rows (keeps last N days); history stays in machine_oee_shifts';

    public function __construct(
        private readonly OeeAggregationService $aggregationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $retainDays = max(1, (int) $this->option('days'));
        $dryRun     = (bool) $this->option('dry-run');
        $skipAgg    = (bool) $this->option('skip-agg');

        // Cutoff: any record with logged_at < cutoff will be deleted
        $cutoff = Carbon::now()->subDays($retainDays)->startOfDay();

        $this->info('IoT Log Purge' . ($dryRun ? ' [DRY RUN]' : ''));
        $this->line(sprintf('  Retain  : last %d day(s)', $retainDays));
        $this->line(sprintf('  Cutoff  : %s', $cutoff->toDateTimeString()));

        // ── Count rows that will be purged ───────────────────────────────────
        $affectedCount = DB::table('iot_logs')
            ->where('logged_at', '<', $cutoff)
            ->count();

        $this->line(sprintf('  Rows to purge: %s', number_format($affectedCount)));

        if ($affectedCount === 0) {
            $this->info('Nothing to purge.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No rows deleted. Remove --dry-run to purge.');
            return self::SUCCESS;
        }

        // ── Phase 1: Re-aggregate every date being purged ───────────────────
        //
        // Find all unique dates in the rows we are about to delete and
        // run OEE aggregation for each one so machine_oee_shifts is complete
        // before any raw data disappears.
        //
        if (! $skipAgg) {
            $this->info('Phase 1: Re-aggregating OEE for dates being purged...');

            $dates = DB::table('iot_logs')
                ->select(DB::raw("DATE(logged_at) as d"))
                ->where('logged_at', '<', $cutoff)
                ->groupBy('d')
                ->orderBy('d')
                ->pluck('d');

            foreach ($dates as $date) {
                $this->line("  → Aggregating {$date}...");
                try {
                    $this->aggregationService->aggregateAll(
                        Carbon::parse($date)
                    );
                } catch (\Throwable $e) {
                    $this->warn("    ✗ Failed for {$date}: {$e->getMessage()}");
                    Log::warning("iot:purge-logs aggregation failed for {$date}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->line('  ✓ Aggregation complete.');
        } else {
            $this->warn('Phase 1: Skipped (--skip-agg). Ensure OEE data is already aggregated!');
        }

        // ── Phase 2: Delete old rows in chunks to avoid long table locks ────
        $this->info('Phase 2: Deleting old iot_logs rows...');

        $totalDeleted = 0;
        $chunkSize    = 10_000;

        do {
            $deleted = DB::table('iot_logs')
                ->where('logged_at', '<', $cutoff)
                ->limit($chunkSize)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $this->line(sprintf(
                    '  Deleted chunk: %s rows  (total: %s)',
                    number_format($deleted),
                    number_format($totalDeleted)
                ));
            }
        } while ($deleted === $chunkSize);

        // ── Summary ──────────────────────────────────────────────────────────
        $remaining = DB::table('iot_logs')->count();

        $this->newLine();
        $this->info(sprintf('Done. %s rows purged. %s rows remain in iot_logs.',
            number_format($totalDeleted),
            number_format($remaining)
        ));

        Log::info('iot:purge-logs completed', [
            'retain_days'   => $retainDays,
            'cutoff'        => $cutoff->toDateTimeString(),
            'rows_deleted'  => $totalDeleted,
            'rows_remaining'=> $remaining,
        ]);

        return self::SUCCESS;
    }
}
