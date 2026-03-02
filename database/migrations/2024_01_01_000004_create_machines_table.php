<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('type', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->string('device_token', 64)->nullable()->unique();
            $table->string('status', 20)->default('active'); // active, maintenance, retired
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['factory_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
