<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status')->default('invalid');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('employment_relationship_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->date('work_date')->nullable();
            $table->foreignId('existing_daily_schedule_assignment_id')->nullable()->constrained('daily_schedule_assignments')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('applied_daily_schedule_assignment_id')->nullable()->constrained('daily_schedule_assignments')->cascadeOnUpdate()->nullOnDelete();
            $table->char('row_fingerprint', 64)->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_number'], 'import_rows_batch_row_unique');
            $table->index(['company_id', 'import_batch_id', 'status'], 'import_rows_company_batch_status_idx');
            $table->index(['company_id', 'employment_relationship_id', 'work_date'], 'import_rows_company_relationship_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
