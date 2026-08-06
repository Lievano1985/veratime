<?php

namespace Tests\Feature\LegalRules;

use App\Domains\LegalRules\Actions\ResolveLegalParameterForDateAction;
use App\Domains\LegalRules\Actions\ResolveLegalRuleVersionForDateAction;
use App\Models\Company;
use App\Models\LegalParameter;
use App\Models\LegalRule;
use App\Models\LegalRuleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegalRuleVersioningFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_rule_tables_are_available_without_future_operational_modules(): void
    {
        $this->assertTrue(Schema::hasTable('legal_rules'));
        $this->assertTrue(Schema::hasTable('legal_rule_versions'));
        $this->assertTrue(Schema::hasTable('legal_parameters'));
        $this->assertTrue(Schema::hasTable('work_day_calculations'));
        $this->assertTrue(Schema::hasTable('alerts'));
        $this->assertFalse(Schema::hasTable('incidents'));
        $this->assertFalse(Schema::hasTable('reports'));
    }

    public function test_resolves_active_legal_rule_version_by_work_date(): void
    {
        $rule = LegalRule::factory()->create([
            'code' => 'daily_limit_diurnal',
            'category' => 'daily_limit',
            'status' => LegalRule::STATUS_ACTIVE,
        ]);
        LegalRuleVersion::factory()->create([
            'legal_rule_id' => $rule->id,
            'version' => 1,
            'value' => ['minutes' => 480],
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'status' => LegalRuleVersion::STATUS_ACTIVE,
        ]);
        LegalRuleVersion::factory()->create([
            'legal_rule_id' => $rule->id,
            'version' => 2,
            'value' => ['minutes' => 450],
            'effective_from' => '2027-01-01',
            'effective_to' => null,
            'status' => LegalRuleVersion::STATUS_ACTIVE,
        ]);

        $version = app(ResolveLegalRuleVersionForDateAction::class)->handle('daily_limit_diurnal', '2027-02-01');

        $this->assertNotNull($version);
        $this->assertSame(2, $version->version);
        $this->assertSame(['minutes' => 450], $version->value);
    }

    public function test_ignores_inactive_rules_and_draft_versions(): void
    {
        $inactiveRule = LegalRule::factory()->create([
            'code' => 'maximum_weekly_hours',
            'status' => LegalRule::STATUS_INACTIVE,
        ]);
        LegalRuleVersion::factory()->create([
            'legal_rule_id' => $inactiveRule->id,
            'status' => LegalRuleVersion::STATUS_ACTIVE,
        ]);
        $activeRule = LegalRule::factory()->create([
            'code' => 'daily_limit_mixed',
            'status' => LegalRule::STATUS_ACTIVE,
        ]);
        LegalRuleVersion::factory()->create([
            'legal_rule_id' => $activeRule->id,
            'version' => 1,
            'status' => LegalRuleVersion::STATUS_DRAFT,
        ]);

        $this->assertNull(app(ResolveLegalRuleVersionForDateAction::class)->handle('maximum_weekly_hours', '2026-08-03'));
        $this->assertNull(app(ResolveLegalRuleVersionForDateAction::class)->handle('daily_limit_mixed', '2026-08-03'));
    }

    public function test_company_legal_parameter_overrides_global_parameter_for_same_date(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);
        LegalParameter::factory()->create([
            'company_id' => null,
            'code' => 'weekly_start_day',
            'value' => ['day' => 'monday'],
            'effective_from' => '2026-01-01',
            'status' => LegalParameter::STATUS_ACTIVE,
        ]);
        LegalParameter::factory()->forCompany($company)->create([
            'code' => 'weekly_start_day',
            'value' => ['day' => 'sunday'],
            'effective_from' => '2026-08-01',
            'status' => LegalParameter::STATUS_ACTIVE,
        ]);
        LegalParameter::factory()->forCompany($otherCompany)->create([
            'code' => 'weekly_start_day',
            'value' => ['day' => 'saturday'],
            'effective_from' => '2026-08-01',
            'status' => LegalParameter::STATUS_ACTIVE,
        ]);

        $parameter = app(ResolveLegalParameterForDateAction::class)->handle($company, 'weekly_start_day', '2026-08-03');
        $fallback = app(ResolveLegalParameterForDateAction::class)->handle(null, 'weekly_start_day', '2026-08-03');

        $this->assertSame(['day' => 'sunday'], $parameter?->value);
        $this->assertSame(['day' => 'monday'], $fallback?->value);
    }
}
