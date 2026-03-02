<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factory_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->decimal('oee_target_pct', 5, 2)->default(85.00);
            $table->decimal('availability_target_pct', 5, 2)->default(90.00);
            $table->decimal('performance_target_pct', 5, 2)->default(95.00);
            $table->decimal('quality_target_pct', 5, 2)->default(99.00);
            $table->decimal('working_hours_per_day', 4, 2)->default(8.00);
            $table->integer('log_interval_seconds')->default(5);
            $table->integer('downtime_threshold_min')->default(5);
            $table->integer('aggregation_lag_min')->default(10);
            $table->integer('raw_log_retention_days')->default(90);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_settings');
    }
};
