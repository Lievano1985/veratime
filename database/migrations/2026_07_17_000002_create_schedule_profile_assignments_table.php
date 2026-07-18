<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleProfile;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_profile_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(ScheduleProfile::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('assignment_scope');
            $table->foreignIdFor(Center::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(OrganizationalUnit::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(EmploymentRelationship::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->string('source')->default('manual');
            $table->text('reason')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('schedule_profile_assignments')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'schedule_profile_id'], 'schedule_profile_assignments_company_profile_idx');
            $table->index(['company_id', 'assignment_scope'], 'schedule_profile_assignments_company_scope_idx');
            $table->index(['company_id', 'center_id'], 'schedule_profile_assignments_company_center_idx');
            $table->index(['company_id', 'organizational_unit_id'], 'schedule_profile_assignments_company_unit_idx');
            $table->index(['company_id', 'employment_relationship_id'], 'schedule_profile_assignments_company_relationship_idx');
            $table->index(['company_id', 'effective_from', 'effective_to'], 'schedule_profile_assignments_company_dates_idx');
            $table->index(['company_id', 'status'], 'schedule_profile_assignments_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_profile_assignments');
    }
};
