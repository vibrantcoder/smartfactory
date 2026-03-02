<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->date('planned_date');
            $table->unsignedInteger('planned_qty');
            $table->string('status', 20)->default('draft'); // draft, scheduled, in_progress, completed, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['factory_id', 'planned_date', 'status']);
            $table->index(['machine_id', 'planned_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plans');
    }
};
