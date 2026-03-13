<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('process_masters', function (Blueprint $table) {
            $table->dropColumn('standard_time');
        });
    }
    public function down(): void {
        Schema::table('process_masters', function (Blueprint $table) {
            $table->decimal('standard_time', 8, 4)->default(0)->after('process_type');
        });
    }
};
