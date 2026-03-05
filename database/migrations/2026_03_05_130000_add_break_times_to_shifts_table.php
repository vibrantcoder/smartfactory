<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Named break window; break_min is derived: break_end - break_start
            $table->time('break_start')->nullable()->after('break_min')
                  ->comment('Break start time (HH:MM:SS), null = no break');
            $table->time('break_end')->nullable()->after('break_start')
                  ->comment('Break end time (HH:MM:SS), must be after break_start');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['break_start', 'break_end']);
        });
    }
};
