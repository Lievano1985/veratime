<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\KioskDevice;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->string('kiosk_enrollment_identifier', 32)->nullable()->unique()->after('require_pin_for_kiosk');
            $table->string('kiosk_enrollment_key_hash')->nullable()->after('kiosk_enrollment_identifier');
        });

        Schema::create('kiosk_terminal_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('request_secret_hash', 64)->unique();
            $table->string('requested_name', 120);
            $table->string('status')->default('pending');
            $table->foreignIdFor(Center::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->string('requested_ip', 45)->nullable();
            $table->string('requested_user_agent', 1000)->nullable();
            $table->foreignIdFor(User::class, 'reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignIdFor(KioskDevice::class)->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'expires_at']);
        });

        // Pending records from the retired QR/code flow never represented an
        // authorized terminal. Invalidate them without affecting active devices.
        DB::table('kiosk_devices')
            ->where('status', 'pending')
            ->update([
                'status' => 'revoked',
                'pairing_code_hash' => null,
                'pairing_expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_terminal_access_requests');

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropUnique('company_settings_kiosk_enrollment_identifier_unique');
            $table->dropColumn(['kiosk_enrollment_identifier', 'kiosk_enrollment_key_hash']);
        });
    }
};
