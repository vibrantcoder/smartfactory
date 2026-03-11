<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup migration — removes dead code from the schema:
 *
 *  1. machine_logs table — orphaned; replaced entirely by iot_logs (raw)
 *     and machine_oee_shifts (aggregated). Never populated or queried.
 *
 *  2. parts.revision column — exists in the original create_parts migration
 *     but is absent from PartData DTO, PartResource, all controllers and views.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop the orphaned machine_logs table
        Schema::dropIfExists('machine_logs');

        // 2. Drop the unused revision column from parts
        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('revision');
        });
    }

    public function down(): void
    {
        // Restore machine_logs
        Schema::create('machine_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id')->nullable()->index();
            $table->unsignedBigInteger('machine_id')->nullable()->index();
            $table->string('status', 20)->nullable();
            $table->decimal('oee_pct', 5, 2)->nullable();
            $table->decimal('availability_pct', 5, 2)->nullable();
            $table->decimal('performance_pct', 5, 2)->nullable();
            $table->decimal('quality_pct', 5, 2)->nullable();
            $table->unsignedSmallInteger('runtime_minutes')->nullable();
            $table->unsignedSmallInteger('downtime_minutes')->nullable();
            $table->unsignedInteger('parts_produced')->nullable();
            $table->unsignedInteger('parts_defect')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->timestamps();
        });

        // Restore revision column on parts
        Schema::table('parts', function (Blueprint $table) {
            $table->string('revision', 10)->nullable()->after('part_number');
        });
    }
};
