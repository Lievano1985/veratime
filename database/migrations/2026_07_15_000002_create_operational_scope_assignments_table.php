<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_scope_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Center::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(OrganizationalUnit::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('responsibility_type');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->string('source')->default('manual');
            $table->text('reason')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('operational_scope_assignments')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'status']);
            $table->index(['company_id', 'center_id', 'status']);
            $table->index(['company_id', 'organizational_unit_id', 'status'], 'osa_company_unit_status_idx');
            $table->index(['company_id', 'effective_from', 'effective_to'], 'osa_company_effective_dates_idx');
            $table->index(['company_id', 'responsibility_type', 'status'], 'osa_company_responsibility_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_scope_assignments');
    }
};