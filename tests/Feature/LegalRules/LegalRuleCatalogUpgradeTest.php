<?php

namespace Tests\Feature\LegalRules;

use App\Domains\LegalRules\Actions\ResolveLegalRuleVersionForDateAction;
use App\Models\LegalRule;
use App\Models\LegalRuleVersion;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LegalRuleCatalogUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_catalog_and_upgrade_are_idempotent(): void
    {
        $this->seed(LegalRuleSeeder::class);
        $before = LegalRuleVersion::query()->orderBy('id')->get()->toArray();
        $this->upgrade();
        $this->seed(LegalRuleSeeder::class);

        $this->assertSame($before, LegalRuleVersion::query()->orderBy('id')->get()->toArray());
    }

    public function test_legacy_open_ended_version_is_preserved_and_future_rules_are_added(): void
    {
        $rule = LegalRule::factory()->create(['code' => 'maximum_weekly_hours']);
        $version = LegalRuleVersion::factory()->create([
            'legal_rule_id' => $rule->id,
            'value' => ['minutes' => 2880],
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'source_reference' => 'Original reviewed source',
        ]);
        $before = $version->fresh()->toArray();
        $this->upgrade();

        $this->assertSame($before, $version->fresh()->toArray());
        $resolver = app(ResolveLegalRuleVersionForDateAction::class);
        $this->assertSame($version->id, $resolver->handle('maximum_weekly_hours', '2026-08-01')->id);
        $this->assertSame(2760, $resolver->handle('maximum_weekly_hours', '2027-01-01')->value['minutes']);
        $this->assertSame(2400, $resolver->handle('maximum_weekly_hours', '2030-01-01')->value['minutes']);
    }

    public function test_existing_approval_states_sources_and_parent_status_are_not_changed(): void
    {
        $this->seed(LegalRuleSeeder::class);
        $rule = LegalRule::query()->where('code', 'maximum_weekly_hours')->firstOrFail();
        $rule->update(['status' => LegalRule::STATUS_INACTIVE]);
        $version = $rule->versions()->where('version', 2)->firstOrFail();
        $version->update(['status' => LegalRuleVersion::STATUS_REVIEWED, 'notes' => 'Reviewed evidence', 'source_reference' => 'Original reviewed source']);
        $before = $version->fresh()->toArray();

        $this->upgrade();
        $this->seed(LegalRuleSeeder::class);

        $this->assertSame($before, $version->fresh()->toArray());
        $this->assertSame(LegalRule::STATUS_INACTIVE, $rule->fresh()->status);
    }

    public function test_conflicting_catalog_is_reported_without_partial_changes(): void
    {
        $rule = LegalRule::factory()->create(['code' => 'maximum_weekly_hours']);
        $version = LegalRuleVersion::factory()->create([
            'legal_rule_id' => $rule->id,
            'version' => 2,
            'value' => ['minutes' => 2700],
            'effective_from' => '2027-01-01',
        ]);
        $before = $version->fresh()->toArray();

        try {
            $this->upgrade();
            $this->fail('A conflicting version must require explicit review.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Conflicto', $exception->getMessage());
        }

        $this->assertSame($before, $version->fresh()->toArray());
        $this->assertSame(1, LegalRuleVersion::query()->count());
    }

    public function test_custom_intermediate_version_is_not_silently_superseded(): void
    {
        $rule = LegalRule::factory()->create(['code' => 'maximum_weekly_hours']);
        LegalRuleVersion::factory()->create([
            'legal_rule_id' => $rule->id,
            'version' => 7,
            'value' => ['minutes' => 2700],
            'effective_from' => '2026-07-01',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conflicto de vigencia');
        $this->upgrade();
    }

    private function upgrade(): void
    {
        $migration = require database_path('migrations/2026_10_12_000000_version_progressive_weekly_legal_limits.php');
        $migration->up();
    }
}
