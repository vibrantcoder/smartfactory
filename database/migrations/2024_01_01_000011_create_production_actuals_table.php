<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_actuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('actual_qty')->default(0);
            $table->unsignedInteger('defect_qty')->default(0);
            $table->unsignedInteger('good_qty')->virtualAs('actual_qty - defect_qty');
            $table->timestamp('recorded_at');
            $table->string('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['production_plan_id']);
            $table->index(['factory_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_actuals');
    }
};
