<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropUnique('company_settings_kiosk_key_hash_unique');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['kiosk_key_hash', 'require_authorized_kiosk_devices']);
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('kiosk_key_hash', 64)->nullable()->unique()->after('require_pin_for_kiosk');
            $table->boolean('require_authorized_kiosk_devices')->default(false)->after('kiosk_key_hash');
        });
    }
};
