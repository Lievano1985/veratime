<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_events', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(EmploymentRelationship::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(Center::class)->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('event_type');
            $table->dateTime('occurred_at_utc');
            $table->date('occurred_local_date');
            $table->time('occurred_local_time');
            $table->string('timezone');
            $table->dateTime('received_at');
            $table->string('source');
            $table->foreignId('source_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('external_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('status')->default('valid');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index(['company_id', 'worker_id', 'occurred_at_utc'], 'time_events_company_worker_utc_idx');
            $table->index(['company_id', 'worker_id', 'occurred_local_date'], 'time_events_company_worker_date_idx');
            $table->index(['company_id', 'center_id', 'occurred_local_date'], 'time_events_company_center_date_idx');
            $table->index(['company_id', 'status'], 'time_events_company_status_idx');
            $table->unique(['company_id', 'source', 'external_id'], 'time_events_company_source_external_unique');
            $table->unique(['company_id', 'idempotency_key'], 'time_events_company_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_events');
    }
};