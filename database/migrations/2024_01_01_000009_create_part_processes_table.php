<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_master_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('sequence_order')->default(1);
            $table->string('machine_type_required', 50)->nullable();
            $table->decimal('standard_cycle_time', 8, 4)->nullable(); // null = use process master default
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['part_id', 'sequence_order']);
            $table->index('part_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_processes');
    }
};
