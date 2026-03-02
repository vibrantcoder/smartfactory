<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downtime_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('category', 30)->default('unplanned'); // planned, unplanned, maintenance
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('factory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downtime_reasons');
    }
};
