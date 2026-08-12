<?php

use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_period_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(AttendancePeriod::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(OrganizationalUnit::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['attendance_period_id', 'organizational_unit_id'], 'attendance_period_scopes_period_unit_unique');
            $table->index(['company_id', 'organizational_unit_id'], 'attendance_period_scopes_company_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_period_scopes');
    }
};
