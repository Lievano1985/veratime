<?php

use App\Models\Company;
use App\Models\ShiftTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_template_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(ShiftTemplate::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('segment_type');
            $table->string('timing_mode');
            $table->time('start_local_time')->nullable();
            $table->time('end_local_time')->nullable();
            $table->unsignedTinyInteger('start_day_offset')->default(0);
            $table->unsignedTinyInteger('end_day_offset')->default(0);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['shift_template_id', 'sort_order'], 'shift_template_segments_sort_unique');
            $table->index(['company_id', 'shift_template_id'], 'shift_template_segments_company_template_idx');
            $table->index(['company_id', 'segment_type'], 'shift_template_segments_company_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_template_segments');
    }
};
