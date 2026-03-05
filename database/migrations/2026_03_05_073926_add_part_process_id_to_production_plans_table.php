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
        Schema::table('production_plans', function (Blueprint $table) {
            $table->foreignId('part_process_id')
                  ->nullable()
                  ->after('part_id')
                  ->constrained('part_processes')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_plans', function (Blueprint $table) {
            $table->dropForeign(['part_process_id']);
            $table->dropColumn('part_process_id');
        });
    }
};
