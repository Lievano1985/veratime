<?php

namespace Tests\Feature\BlockE1;

use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Scheduling\Actions\AssignScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileCycleRulesAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileFlexibleRulesAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileOnCallRulesAction;
use App\Domains\Scheduling\Actions\ResolveScheduleProfileForRelationshipAction;
use App\Domains\Scheduling\Actions\ResolveScheduleProfileRuleForDateAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\ScheduleProfileCycleRule;
use App\Models\ScheduleProfileFlexibleRule;
use App\Models\ScheduleProfileOnCallRule;
use App\Models\ShiftTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class AdvancedScheduleProfileDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_e1_schema_keeps_daily_publishing_tables_without_generating_calculations(): void
    {
        $this->assertTrue(Schema::hasTable('schedule_profile_cycle_rules'));
        $this->assertTrue(Schema::hasTable('schedule_profile_flexible_rules'));
        $this->assertTrue(Schema::hasTable('schedule_profile_on_call_rules'));
        $this->assertTrue(Schema::hasTable('schedule_batches'));
        $this->assertTrue(Schema::hasTable('daily_schedule_assignments'));
        $this->assertTrue(Schema::hasTable('daily_schedule_segments'));
        $this->assertTrue(Schema::hasTable('work_days'));
        $this->assertTrue(Schema::hasTable('work_day_calculations'));
        $this->assertFalse(Schema::hasTable('on_call_activations'));
    }

    public function test_profile_types_accept_e1_values_and_reject_legacy_aliases(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $template = $this->shiftTemplate($company, 'DAY');

        $weekly = app(CreateScheduleProfileAction::class)->handle($company, [
            'code' => 'WEEKLY',
            'name' => 'Semanal',
            'profile_type' => 'pattern',
            'pattern_mode' => 'weekly',
        ], $this->weeklyRules($template));
        $cycle = app(CreateScheduleProfileAction::class)->handle($company, ['code' => 'CYCLE', 'name' => 'Ciclo', 'profile_type' => 'pattern', 'pattern_mode' => 'cycle']);
        $calendar = app(CreateScheduleProfileAction::class)->handle($company, ['code' => 'CAL', 'name' => 'Calendario', 'profile_type' => 'calendar']);
        $flexible = app(CreateScheduleProfileAction::class)->handle($company, ['code' => 'FLEX', 'name' => 'Flexible', 'profile_type' => 'flexible']);
        $onCall = app(CreateScheduleProfileAction::class)->handle($company, ['code' => 'CALL', 'name' => 'Bajo demanda', 'profile_type' => 'on_call']);

        $this->assertSame('weekly', $weekly->pattern_mode);
        $this->assertSame('cycle', $cycle->pattern_mode);
        $this->assertNull($calendar->pattern_mode);
        $this->assertNull($flexible->pattern_mode);
        $this->assertNull($onCall->pattern_mode);

        foreach (['fixed', 'variable', 'rotating'] as $type) {
            $this->assertInvalid(fn () => app(CreateScheduleProfileAction::class)->handle($company, [
                'code' => 'BAD'.strtoupper($type),
                'name' => 'Invalido',
                'profile_type' => $type,
            ]));
        }

        $this->assertInvalid(fn () => app(CreateScheduleProfileAction::class)->handle($company, ['code' => 'PATNULL', 'name' => 'Sin modo', 'profile_type' => 'pattern']));
        foreach (['calendar', 'flexible', 'on_call'] as $type) {
            $this->assertInvalid(fn () => app(CreateScheduleProfileAction::class)->handle($company, [
                'code' => 'MODE'.strtoupper(str_replace('_', '', $type)),
                'name' => 'Modo invalido',
                'profile_type' => $type,
                'pattern_mode' => 'weekly',
            ]));
        }
    }

    public function test_cycle_rules_validate_sequence_templates_tenant_and_resolution(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $morning = $this->shiftTemplate($company, 'MOR');
        $night = $this->shiftTemplate($company, 'NIG', 'active', '22:00', '06:00', 0, 1);
        $inactive = $this->shiftTemplate($company, 'INA', 'inactive');
        $otherTemplate = $this->shiftTemplate($otherCompany, 'EXT');
        $profile = $this->profile($company, 'CYCLE', 'pattern', 'cycle');

        $profile = app(ReplaceScheduleProfileCycleRulesAction::class)->handle($company, $profile, $this->cycleRules($morning, $night));
        $this->assertCount(4, $profile->cycleRules);

        foreach ([
            array_slice($this->cycleRules($morning, $night), 0, 1),
            [['cycle_day' => 1, 'day_type' => 'rest'], ['cycle_day' => 3, 'day_type' => 'shift', 'shift_template_id' => $morning->id]],
            [['cycle_day' => 1, 'day_type' => 'shift', 'shift_template_id' => $morning->id], ['cycle_day' => 1, 'day_type' => 'shift', 'shift_template_id' => $morning->id]],
            [['cycle_day' => 1, 'day_type' => 'rest'], ['cycle_day' => 2, 'day_type' => 'rest']],
            [['cycle_day' => 1, 'day_type' => 'shift'], ['cycle_day' => 2, 'day_type' => 'rest']],
            [['cycle_day' => 1, 'day_type' => 'rest', 'shift_template_id' => $morning->id], ['cycle_day' => 2, 'day_type' => 'shift', 'shift_template_id' => $morning->id]],
            [['cycle_day' => 1, 'day_type' => 'shift', 'shift_template_id' => $inactive->id], ['cycle_day' => 2, 'day_type' => 'rest']],
            [['cycle_day' => 1, 'day_type' => 'shift', 'shift_template_id' => $otherTemplate->id], ['cycle_day' => 2, 'day_type' => 'rest']],
        ] as $rules) {
            $this->assertInvalid(fn () => app(ReplaceScheduleProfileCycleRulesAction::class)->handle($company, $profile, $rules));
        }

        $assignment = app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'company', 'effective_from' => '2026-08-01']);
        $resolver = app(ResolveScheduleProfileRuleForDateAction::class);
        $this->assertSame(1, $resolver->handle($assignment, '2026-08-01')['cycle_day']);
        $this->assertSame(4, $resolver->handle($assignment, '2026-08-04')['cycle_day']);
        $this->assertSame(1, $resolver->handle($assignment, '2026-08-05')['cycle_day']);
        $this->assertSame('rest', $resolver->handle($assignment, '2026-08-04')['day_type']);
        $this->assertInvalid(fn () => $resolver->handle($assignment, '2026-07-31'));
    }

    public function test_cycle_uses_assignment_effective_from_as_anchor(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $template = $this->shiftTemplate($company, 'BASE');
        $profile = app(ReplaceScheduleProfileCycleRulesAction::class)->handle(
            $company,
            $this->profile($company, 'CYCLE', 'pattern', 'cycle'),
            $this->cycleRules($template),
        );

        $first = app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'company', 'effective_from' => '2026-08-01', 'effective_to' => '2026-08-31']);
        $second = app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'company', 'effective_from' => '2026-09-03']);
        $resolver = app(ResolveScheduleProfileRuleForDateAction::class);

        $this->assertSame(3, $resolver->handle($first, '2026-08-03')['cycle_day']);
        $this->assertSame(1, $resolver->handle($second, '2026-09-03')['cycle_day']);
    }

    public function test_flexible_rules_validate_week_and_resolution(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $profile = $this->profile($company, 'FLEX', 'flexible');

        $profile = app(ReplaceScheduleProfileFlexibleRulesAction::class)->handle($company, $profile, $this->flexibleRules());
        $this->assertCount(7, $profile->flexibleRules);
        $this->assertSame('rest', $profile->flexibleRules->firstWhere('day_of_week', 6)->day_type);
        $this->assertNull($profile->flexibleRules->firstWhere('day_of_week', 6)->required_minutes);

        foreach ([
            array_slice($this->flexibleRules(), 0, 6),
            array_replace($this->flexibleRules(), [0 => ['day_of_week' => 1, 'day_type' => 'work']]),
            array_replace($this->flexibleRules(), [0 => ['day_of_week' => 1, 'day_type' => 'work', 'required_minutes' => 0]]),
            array_replace($this->flexibleRules(), [0 => ['day_of_week' => 1, 'day_type' => 'work', 'required_minutes' => 480, 'window_start_local_time' => '07:00']]),
            array_replace($this->flexibleRules(), [0 => ['day_of_week' => 1, 'day_type' => 'work', 'required_minutes' => 480, 'window_start_local_time' => '08:00', 'window_end_local_time' => '08:00']]),
            array_replace($this->flexibleRules(), [0 => ['day_of_week' => 1, 'day_type' => 'work', 'required_minutes' => 480, 'window_start_local_time' => '07:00', 'window_end_local_time' => '08:01', 'window_start_day_offset' => 0, 'window_end_day_offset' => 1]]),
            array_map(fn (array $rule): array => ['day_of_week' => $rule['day_of_week'], 'day_type' => 'rest'], $this->flexibleRules()),
        ] as $rules) {
            $this->assertInvalid(fn () => app(ReplaceScheduleProfileFlexibleRulesAction::class)->handle($company, $profile, $rules));
        }

        $nightWindow = array_replace($this->flexibleRules(), [
            0 => ['day_of_week' => 1, 'day_type' => 'work', 'required_minutes' => 480, 'window_start_local_time' => '22:00', 'window_end_local_time' => '06:00', 'window_start_day_offset' => 0, 'window_end_day_offset' => 1],
        ]);
        app(ReplaceScheduleProfileFlexibleRulesAction::class)->handle($company, $profile, $nightWindow);
        $assignment = app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'company', 'effective_from' => '2026-08-01']);
        $result = app(ResolveScheduleProfileRuleForDateAction::class)->handle($assignment, '2026-08-03');
        $this->assertSame('flexible', $result['resolved_rule_type']);
        $this->assertSame(480, $result['required_minutes']);
        $this->assertSame(1, $result['window_end_day_offset']);
    }

    public function test_on_call_rules_validate_availability_and_resolution_without_counting_availability_as_work(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $profile = $this->profile($company, 'CALL', 'on_call');

        $profile = app(ReplaceScheduleProfileOnCallRulesAction::class)->handle($company, $profile, $this->onCallRules());
        $this->assertCount(7, $profile->onCallRules);

        foreach ([
            array_slice($this->onCallRules(), 0, 6),
            array_replace($this->onCallRules(), [0 => ['day_of_week' => 1, 'day_type' => 'on_call']]),
            array_replace($this->onCallRules(), [0 => ['day_of_week' => 1, 'day_type' => 'on_call', 'availability_start_local_time' => '06:00', 'availability_end_local_time' => '22:00', 'max_work_minutes' => 0]]),
            array_replace($this->onCallRules(), [0 => ['day_of_week' => 1, 'day_type' => 'on_call', 'availability_start_local_time' => '06:00', 'availability_end_local_time' => '06:01', 'availability_start_day_offset' => 0, 'availability_end_day_offset' => 1, 'max_work_minutes' => 480]]),
            array_map(fn (array $rule): array => ['day_of_week' => $rule['day_of_week'], 'day_type' => 'rest'], $this->onCallRules()),
        ] as $rules) {
            $this->assertInvalid(fn () => app(ReplaceScheduleProfileOnCallRulesAction::class)->handle($company, $profile, $rules));
        }

        $nightAvailability = array_replace($this->onCallRules(), [
            0 => ['day_of_week' => 1, 'day_type' => 'on_call', 'availability_start_local_time' => '22:00', 'availability_end_local_time' => '06:00', 'availability_start_day_offset' => 0, 'availability_end_day_offset' => 1, 'max_work_minutes' => 480],
        ]);
        app(ReplaceScheduleProfileOnCallRulesAction::class)->handle($company, $profile, $nightAvailability);
        $assignment = app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'company', 'effective_from' => '2026-08-01']);
        $result = app(ResolveScheduleProfileRuleForDateAction::class)->handle($assignment, '2026-08-03');

        $this->assertSame('on_call', $result['resolved_rule_type']);
        $this->assertSame('on_call', $result['day_type']);
        $this->assertSame(480, $result['max_work_minutes']);
        $this->assertArrayNotHasKey('required_minutes', $result);
    }

    public function test_invalid_replacement_is_atomic_and_tenant_manipulation_is_blocked(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $template = $this->shiftTemplate($company, 'BASE');
        $otherProfile = $this->profile($otherCompany, 'OTHER', 'pattern', 'cycle');
        $profile = app(ReplaceScheduleProfileCycleRulesAction::class)->handle(
            $company,
            $this->profile($company, 'CYCLE', 'pattern', 'cycle'),
            $this->cycleRules($template),
        );

        $this->assertCount(4, $profile->cycleRules);
        $this->assertInvalid(fn () => app(ReplaceScheduleProfileCycleRulesAction::class)->handle($company, $profile, [['cycle_day' => 1, 'day_type' => 'rest'], ['cycle_day' => 2, 'day_type' => 'rest']]));
        $this->assertCount(4, $profile->refresh()->cycleRules);
        $this->assertInvalid(fn () => app(ReplaceScheduleProfileCycleRulesAction::class)->handle($company, $otherProfile, $this->cycleRules($template)));
    }

    public function test_resolution_uses_existing_inheritance_and_temporary_support_does_not_change_profile_or_cycle(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['status' => 'active']);
        $supportCenter = Center::factory()->for($company)->create(['status' => 'active']);
        $unit = OrganizationalUnit::factory()->for($company)->for($center)->create(['status' => 'active']);
        $supportUnit = OrganizationalUnit::factory()->for($company)->for($supportCenter)->create(['status' => 'active']);
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create(['center_id' => $center->id, 'started_at' => '2026-08-01']);
        $template = $this->shiftTemplate($company, 'BASE');

        $companyProfile = $this->cycleProfile($company, 'COMP', $template);
        $centerProfile = $this->cycleProfile($company, 'CENT', $template);
        $unitProfile = $this->cycleProfile($company, 'UNIT', $template);
        $directProfile = $this->cycleProfile($company, 'DIR', $template);

        app(AssignScheduleProfileAction::class)->handle($company, $companyProfile, ['assignment_scope' => 'company', 'effective_from' => '2026-08-01']);
        app(AssignScheduleProfileAction::class)->handle($company, $centerProfile, ['assignment_scope' => 'center', 'center_id' => $center->id, 'effective_from' => '2026-08-01']);
        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, ['effective_from' => '2026-08-10']);
        EmploymentUnitAssignment::query()->forceCreate([
            'company_id' => $company->id,
            'employment_relationship_id' => $relationship->id,
            'organizational_unit_id' => $supportUnit->id,
            'assignment_type' => 'temporary_support',
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-08-31',
            'status' => 'active',
            'source' => 'system',
            'metadata' => ['legacy' => true],
        ]);
        app(AssignScheduleProfileAction::class)->handle($company, $unitProfile, ['assignment_scope' => 'organizational_unit', 'organizational_unit_id' => $unit->id, 'effective_from' => '2026-08-01']);
        app(AssignScheduleProfileAction::class)->handle($company, $directProfile, ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => $relationship->id, 'effective_from' => '2026-09-03']);

        $effective = app(ResolveScheduleProfileForRelationshipAction::class);
        $rule = app(ResolveScheduleProfileRuleForDateAction::class);

        $this->assertSame('UNIT', $effective->handle($company, $relationship, '2026-08-05')['schedule_profile']->code);
        $this->assertSame('UNIT', $effective->handle($company, $relationship, '2026-08-15')['schedule_profile']->code);
        $unitAssignment = $effective->handle($company, $relationship, '2026-08-15')['assignment'];
        $this->assertSame(1, $rule->handle($unitAssignment, '2026-08-17')['cycle_day']);
        $directAssignment = $effective->handle($company, $relationship, '2026-09-03')['assignment'];
        $this->assertSame('DIR', $directAssignment->scheduleProfile->code);
        $this->assertSame(1, $rule->handle($directAssignment, '2026-09-03')['cycle_day']);
    }

    public function test_scenario_seeder_is_idempotent_and_creates_e1_isolated_scenarios_without_daily_schedules(): void
    {
        Artisan::call('db:seed', ['--class' => 'VeraTimeScheduleProfileScenarioSeeder']);
        Artisan::call('db:seed', ['--class' => 'VeraTimeScheduleProfileScenarioSeeder']);

        $companies = Company::query()->whereIn('tax_id', ['VTSP-CYCLE', 'VTSP-FLEX', 'VTSP-ONCALL'])->get()->keyBy('tax_id');

        $this->assertCount(3, $companies);
        $this->assertSame(1, ScheduleProfile::query()->where('company_id', $companies['VTSP-CYCLE']->id)->where('profile_type', 'pattern')->where('pattern_mode', 'cycle')->count());
        $this->assertSame(8, ScheduleProfileCycleRule::query()->where('company_id', $companies['VTSP-CYCLE']->id)->count());
        $this->assertSame(7, ScheduleProfileFlexibleRule::query()->where('company_id', $companies['VTSP-FLEX']->id)->count());
        $this->assertSame(7, ScheduleProfileOnCallRule::query()->where('company_id', $companies['VTSP-ONCALL']->id)->count());
        $this->assertSame(1, ScheduleProfileAssignment::query()->where('company_id', $companies['VTSP-CYCLE']->id)->where('status', 'active')->count());
        $this->assertSame(0, DailyScheduleAssignment::query()->whereIn('company_id', $companies->pluck('id'))->count());
    }

    private function assertInvalid(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Configuracion invalida aceptada.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    private function profile(Company $company, string $code, string $type, ?string $patternMode = null): ScheduleProfile
    {
        return app(CreateScheduleProfileAction::class)->handle($company, [
            'code' => $code,
            'name' => 'Perfil '.$code,
            'profile_type' => $type,
            'pattern_mode' => $patternMode,
        ]);
    }

    private function cycleProfile(Company $company, string $code, ShiftTemplate $template): ScheduleProfile
    {
        return app(ReplaceScheduleProfileCycleRulesAction::class)->handle(
            $company,
            $this->profile($company, $code, 'pattern', 'cycle'),
            $this->cycleRules($template),
        );
    }

    private function shiftTemplate(Company $company, string $code, string $status = 'active', string $start = '08:00', string $end = '16:00', int $startOffset = 0, int $endOffset = 0): ShiftTemplate
    {
        return app(CreateShiftTemplateAction::class)->handle($company, [
            'code' => $code,
            'name' => 'Turno '.$code,
            'status' => $status,
        ], [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => $start, 'end_local_time' => $end, 'start_day_offset' => $startOffset, 'end_day_offset' => $endOffset, 'sort_order' => 1],
        ]);
    }

    private function weeklyRules(ShiftTemplate $template): array
    {
        return [
            ['day_of_week' => 1, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 2, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 3, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 4, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 5, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 6, 'day_type' => 'rest'],
            ['day_of_week' => 7, 'day_type' => 'rest'],
        ];
    }

    private function cycleRules(ShiftTemplate $template, ?ShiftTemplate $secondTemplate = null): array
    {
        $secondTemplate ??= $template;

        return [
            ['cycle_day' => 1, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['cycle_day' => 2, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['cycle_day' => 3, 'day_type' => 'shift', 'shift_template_id' => $secondTemplate->id],
            ['cycle_day' => 4, 'day_type' => 'rest'],
        ];
    }

    private function flexibleRules(): array
    {
        $rules = [];
        for ($day = 1; $day <= 5; $day++) {
            $rules[] = [
                'day_of_week' => $day,
                'day_type' => 'work',
                'required_minutes' => 480,
                'window_start_local_time' => '07:00',
                'window_end_local_time' => '20:00',
            ];
        }
        $rules[] = ['day_of_week' => 6, 'day_type' => 'rest', 'required_minutes' => 480, 'window_start_local_time' => '07:00', 'window_end_local_time' => '20:00'];
        $rules[] = ['day_of_week' => 7, 'day_type' => 'rest'];

        return $rules;
    }

    private function onCallRules(): array
    {
        $rules = [];
        for ($day = 1; $day <= 7; $day++) {
            $rules[] = [
                'day_of_week' => $day,
                'day_type' => 'on_call',
                'availability_start_local_time' => '06:00',
                'availability_end_local_time' => '22:00',
                'max_work_minutes' => 480,
            ];
        }

        return $rules;
    }
}
