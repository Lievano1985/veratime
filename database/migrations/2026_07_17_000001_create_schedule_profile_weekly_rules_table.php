<?php

use App\Models\Company;
use App\Models\ScheduleProfile;
use App\Models\ShiftTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_profile_weekly_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(ScheduleProfile::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('day_of_week');
            $table->string('day_type');
            $table->foreignIdFor(ShiftTemplate::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['schedule_profile_id', 'day_of_week'], 'schedule_profile_weekly_day_unique');
            $table->index(['company_id', 'schedule_profile_id'], 'schedule_profile_weekly_company_profile_idx');
            $table->index(['company_id', 'shift_template_id'], 'schedule_profile_weekly_company_template_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_profile_weekly_rules');
    }
};
