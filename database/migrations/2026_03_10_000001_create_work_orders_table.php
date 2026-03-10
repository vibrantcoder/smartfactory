<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_number', 20);
            $table->foreignId('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('part_id')->constrained('parts');
            $table->unsignedInteger('order_qty');
            $table->unsignedInteger('excess_qty')->default(0);
            $table->unsignedInteger('total_planned_qty')->storedAs('order_qty + excess_qty');
            $table->date('expected_delivery_date');
            $table->date('planned_start_date')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['draft', 'confirmed', 'released', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Unique WO number per factory
            $table->unique(['factory_id', 'wo_number'], 'uq_work_orders_factory_number');
            // Frequent query patterns
            $table->index(['factory_id', 'status'], 'idx_wo_factory_status');
            $table->index(['factory_id', 'expected_delivery_date'], 'idx_wo_factory_delivery');
            $table->index(['factory_id', 'customer_id'], 'idx_wo_factory_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
