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
            $table->foreignId('operator_id')
                ->nullable()
                ->after('shift_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_plans', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropColumn('operator_id');
        });
    }
};
