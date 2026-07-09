<?php

use App\Models\Company;
use App\Models\ScheduleDay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ScheduleDay::class)->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'schedule_day_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_breaks');
    }
};
