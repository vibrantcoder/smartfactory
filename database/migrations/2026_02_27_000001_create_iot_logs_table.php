<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('alarm_code')->default(0);
            $table->tinyInteger('auto_mode')->default(0);    // 0=manual, 1=auto
            $table->tinyInteger('cycle_state')->default(0);  // 0=idle, 1=running
            $table->unsignedInteger('part_count')->default(0);   // cumulative counter from device
            $table->unsignedInteger('part_reject')->default(0);  // cumulative reject counter
            $table->string('slave_id', 50)->nullable();
            $table->string('slave_name', 100)->nullable();
            $table->timestamp('logged_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['machine_id', 'logged_at']);
            $table->index(['factory_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_logs');
    }
};
