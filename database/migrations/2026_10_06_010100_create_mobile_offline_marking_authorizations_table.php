<?php

use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\MobileMarkingPolicy;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_offline_marking_authorizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileMarkingPolicy::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileDeviceBinding::class)->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->unsignedInteger('policy_version');
            $table->unsignedBigInteger('issued_monotonic_milliseconds');
            $table->unsignedInteger('max_clock_drift_seconds')->default(120);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->json('policy_snapshot');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'worker_id', 'expires_at'], 'moma_company_user_worker_expires_index');
            $table->index(['company_id', 'mobile_device_binding_id', 'status'], 'moma_company_binding_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_offline_marking_authorizations');
    }
};
