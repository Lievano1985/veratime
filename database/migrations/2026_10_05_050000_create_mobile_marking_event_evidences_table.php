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
            $table->foreignIdFor(Company::class);
            $table->foreignIdFor(User::class);
            $table->foreignIdFor(Worker::class);
            $table->foreignIdFor(TimeEvent::class)->unique('mmee_time_event_unique');
            $table->foreignIdFor(MobileMarkingPolicy::class);
            $table->foreignIdFor(MobileDeviceBinding::class)->nullable();
            $table->foreignIdFor(MobileMarkingTimeReference::class);
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
            $table->foreign('company_id', 'mmee_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('user_id', 'mmee_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('worker_id', 'mmee_worker_fk')->references('id')->on('workers')->restrictOnDelete();
            $table->foreign('time_event_id', 'mmee_time_event_fk')->references('id')->on('time_events')->restrictOnDelete();
            $table->foreign('mobile_marking_policy_id', 'mmee_policy_fk')->references('id')->on('mobile_marking_policies')->restrictOnDelete();
            $table->foreign('mobile_device_binding_id', 'mmee_binding_fk')->references('id')->on('mobile_device_bindings')->restrictOnDelete();
            $table->foreign('mobile_marking_time_reference_id', 'mmee_reference_fk')->references('id')->on('mobile_marking_time_references')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_marking_event_evidences');
    }
};
