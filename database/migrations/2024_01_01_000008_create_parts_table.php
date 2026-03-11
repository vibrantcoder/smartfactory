<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('part_number', 50)->unique();
            $table->string('name');
            $table->decimal('cycle_time_std', 10, 4)->nullable();   // seconds (for OEE)
            $table->decimal('total_cycle_time', 10, 4)->default(0); // minutes (sum of routing)
            $table->string('status', 20)->default('active'); // active|discontinued
            $table->timestamps();
            $table->softDeletes();

            $table->index(['factory_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
