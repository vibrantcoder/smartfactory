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
        Schema::table('process_masters', function (Blueprint $table) {
            $table->enum('process_type', ['inhouse', 'outside'])->default('inhouse')->after('machine_type_default');
        });
    }

    public function down(): void
    {
        Schema::table('process_masters', function (Blueprint $table) {
            $table->dropColumn('process_type');
        });
    }
};
