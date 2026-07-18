<?php

namespace Tests\Feature\BlockD1;

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\AssignTemporarySupportAction;
use App\Domains\Scheduling\Actions\AssignScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\EndScheduleProfileAssignmentAction;
use App\Domains\Scheduling\Actions\InactivateScheduleProfileAction;
use App\Domains\Scheduling\Actions\ReactivateScheduleProfileAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileAssignmentAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileWeeklyRulesAction;
use App\Domains\Scheduling\Actions\ResolveScheduleProfileForRelationshipAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ScheduleProfileDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_keeps_d1_tables_and_daily_core_without_future_calculation_tables(): void
    {
        $this->assertTrue(Schema::hasTable('schedule_profiles'));
        $this->assertTrue(Schema::hasTable('schedule_profile_weekly_rules'));
        $this->assertTrue(Schema::hasTable('schedule_profile_assignments'));
        $this->assertTrue(Schema::hasColumn('schedule_profiles', 'pattern_mode'));
        $this->assertFalse(Schema::hasColumn('schedule_profiles', 'center_id'));
        $this->assertFalse(Schema::hasColumn('schedule_profiles', 'worker_id'));
        $this->assertFalse(Schema::hasColumn('schedule_profiles', 'timezone'));
        $this->assertFalse(Schema::hasColumn('schedule_profiles', 'required_minutes'));
        $this->assertFalse(Schema::hasTable('rotation_patterns'));
        $this->assertTrue(Schema::hasTable('schedule_batches'));
        $this->assertTrue(Schema::hasTable('daily_schedule_assignments'));
        $this->assertTrue(Schema::hasTable('daily_schedule_segments'));
        $this->assertFalse(Schema::hasTable('work_days'));
        $this->assertFalse(Schema::hasTable('work_day_calculations'));
    }

    public function test_creates_pattern_weekly_and_calendar_profiles_with_d1_rules(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $template = $this->shiftTemplate($company, 'APER');

        $pattern = app(CreateScheduleProfileAction::class)->handle($company, [
            'code' => 'PAT',
            'name' => 'Patron semanal',
            'profile_type' => 'pattern',
            'pattern_mode' => 'weekly',
        ], $this->weeklyRules($template));

        $calendar = app(CreateScheduleProfileAction::class)->handle($company, [
            'code' => 'CAL',
            'name' => 'Calendario',
            'profile_type' => 'calendar',
        ]);

        $this->assertSame('pattern', $pattern->profile_type);
        $this->assertSame('weekly', $pattern->pattern_mode);
        $this->assertCount(7, $pattern->weeklyRules);
        $this->assertSame('calendar', $calendar->profile_type);
        $this->assertNull($calendar->pattern_mode);
        $this->assertCount(0, $calendar->weeklyRules);
    }

    public function test_weekly_rules_require_exact_pattern_configuration(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $template = $this->shiftTemplate($company, 'APER');
        $inactiveTemplate = $this->shiftTemplate($company, 'INA', 'inactive');
        $otherTemplate = $this->shiftTemplate(Company::factory()->create(['status' => 'active']), 'EXT');

        $invalidSets = [
            array_slice($this->weeklyRules($template), 0, 6),
            array_map(fn (array $rule): array => ['day_of_week' => $rule['day_of_week'], 'day_type' => 'rest'], $this->weeklyRules($template)),
            array_replace($this->weeklyRules($template), [1 => ['day_of_week' => 1, 'day_type' => 'shift', 'shift_template_id' => $template->id]]),
            array_replace($this->weeklyRules($template), [0 => ['day_of_week' => 1, 'day_type' => 'shift']]),
            array_replace($this->weeklyRules($template), [0 => ['day_of_week' => 1, 'day_type' => 'rest', 'shift_template_id' => $template->id]]),
            array_replace($this->weeklyRules($template), [0 => ['day_of_week' => 1, 'day_type' => 'shift', 'shift_template_id' => $inactiveTemplate->id]]),
            array_replace($this->weeklyRules($template), [0 => ['day_of_week' => 1, 'day_type' => 'shift', 'shift_template_id' => $otherTemplate->id]]),
        ];

        foreach ($invalidSets as $rules) {
            try {
                app(CreateScheduleProfileAction::class)->handle($company, [
                    'code' => 'BAD'.uniqid(),
                    'name' => 'Invalido',
                    'profile_type' => 'pattern',
                    'pattern_mode' => 'weekly',
                ], $rules);
                $this->fail('Reglas invalidas aceptadas.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $calendar = app(CreateScheduleProfileAction::class)->handle($company, ['code' => 'CAL', 'name' => 'Calendario', 'profile_type' => 'calendar']);
        $this->expectException(InvalidArgumentException::class);
        app(ReplaceScheduleProfileWeeklyRulesAction::class)->handle($company, $calendar, $this->weeklyRules($template));
    }

    public function test_legacy_fixed_variable_rotating_aliases_are_not_operational(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $template = $this->shiftTemplate($company, 'APER');

        foreach (['fixed', 'variable', 'rotating'] as $type) {
            try {
                app(CreateScheduleProfileAction::class)->handle($company, [
                    'code' => 'BAD'.strtoupper(str_replace('_', '', $type)),
                    'name' => 'Invalido',
                    'profile_type' => $type,
                    'pattern_mode' => $type === 'fixed' ? 'weekly' : null,
                ], $this->weeklyRules($template));
                $this->fail('Alias legacy aceptado como tipo de perfil.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        foreach ([null, 'rotating'] as $mode) {
            try {
                app(CreateScheduleProfileAction::class)->handle($company, [
                    'code' => 'MOD'.($mode ?: 'NULL'),
                    'name' => 'Modo invalido',
                    'profile_type' => 'pattern',
                    'pattern_mode' => $mode,
                ], $this->weeklyRules($template));
                $this->fail('Modalidad de patron invalida aceptada.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_profile_code_uniqueness_inactivation_and_reactivation(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $template = $this->shiftTemplate($company, 'APER');
        $profile = $this->patternProfile($company, $template, 'BASE');

        $this->patternProfile($otherCompany, $this->shiftTemplate($otherCompany, 'APER'), 'BASE');

        app(InactivateScheduleProfileAction::class)->handle($company, $profile);
        $this->assertSame('inactive', $profile->refresh()->status);
        app(ReactivateScheduleProfileAction::class)->handle($company, $profile);
        $this->assertSame('active', $profile->refresh()->status);

        $this->expectException(InvalidArgumentException::class);
        $this->patternProfile($company, $template, 'base');
    }

    public function test_inactivation_blocks_active_or_future_assignments(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $profile = $this->patternProfile($company, $this->shiftTemplate($company, 'APER'), 'BASE');

        app(AssignScheduleProfileAction::class)->handle($company, $profile, [
            'assignment_scope' => 'company',
            'effective_from' => now()->toDateString(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(InactivateScheduleProfileAction::class)->handle($company, $profile);
    }

    public function test_assignments_validate_scope_columns_tenant_relationship_and_overlap(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $profile = $this->patternProfile($company, $this->shiftTemplate($company, 'APER'), 'BASE');
        $otherProfile = $this->patternProfile($otherCompany, $this->shiftTemplate($otherCompany, 'APER'), 'BASE');
        $center = Center::factory()->for($company)->create(['status' => 'active']);
        $unit = OrganizationalUnit::factory()->for($company)->for($center)->create(['status' => 'active']);
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create([
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'ended_at' => null,
        ]);

        app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'company', 'effective_from' => '2026-08-01']);
        app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'center', 'center_id' => $center->id, 'effective_from' => '2026-08-01']);
        app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'organizational_unit', 'organizational_unit_id' => $unit->id, 'effective_from' => '2026-08-01']);
        app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => $relationship->id, 'effective_from' => '2026-08-01']);

        $this->assertDatabaseCount('schedule_profile_assignments', 4);

        foreach ([
            [$company, $otherProfile, ['assignment_scope' => 'company', 'effective_from' => '2026-09-01']],
            [$company, $profile, ['assignment_scope' => 'company', 'center_id' => $center->id, 'effective_from' => '2026-09-01']],
            [$company, $profile, ['assignment_scope' => 'organizational_unit', 'organizational_unit_id' => OrganizationalUnit::factory()->for($company)->for($center)->create(['status' => 'inactive'])->id, 'effective_from' => '2026-09-01']],
            [$company, $profile, ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => EmploymentRelationship::factory()->forCompany($company)->create(['started_at' => '2026-10-01'])->id, 'effective_from' => '2026-09-01']],
            [$company, $profile, ['assignment_scope' => 'company', 'effective_from' => '2026-08-15']],
        ] as [$targetCompany, $targetProfile, $data]) {
            try {
                app(AssignScheduleProfileAction::class)->handle($targetCompany, $targetProfile, $data);
                $this->fail('Asignacion invalida aceptada.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_replacement_and_end_preserve_assignment_history(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $base = $this->patternProfile($company, $this->shiftTemplate($company, 'APER'), 'BASE');
        $newProfile = app(CreateScheduleProfileAction::class)->handle($company, ['code' => 'CAL', 'name' => 'Calendario', 'profile_type' => 'calendar']);

        $assignment = app(AssignScheduleProfileAction::class)->handle($company, $base, ['assignment_scope' => 'company', 'effective_from' => '2026-08-01']);
        $replacement = app(ReplaceScheduleProfileAssignmentAction::class)->handle($company, $assignment, $newProfile, ['effective_from' => '2026-09-01']);

        $this->assertSame('replaced', $assignment->refresh()->status);
        $this->assertSame($replacement->id, $assignment->replaced_by_id);
        $this->assertSame('2026-08-31', $assignment->effective_to->toDateString());

        app(EndScheduleProfileAssignmentAction::class)->handle($company, $replacement, '2026-09-30');
        $this->assertSame('inactive', $replacement->refresh()->status);
        $this->assertSame('2026-09-30', $replacement->effective_to->toDateString());
    }

    public function test_resolution_priority_uses_direct_unit_center_company_and_ignores_temporary_support(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['status' => 'active']);
        $otherCenter = Center::factory()->for($company)->create(['status' => 'active']);
        $unit = OrganizationalUnit::factory()->for($company)->for($center)->create(['status' => 'active']);
        $supportUnit = OrganizationalUnit::factory()->for($company)->for($otherCenter)->create(['status' => 'active']);
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create([
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
        ]);

        $companyProfile = $this->patternProfile($company, $this->shiftTemplate($company, 'COMP'), 'COMP');
        $centerProfile = $this->patternProfile($company, $this->shiftTemplate($company, 'CENT'), 'CENT');
        $unitProfile = $this->patternProfile($company, $this->shiftTemplate($company, 'UNIT'), 'UNIT');
        $directProfile = $this->patternProfile($company, $this->shiftTemplate($company, 'DIR'), 'DIR');

        app(AssignScheduleProfileAction::class)->handle($company, $companyProfile, ['assignment_scope' => 'company', 'effective_from' => '2026-08-01']);
        app(AssignScheduleProfileAction::class)->handle($company, $centerProfile, ['assignment_scope' => 'center', 'center_id' => $center->id, 'effective_from' => '2026-08-01']);
        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, ['effective_from' => '2026-08-10']);
        app(AssignScheduleProfileAction::class)->handle($company, $unitProfile, ['assignment_scope' => 'organizational_unit', 'organizational_unit_id' => $unit->id, 'effective_from' => '2026-08-01']);
        app(AssignTemporarySupportAction::class)->handle($company, $relationship, $supportUnit, ['effective_from' => '2026-08-01', 'effective_to' => '2026-08-31']);
        app(AssignScheduleProfileAction::class)->handle($company, $directProfile, ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => $relationship->id, 'effective_from' => '2026-09-01']);

        $resolver = app(ResolveScheduleProfileForRelationshipAction::class);

        $this->assertSame('CENT', $resolver->handle($company, $relationship, '2026-08-05')['schedule_profile']->code);
        $this->assertSame('UNIT', $resolver->handle($company, $relationship, '2026-08-15')['schedule_profile']->code);
        $this->assertSame('DIR', $resolver->handle($company, $relationship, '2026-09-05')['schedule_profile']->code);

        $emptyCompany = Company::factory()->create(['status' => 'active']);
        $emptyRelationship = EmploymentRelationship::factory()->forCompany($emptyCompany)->create();
        $this->assertNull($resolver->handle($emptyCompany, $emptyRelationship, '2026-08-01')['schedule_profile']);
    }

    public function test_policies_allow_managers_and_limit_supervisor_assignment_to_relationship_scope(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['status' => 'active']);
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create(['center_id' => $center->id]);
        $profile = $this->patternProfile($company, $this->shiftTemplate($company, 'BASE'), 'BASE');

        foreach ([RoleKey::OWNER, RoleKey::ADMIN, RoleKey::RH] as $roleKey) {
            $user = $this->userWithCompanyRole($company, $roleKey);
            $this->assertTrue(Gate::forUser($user)->allows('create', [ScheduleProfile::class, $company]));
            $this->assertTrue(Gate::forUser($user)->allows('assign', [ScheduleProfile::class, $company, 'company', null, '2026-08-01']));
        }

        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);
        $this->assertFalse(Gate::forUser($supervisor)->allows('create', [ScheduleProfile::class, $company]));
        $this->assertFalse(Gate::forUser($supervisor)->allows('assign', [ScheduleProfile::class, $company, 'company', null, '2026-08-01']));
        $this->assertFalse(Gate::forUser($supervisor)->allows('assign', [ScheduleProfile::class, $company, 'employment_relationship', $relationship, '2026-08-01']));

        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => now()->toDateString()], center: $center);

        $this->assertTrue(Gate::forUser($supervisor)->allows('view', $profile));
        $this->assertTrue(Gate::forUser($supervisor)->allows('assign', [ScheduleProfile::class, $company, 'employment_relationship', $relationship, '2026-08-01']));
        $this->assertFalse(Gate::forUser($supervisor)->allows('assign', [ScheduleProfile::class, $company, 'center', null, '2026-08-01']));

        $this->expectException(InvalidArgumentException::class);
        app(AssignScheduleProfileAction::class)->handle($company, $profile, [
            'assignment_scope' => 'center',
            'center_id' => $center->id,
            'effective_from' => '2026-08-01',
        ], $supervisor);
    }

    public function test_invalid_rules_or_failed_replacement_do_not_leave_partial_state(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $profile = $this->patternProfile($company, $this->shiftTemplate($company, 'BASE'), 'BASE');
        $assignment = app(AssignScheduleProfileAction::class)->handle($company, $profile, ['assignment_scope' => 'company', 'effective_from' => '2026-08-01']);

        try {
            app(ReplaceScheduleProfileWeeklyRulesAction::class)->handle($company, $profile, array_slice($this->weeklyRules($this->shiftTemplate($company, 'NEW')), 0, 6));
        } catch (InvalidArgumentException) {
            $this->assertCount(7, $profile->refresh()->weeklyRules);
        }

        try {
            app(ReplaceScheduleProfileAssignmentAction::class)->handle($company, $assignment, $profile, ['effective_from' => '2026-07-01']);
        } catch (InvalidArgumentException) {
            $this->assertSame('active', $assignment->refresh()->status);
            $this->assertNull($assignment->effective_to);
        }
    }

    public function test_demo_seeder_creates_idempotent_profiles_and_four_resolution_levels(): void
    {
        Artisan::call('db:seed', ['--class' => 'VeraTimeDemoSeeder']);
        Artisan::call('db:seed', ['--class' => 'VeraTimeDemoSeeder']);

        $company = Company::query()->where('tax_id', 'VTD260712XX1')->firstOrFail();
        $resolver = app(ResolveScheduleProfileForRelationshipAction::class);

        $relationships = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->whereIn('external_id', ['demo-rel-VT-001', 'demo-rel-VT-002', 'demo-rel-VT-003', 'demo-rel-VT-004'])
            ->get()
            ->keyBy('external_id');

        $this->assertSame(2, ScheduleProfile::query()->where('company_id', $company->id)->count());
        $this->assertSame(4, ScheduleProfileAssignment::query()->where('company_id', $company->id)->where('status', 'active')->count());
        $this->assertSame('organizational_unit', $resolver->handle($company, $relationships['demo-rel-VT-001'], '2026-08-17')['assignment_scope']);
        $this->assertSame('employment_relationship', $resolver->handle($company, $relationships['demo-rel-VT-002'], '2026-08-17')['assignment_scope']);
        $this->assertSame('company', $resolver->handle($company, $relationships['demo-rel-VT-003'], '2026-08-17')['assignment_scope']);
        $this->assertSame('center', $resolver->handle($company, $relationships['demo-rel-VT-004'], '2026-08-17')['assignment_scope']);
        $this->assertSame(0, DailyScheduleAssignment::query()->where('company_id', $company->id)->count());
    }

    private function patternProfile(Company $company, ShiftTemplate $template, string $code): ScheduleProfile
    {
        return app(CreateScheduleProfileAction::class)->handle($company, [
            'code' => $code,
            'name' => 'Perfil '.$code,
            'profile_type' => 'pattern',
            'pattern_mode' => 'weekly',
        ], $this->weeklyRules($template));
    }

    private function shiftTemplate(Company $company, string $code, string $status = 'active'): ShiftTemplate
    {
        return app(CreateShiftTemplateAction::class)->handle($company, [
            'code' => $code,
            'name' => 'Turno '.$code,
            'status' => $status,
        ], [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'sort_order' => 1],
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

    private function userWithCompanyRole(Company $company, string $roleKey): User
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => strtoupper($roleKey), 'description' => 'Rol prueba', 'is_system' => true],
        );
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return $user->refresh();
    }
}
