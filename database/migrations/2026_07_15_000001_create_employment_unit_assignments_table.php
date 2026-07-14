<?php

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_unit_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(EmploymentRelationship::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(OrganizationalUnit::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('assignment_type');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->string('source')->default('manual');
            $table->text('reason')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('employment_unit_assignments')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'employment_relationship_id', 'assignment_type', 'status'], 'eua_company_relationship_type_status_idx');
            $table->index(['company_id', 'organizational_unit_id', 'status'], 'eua_company_unit_status_idx');
            $table->index(['company_id', 'effective_from', 'effective_to'], 'eua_company_effective_dates_idx');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_unit_assignments');
    }
};