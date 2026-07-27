<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Center::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('version')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('previous_batch_id')->nullable()->constrained('schedule_batches')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('creation_source')->default('manual');
            $table->text('notes')->nullable();
            $table->string('snapshot_schema_version')->nullable();
            $table->longText('snapshot_canonical_json')->nullable();
            $table->char('snapshot_sha256', 64)->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(User::class, 'published_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('schedule_batches')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'center_id', 'period_start', 'period_end', 'version'], 'schedule_batches_period_version_unique');
            $table->index(['company_id', 'center_id'], 'schedule_batches_company_center_idx');
            $table->index(['company_id', 'center_id', 'status', 'period_start', 'period_end'], 'schedule_batches_status_period_idx');
            $table->index(['company_id', 'previous_batch_id'], 'schedule_batches_company_previous_idx');
            $table->index(['company_id', 'snapshot_sha256'], 'schedule_batches_company_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_batches');
    }
};
