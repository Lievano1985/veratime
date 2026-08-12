<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_periods', function (Blueprint $table): void {
            $table->foreignIdFor(User::class, 'validated_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->foreignIdFor(User::class, 'closed_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('closed_at')->nullable()->after('closed_by');
            $table->json('validation_summary')->nullable()->after('cancellation_reason');
            $table->json('report_summary')->nullable()->after('validation_summary');
            $table->string('snapshot_schema_version')->nullable()->after('report_summary');
            $table->longText('snapshot_canonical_json')->nullable()->after('snapshot_schema_version');
            $table->char('snapshot_sha256', 64)->nullable()->after('snapshot_canonical_json');

            $table->index(['company_id', 'closed_at'], 'attendance_periods_company_closed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_periods', function (Blueprint $table): void {
            $table->dropIndex('attendance_periods_company_closed_at_idx');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn('validated_at');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('closed_at');
            $table->dropColumn('validation_summary');
            $table->dropColumn('report_summary');
            $table->dropColumn('snapshot_schema_version');
            $table->dropColumn('snapshot_canonical_json');
            $table->dropColumn('snapshot_sha256');
        });
    }
};
