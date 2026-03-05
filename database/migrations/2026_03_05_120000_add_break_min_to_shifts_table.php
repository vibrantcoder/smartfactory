<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Break time excluded from OEE planned operating time.
            // planned_min = duration_min - break_min
            $table->unsignedSmallInteger('break_min')->default(0)->after('duration_min')
                  ->comment('Break/lunch minutes excluded from OEE planned time');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('break_min');
        });
    }
};
