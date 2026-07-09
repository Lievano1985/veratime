<?php

use App\Models\Company;
use App\Models\EmploymentRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labor_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(EmploymentRelationship::class)->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable();
            $table->string('work_modality');
            $table->decimal('weekly_hours', 5, 2)->nullable();
            $table->unsignedTinyInteger('rest_day_of_week')->nullable();
            $table->string('policy_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'employment_relationship_id', 'effective_from', 'effective_to'], 'lc_company_rel_dates_idx');
            $table->index(['company_id', 'schedule_id'], 'lc_company_schedule_idx');
            $table->index(['company_id', 'status'], 'lc_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labor_conditions');
    }
};
