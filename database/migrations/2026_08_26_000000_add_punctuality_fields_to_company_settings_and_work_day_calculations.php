<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_arrival_tolerance_minutes')->default(0)->after('work_days_auto_refresh_time');
            $table->unsignedSmallInteger('early_departure_tolerance_minutes')->default(0)->after('late_arrival_tolerance_minutes');
        });

        Schema::table('work_day_calculations', function (Blueprint $table) {
            $table->unsignedInteger('late_arrival_minutes')->default(0)->after('paid_break_minutes');
            $table->unsignedInteger('early_departure_minutes')->default(0)->after('late_arrival_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('work_day_calculations', function (Blueprint $table) {
            $table->dropColumn(['late_arrival_minutes', 'early_departure_minutes']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['late_arrival_tolerance_minutes', 'early_departure_tolerance_minutes']);
        });
    }
};