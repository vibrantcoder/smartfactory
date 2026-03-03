<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── IoT OEE Aggregation ───────────────────────────────────────────────────────
//
// Aggregates raw iot_logs into the machine_oee_shifts summary table.
// Runs every 5 minutes so the dashboard always shows fresh OEE without
// scanning millions of raw rows per request.
//
// On XAMPP (Windows), the scheduler is driven by a Windows Task Scheduler
// entry running:  php artisan schedule:run
// every minute, OR you can run  php artisan schedule:work  in a terminal.
//
// Aggregate raw iot_logs → machine_oee_shifts every 5 minutes
Schedule::command('iot:aggregate-oee')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/oee-aggregation.log'));

// ── IoT Log Purge ─────────────────────────────────────────────────────────────
//
// Runs daily at 00:30 (after midnight) to keep iot_logs lean.
//   Phase 1: re-aggregates all dates being purged → history saved in machine_oee_shifts
//   Phase 2: deletes rows older than 1 day in 10 000-row chunks
//
// Historical data lives permanently in machine_oee_shifts (OEE per machine/shift/day).
// Increase --days=N to retain more raw data (at the cost of disk space).
//
Schedule::command('iot:purge-logs --days=1')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/iot-purge.log'));
