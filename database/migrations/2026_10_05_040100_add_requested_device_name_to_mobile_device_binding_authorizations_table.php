<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_device_binding_authorizations', function (Blueprint $table): void {
            $table->string('requested_device_name', 120)->nullable()->after('challenge_hash');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_device_binding_authorizations', function (Blueprint $table): void {
            $table->dropColumn('requested_device_name');
        });
    }
};
