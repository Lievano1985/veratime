<?php

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(ScheduleBatch::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(EmploymentRelationship::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(OrganizationalUnit::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('work_date');
            $table->string('day_type');
            $table->string('timezone');
            $table->foreignIdFor(ShiftTemplate::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('source_type')->default('manual');
            $table->json('source_reference')->nullable();
            $table->unsignedSmallInteger('required_minutes')->nullable();
            $table->time('window_start_local_time')->nullable();
            $table->time('window_end_local_time')->nullable();
            $table->unsignedTinyInteger('window_start_day_offset')->default(0);
            $table->unsignedTinyInteger('window_end_day_offset')->default(0);
            $table->time('availability_start_local_time')->nullable();
            $table->time('availability_end_local_time')->nullable();
            $table->unsignedTinyInteger('availability_start_day_offset')->default(0);
            $table->unsignedTinyInteger('availability_end_day_offset')->default(0);
            $table->unsignedSmallInteger('max_work_minutes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['schedule_batch_id', 'employment_relationship_id', 'work_date'], 'daily_schedule_assignments_batch_relationship_date_unique');
            $table->index(['company_id', 'schedule_batch_id'], 'daily_schedule_assignments_company_batch_idx');
            $table->index(['company_id', 'employment_relationship_id', 'work_date'], 'daily_schedule_assignments_company_relationship_date_idx');
            $table->index(['company_id', 'work_date', 'day_type'], 'daily_schedule_assignments_company_date_type_idx');
            $table->index(['company_id', 'organizational_unit_id'], 'daily_schedule_assignments_company_unit_idx');
            $table->index(['company_id', 'shift_template_id'], 'daily_schedule_assignments_company_template_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_schedule_assignments');
    }
};
