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
        Schema::create('attendance_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Center::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('scope_type');
            $table->string('name')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('timezone');
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(User::class, 'cancelled_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignIdFor(User::class, 'validated_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('validated_at')->nullable();
            $table->foreignIdFor(User::class, 'closed_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('closed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('validation_summary')->nullable();
            $table->json('report_summary')->nullable();
            $table->string('snapshot_schema_version')->nullable();
            $table->longText('snapshot_canonical_json')->nullable();
            $table->char('snapshot_sha256', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'center_id', 'period_start', 'period_end'], 'attendance_periods_company_center_dates_idx');
            $table->index(['company_id', 'status', 'period_start'], 'attendance_periods_company_status_start_idx');
            $table->index(['company_id', 'scope_type'], 'attendance_periods_company_scope_idx');
            $table->index(['company_id', 'closed_at'], 'attendance_periods_company_closed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_periods');
    }
};
