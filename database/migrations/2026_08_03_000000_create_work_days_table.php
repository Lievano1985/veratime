<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_days', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(EmploymentRelationship::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Center::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(ScheduleBatch::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(DailyScheduleAssignment::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('work_date');
            $table->string('timezone')->nullable();
            $table->string('status')->default('pending');
            $table->string('schedule_status');
            $table->string('day_type')->nullable();
            $table->unsignedSmallInteger('expected_work_minutes')->nullable();
            $table->unsignedSmallInteger('valid_time_event_count')->default(0);
            $table->dateTime('first_event_at_utc')->nullable();
            $table->dateTime('last_event_at_utc')->nullable();
            $table->json('valid_time_event_ids')->nullable();
            $table->unsignedBigInteger('active_calculation_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'worker_id', 'work_date'], 'work_days_company_worker_date_unique');
            $table->index(['company_id', 'employment_relationship_id', 'work_date'], 'work_days_company_relationship_date_idx');
            $table->index(['company_id', 'work_date'], 'work_days_company_date_idx');
            $table->index(['company_id', 'center_id', 'work_date'], 'work_days_company_center_date_idx');
            $table->index(['company_id', 'status'], 'work_days_company_status_idx');
            $table->index(['company_id', 'schedule_status'], 'work_days_company_schedule_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_days');
    }
};
