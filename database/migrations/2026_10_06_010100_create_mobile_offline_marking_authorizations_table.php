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
            $table->foreignIdFor(Company::class);
            $table->foreignIdFor(User::class);
            $table->foreignIdFor(Worker::class);
            $table->foreignIdFor(MobileMarkingPolicy::class);
            $table->foreignIdFor(MobileDeviceBinding::class);
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
            $table->foreign('company_id', 'moma_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('user_id', 'moma_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('worker_id', 'moma_worker_fk')->references('id')->on('workers')->restrictOnDelete();
            $table->foreign('mobile_marking_policy_id', 'moma_policy_fk')->references('id')->on('mobile_marking_policies')->restrictOnDelete();
            $table->foreign('mobile_device_binding_id', 'moma_binding_fk')->references('id')->on('mobile_device_bindings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_offline_marking_authorizations');
    }
};
