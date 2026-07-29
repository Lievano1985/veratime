<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_events', function (Blueprint $table): void {
            $table->dateTime('voided_at')->nullable()->after('status');
            $table->foreignIdFor(User::class, 'voided_by_user_id')
                ->nullable()
                ->after('voided_at')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('void_reason', 500)->nullable()->after('voided_by_user_id');

            $table->index(['company_id', 'voided_at'], 'time_events_company_voided_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('time_events', function (Blueprint $table): void {
            $table->dropIndex('time_events_company_voided_at_idx');
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
