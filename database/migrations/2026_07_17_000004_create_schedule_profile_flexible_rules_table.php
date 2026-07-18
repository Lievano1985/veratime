<?php

use App\Models\Company;
use App\Models\ScheduleProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_profile_flexible_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(ScheduleProfile::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('day_of_week');
            $table->string('day_type');
            $table->unsignedSmallInteger('required_minutes')->nullable();
            $table->time('window_start_local_time')->nullable();
            $table->time('window_end_local_time')->nullable();
            $table->unsignedTinyInteger('window_start_day_offset')->default(0);
            $table->unsignedTinyInteger('window_end_day_offset')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['schedule_profile_id', 'day_of_week'], 'schedule_profile_flexible_day_unique');
            $table->index(['company_id', 'schedule_profile_id'], 'schedule_profile_flexible_company_profile_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_profile_flexible_rules');
    }
};
