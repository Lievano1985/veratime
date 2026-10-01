<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Center::class)->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('pending');
            $table->string('pairing_code_hash', 64)->nullable()->unique();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->string('device_token_hash', 64)->nullable()->unique();
            $table->foreignIdFor(User::class, 'created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignIdFor(User::class, 'revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 45)->nullable();
            $table->string('last_seen_user_agent', 1000)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['center_id', 'status']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('require_authorized_kiosk_devices')->default(false)->after('kiosk_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('require_authorized_kiosk_devices');
        });

        Schema::dropIfExists('kiosk_devices');
    }
};
