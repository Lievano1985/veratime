<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_marking_policies', function (Blueprint $table) {
            $table->unsignedInteger('offline_authorization_duration_minutes')
                ->nullable()
                ->after('max_location_age_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_marking_policies', function (Blueprint $table) {
            $table->dropColumn('offline_authorization_duration_minutes');
        });
    }
};
