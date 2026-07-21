<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('import_type');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('status')->default('uploaded');
            $table->string('existing_assignment_policy')->default('replace_existing');
            $table->string('original_filename');
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->char('file_sha256', 64);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('encoding')->default('UTF-8');
            $table->string('delimiter', 8)->default(',');
            $table->unsignedSmallInteger('header_schema_version')->default(1);
            $table->char('validation_sha256', 64)->nullable();
            $table->string('idempotency_key')->nullable();
            $table->text('reason');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->unsignedInteger('applied_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'import_type', 'status'], 'import_batches_company_import_status_idx');
            $table->index(['company_id', 'target_type', 'target_id'], 'import_batches_company_target_idx');
            $table->index(['company_id', 'file_sha256'], 'import_batches_company_file_hash_idx');
            $table->unique(['company_id', 'import_type', 'idempotency_key'], 'import_batches_company_import_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
