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
Schedule::command('iot:aggregate-oee')
    ->everyFiveMinutes()
    ->withoutOverlapping()   // skip if a previous run is still going
    ->runInBackground()      // don't block other scheduled tasks
    ->appendOutputTo(storage_path('logs/oee-aggregation.log'));
