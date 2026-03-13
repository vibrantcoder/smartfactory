<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds chart_data JSON column to machine_oee_shifts.
 *
 * Stores the full hourly chart snapshot (parts/hour, rejects/hour,
 * spindle utilisation, time stats) at the time of final OEE aggregation.
 * When raw iot_logs are purged, the dashboard reads this column for
 * historical shift views instead of re-scanning deleted rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_oee_shifts', function (Blueprint $table) {
            $table->json('chart_data')
                ->nullable()
                ->after('log_interval_seconds')
                ->comment('Hourly chart snapshot stored after shift ends; served for past-date queries once logs are purged');
        });
    }

    public function down(): void
    {
        Schema::table('machine_oee_shifts', function (Blueprint $table) {
            $table->dropColumn('chart_data');
        });
    }
};
