<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('part_drawings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')
                  ->constrained('parts')
                  ->cascadeOnDelete();
            $table->string('original_name');      // original filename from user
            $table->string('stored_name');        // UUID-based filename on disk
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');      // file size in bytes
            $table->timestamps();

            $table->index('part_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('part_drawings');
    }
};
