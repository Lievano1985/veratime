<?php

use App\Models\Company;
use App\Models\MobileDeviceBindingAuthorization;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_device_bindings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileDeviceBindingAuthorization::class)->unique('mdb_binding_authorization_unique')->constrained()->restrictOnDelete();
            $table->string('device_name', 120);
            $table->string('algorithm', 20);
            $table->text('public_key_spki');
            $table->string('key_fingerprint', 64);
            $table->string('status')->default('active');
            $table->timestamp('activated_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignIdFor(User::class, 'revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'key_fingerprint'], 'mdb_company_key_fingerprint_unique');
            $table->index(['company_id', 'user_id', 'worker_id', 'status'], 'mdb_company_identity_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_device_bindings');
    }
};
