<?php

use App\Domains\LegalRules\Actions\EnsureLegalRuleCatalogAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('legal_rules')->where('code', 'maximum_weekly_hours')->exists()) {
            return; // A new installation receives the catalog from LegalRuleSeeder.
        }

        app(EnsureLegalRuleCatalogAction::class)->ensureProgressiveWeeklyLimits();
    }

    public function down(): void
    {
        // Legal versions may already be referenced by historical calculations.
    }
};
