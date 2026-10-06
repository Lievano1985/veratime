<?php

use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\MobileMarkingPolicy;
use App\Models\MobileOfflineMarkingAuthorization;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_offline_marking_captures', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(TimeEvent::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileOfflineMarkingAuthorization::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileMarkingPolicy::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileDeviceBinding::class)->nullable()->constrained()->restrictOnDelete();
            $table->string('client_event_id');
            $table->string('event_type');
            $table->string('timezone')->nullable();
            $table->uuid('offline_authorization_public_id')->nullable();
            $table->uuid('binding_public_id')->nullable();
            $table->uuid('policy_public_id')->nullable();
            $table->unsignedInteger('policy_version')->nullable();
            $table->timestamp('occurred_at_claimed')->nullable();
            $table->string('occurred_at_raw');
            $table->unsignedBigInteger('monotonic_elapsed_milliseconds')->nullable();
            $table->timestamp('estimated_occurred_at')->nullable();
            $table->string('status')->default('pending_review');
            $table->string('review_reason')->nullable();
            $table->string('signature', 1024)->nullable();
            $table->string('payload_hash', 64);
            $table->json('policy_snapshot')->nullable();
            $table->decimal('location_latitude', 10, 7)->nullable();
            $table->decimal('location_longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy_meters', 10, 2)->nullable();
            $table->timestamp('location_captured_at')->nullable();
            $table->string('location_captured_at_raw')->nullable();
            $table->boolean('location_is_mocked')->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'client_event_id'], 'momc_company_client_event_unique');
            $table->index(['company_id', 'worker_id', 'status'], 'momc_company_worker_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_offline_marking_captures');
    }
};
