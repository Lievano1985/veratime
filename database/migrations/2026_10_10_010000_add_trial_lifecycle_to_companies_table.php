<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('is_trial')->default(false)->after('status');
            $table->dateTime('trial_ends_at')->nullable()->after('is_trial');
            $table->index(['is_trial', 'trial_ends_at'], 'companies_trial_expiration_index');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropIndex('companies_trial_expiration_index');
            $table->dropColumn(['is_trial', 'trial_ends_at']);
        });
    }
};
