<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_device_binding_authorizations', fn (Blueprint $table) => $table->text('challenge_encrypted')->nullable()->after('challenge_hash'));
    }

    public function down(): void
    {
        Schema::table('mobile_device_binding_authorizations', fn (Blueprint $table) => $table->dropColumn('challenge_encrypted'));
    }
};
