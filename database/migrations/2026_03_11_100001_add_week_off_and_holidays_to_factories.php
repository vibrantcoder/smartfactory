<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            // JSON array of ISO weekday numbers: 0=Sunday, 1=Monday, …, 6=Saturday
            $table->json('week_off_days')->nullable()->after('status');
        });

        Schema::create('factory_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name'); // e.g. "Republic Day"
            $table->timestamps();
            $table->unique(['factory_id', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_holidays');
        Schema::table('factories', function (Blueprint $table) {
            $table->dropColumn('week_off_days');
        });
    }
};
