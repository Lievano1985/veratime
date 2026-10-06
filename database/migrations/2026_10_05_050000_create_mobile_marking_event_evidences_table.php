<?php

use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\MobileMarkingPolicy;
use App\Models\MobileMarkingTimeReference;
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
        Schema::create('mobile_marking_event_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(TimeEvent::class)->unique()->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileMarkingPolicy::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileDeviceBinding::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileMarkingTimeReference::class)->constrained()->restrictOnDelete();
            $table->uuid('policy_public_id');
            $table->unsignedInteger('policy_version');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->text('signature')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->json('policy_snapshot');
            $table->decimal('location_latitude', 10, 7)->nullable();
            $table->decimal('location_longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy_meters', 10, 2)->nullable();
            $table->timestamp('location_captured_at')->nullable();
            $table->boolean('location_is_mocked')->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'worker_id', 'policy_version'], 'mmee_company_worker_policy_index');
            $table->index(['company_id', 'mobile_device_binding_id'], 'mmee_company_binding_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_marking_event_evidences');
    }
};
