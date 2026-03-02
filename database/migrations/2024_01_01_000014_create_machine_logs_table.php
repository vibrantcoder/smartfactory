<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);          // running, idle, down
            $table->decimal('oee_pct', 5, 2)->nullable();
            $table->decimal('availability_pct', 5, 2)->nullable();
            $table->decimal('performance_pct', 5, 2)->nullable();
            $table->decimal('quality_pct', 5, 2)->nullable();
            $table->unsignedInteger('runtime_minutes')->default(0);
            $table->unsignedInteger('downtime_minutes')->default(0);
            $table->unsignedInteger('parts_produced')->default(0);
            $table->unsignedInteger('parts_defect')->default(0);
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['factory_id', 'machine_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_logs');
    }
};
