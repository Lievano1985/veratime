<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->time('work_days_auto_refresh_time')->nullable()->after('default_closure_day');
            $table->dateTime('work_days_last_refreshed_at')->nullable()->after('work_days_auto_refresh_time');
            $table->string('work_days_last_refresh_status')->nullable()->after('work_days_last_refreshed_at');
            $table->json('work_days_last_refresh_summary')->nullable()->after('work_days_last_refresh_status');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'work_days_auto_refresh_time',
                'work_days_last_refreshed_at',
                'work_days_last_refresh_status',
                'work_days_last_refresh_summary',
            ]);
        });
    }
};
