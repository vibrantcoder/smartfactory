<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * machine_oee_shifts
 *
 * Pre-aggregated OEE per machine × shift × date.
 *
 * Purpose: Avoid scanning millions of raw iot_logs rows for every dashboard request.
 * Populated by the OeeAggregationService (run every 5 minutes by the scheduler).
 *
 * One row = one machine's performance during one shift on one calendar date.
 * The UNIQUE constraint (machine_id, shift_id, oee_date) ensures upserts are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_oee_shifts', function (Blueprint $table) {
            $table->id();

            // ── Foreign keys ─────────────────────────────────────────
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();

            // ── Date (calendar date of shift start) ──────────────────
            $table->date('oee_date');

            // ── Production quantities ─────────────────────────────────
            $table->unsignedInteger('planned_qty')->default(0);    // from production_plans (0 if no plan)
            $table->unsignedInteger('total_parts')->default(0);    // SUM(part_count) from iot_logs
            $table->unsignedInteger('good_parts')->default(0);     // total_parts - reject_parts
            $table->unsignedInteger('reject_parts')->default(0);   // SUM(part_reject) from iot_logs

            // ── Time components (minutes) ─────────────────────────────
            $table->unsignedSmallInteger('planned_minutes')->default(0);
            $table->unsignedSmallInteger('alarm_minutes')->default(0);
            $table->unsignedSmallInteger('available_minutes')->default(0);

            // ── OEE percentages (null = no production plan / no cycle_time_std) ──
            $table->decimal('availability_pct', 5, 2)->default(0.00);
            $table->decimal('performance_pct',  5, 2)->nullable();
            $table->decimal('quality_pct',      5, 2)->default(100.00);
            $table->decimal('oee_pct',          5, 2)->nullable();
            $table->decimal('attainment_pct',   5, 2)->nullable();

            // ── Raw log metadata ─────────────────────────────────────
            $table->unsignedInteger('log_count')->default(0);
            $table->unsignedSmallInteger('log_interval_seconds')->default(5);

            // ── Timestamps ───────────────────────────────────────────
            $table->timestamp('calculated_at')->nullable()->comment('Last time this row was aggregated');
            $table->timestamps();

            // ── Constraints & indexes ────────────────────────────────
            $table->unique(['machine_id', 'shift_id', 'oee_date'], 'uq_machine_shift_date');
            $table->index(['factory_id', 'oee_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_oee_shifts');
    }
};
