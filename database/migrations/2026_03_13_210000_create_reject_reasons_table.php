<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reject_reasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factory_id')->nullable()->index();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('category', 30)->default('other');
            // categories: cosmetic, dimensional, functional, material, assembly, other
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reject_reasons');
    }
};
