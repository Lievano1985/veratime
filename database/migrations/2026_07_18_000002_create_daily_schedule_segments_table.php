<?php

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\ShiftTemplateSegment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_schedule_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(DailyScheduleAssignment::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedSmallInteger('segment_order');
            $table->string('segment_type');
            $table->string('timing_mode');
            $table->time('start_local_time')->nullable();
            $table->time('end_local_time')->nullable();
            $table->unsignedTinyInteger('start_day_offset')->default(0);
            $table->unsignedTinyInteger('end_day_offset')->default(0);
            $table->timestamp('starts_at_utc')->nullable();
            $table->timestamp('ends_at_utc')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->foreignIdFor(ShiftTemplateSegment::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['daily_schedule_assignment_id', 'segment_order'], 'daily_schedule_segments_assignment_order_unique');
            $table->index(['company_id', 'daily_schedule_assignment_id'], 'daily_schedule_segments_company_assignment_idx');
            $table->index(['company_id', 'segment_type'], 'daily_schedule_segments_company_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_schedule_segments');
    }
};
