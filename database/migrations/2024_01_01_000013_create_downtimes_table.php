<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            $table->foreignId('downtime_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable(); // calculated on close
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['factory_id', 'machine_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downtimes');
    }
};
