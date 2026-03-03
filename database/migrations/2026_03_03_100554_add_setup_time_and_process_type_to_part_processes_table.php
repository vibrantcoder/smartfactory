<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_processes', function (Blueprint $table) {
            $table->decimal('setup_time', 8, 2)->nullable()->after('standard_cycle_time')
                  ->comment('Setup / changeover time in minutes');
            $table->enum('process_type', ['inhouse', 'outside'])->default('inhouse')->after('setup_time')
                  ->comment('Whether the process is performed in-house or outsourced');
        });
    }

    public function down(): void
    {
        Schema::table('part_processes', function (Blueprint $table) {
            $table->dropColumn(['setup_time', 'process_type']);
        });
    }
};
