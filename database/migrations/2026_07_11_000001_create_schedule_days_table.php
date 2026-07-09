<?php

use App\Models\Company;
use App\Models\Schedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Schedule::class)->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_working_day')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('crosses_midnight')->default(false);
            $table->timestamps();

            $table->unique(['schedule_id', 'day_of_week']);
            $table->index(['company_id', 'schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_days');
    }
};
