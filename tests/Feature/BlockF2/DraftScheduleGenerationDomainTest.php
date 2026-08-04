<?php

namespace Tests\Feature\BlockF2;

use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Scheduling\Actions\AssignScheduleProfileAction;
use App\Domains\Scheduling\Actions\BuildScheduleBatchSnapshotAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\GenerateDraftScheduleBatchFromProfilesAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileCycleRulesAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileFlexibleRulesAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileOnCallRulesAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileWeeklyRulesAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class DraftScheduleGenerationDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_company_managers_generate_and_supervisor_or_foreign_users_are_blocked(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $this->assignWeeklyCompanyProfile($company, $this->template($company), '2026-08-01');
        $batch = $this->batch($company, $center);

        foreach ([RoleKey::OWNER, RoleKey::ADMIN, RoleKey::RH] as $role) {
            $result = $this->generate($this->userWithCompanyRole($company, $role), $company, $batch);
            $this->assertGreaterThanOrEqual(1, $result->assignmentsCreated + $result->assignmentsPreserved);
        }

        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);
        $this->assertInvalid(fn () => $this->generate($supervisor, $company, $batch));

        $otherCompany = Company::factory()->create(['status' => 'active']);
        $foreign = $this->userWithCompanyRole($otherCompany, RoleKey::RH);
        $this->assertInvalid(fn () => $this->generate($foreign, $company, $batch));

        $company->forceFill(['status' => 'inactive'])->save();
        $this->assertInvalid(fn () => $this->generate($this->userWithCompanyRole($company->refresh(), RoleKey::RH), $company->refresh(), $batch));
        $this->assertSame($center->id, $relationship->center_id);
    }

    public function test_generation_requires_draft_batch_same_company_and_center(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $actor = $this->userWithCompanyRole($company, RoleKey::RH);
        $batch = $this->batch($company, $center);

        foreach (['published', 'superseded', 'cancelled'] as $status) {
            $batch->forceFill(['status' => $status])->save();
            $this->assertInvalid(fn () => $this->generate($actor, $company, $batch->refresh()));
        }

        $otherCompany = Company::factory()->create(['status' => 'active']);
        $otherCenter = Center::factory()->for($otherCompany)->create(['status' => 'active']);
        $foreignBatch = $this->batch($otherCompany, $otherCenter);

        $this->assertInvalid(fn () => $this->generate($actor, $company, $foreignBatch));
    }

    public function test_generates_only_applicable_active_relationship_dates_for_batch_center(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $template = $this->template($company);
        $this->assignWeeklyCompanyProfile($company, $template, '2026-08-01');
        $full = $this->relationship($company, $center, 'FULL', '2026-08-01');
        $startsMid = $this->relationship($company, $center, 'MIDSTART', '2026-08-05');
        $endsMid = $this->relationship($company, $center, 'MIDEND', '2026-08-01', '2026-08-06');
        $this->relationship($company, $center, 'OUTSIDE', '2026-09-01');
        $otherCenter = Center::factory()->for($company)->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $this->relationship($company, $otherCenter, 'OTHER', '2026-08-01');
        $inactive = $this->relationship($company, $center, 'INACTIVE', '2026-08-01');
        $inactive->forceFill(['status' => 'inactive'])->save();

        $batch = $this->batch($company, $center, '2026-08-03', '2026-08-07');
        $result = $this->generate($this->userWithCompanyRole($company, RoleKey::RH), $company, $batch);

        $this->assertSame(3, $result->relationshipsConsidered);
        $this->assertSame(7 + 5 + 4, $result->assignmentsCreated);
        $this->assertSame(7, DailyScheduleAssignment::query()->where('employment_relationship_id', $full->id)->count());
        $this->assertSame(5, DailyScheduleAssignment::query()->where('employment_relationship_id', $startsMid->id)->count());
        $this->assertSame(4, DailyScheduleAssignment::query()->where('employment_relationship_id', $endsMid->id)->count());
    }

    public function test_weekly_profile_generates_shift_rest_segments_utc_timezone_and_primary_unit_snapshot(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext(timezone: 'America/Monterrey');
        $unit = $this->unit($company, $center, 'OPS');
        $this->primaryUnit($company, $relationship, $unit, '2026-08-01');

        $template = $this->templateWithBreaks($company);
        $this->assignWeeklyCompanyProfile($company, $template, '2026-08-01');

        $batch = $this->batch($company, $center, '2026-08-03', '2026-08-09');
        $result = $this->generate($this->userWithCompanyRole($company, RoleKey::RH), $company, $batch);

        $this->assertSame(5, $result->assignmentsShift);
        $this->assertSame(2, $result->assignmentsRest);

        $monday = DailyScheduleAssignment::query()->with('segments')->where('employment_relationship_id', $relationship->id)->whereDate('work_date', '2026-08-03')->firstOrFail();
        $this->assertSame('shift', $monday->day_type);
        $this->assertSame('America/Monterrey', $monday->timezone);
        $this->assertSame($unit->id, $monday->organizational_unit_id);
        $this->assertCount(3, $monday->segments);
        $this->assertSame($this->utc('2026-08-03', '08:00:00', 0, 'America/Monterrey'), $monday->segments[0]->starts_at_utc->toDateTimeString());
        $this->assertSame('duration', $monday->segments[2]->timing_mode);
        $this->assertFalse($monday->segments[2]->is_paid);

        $wednesday = DailyScheduleAssignment::query()->whereDate('work_date', '2026-08-05')->firstOrFail();
        $this->assertSame($unit->id, $wednesday->organizational_unit_id);

        $saturday = DailyScheduleAssignment::query()->whereDate('work_date', '2026-08-08')->firstOrFail();
        $this->assertSame('rest', $saturday->day_type);
        $this->assertSame(0, $saturday->segments()->count());
    }

    public function test_cycle_profile_respects_assignment_effective_from_and_does_not_restart_at_batch_start(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $morning = $this->template($company, 'MOR', '06:00', '14:00');
        $night = $this->template($company, 'NIGHT', '22:00', '06:00', 0, 1);
        $profile = $this->cycleProfile($company, $morning, $night);
        $first = $this->relationship($company, $center, 'CYC1', '2026-08-01');
        $second = $this->relationship($company, $center, 'CYC2', '2026-08-01');
        $actor = $this->userWithCompanyRole($company, RoleKey::RH);

        $this->assignProfile($company, $profile, ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => $first->id, 'effective_from' => '2026-08-01'], $actor);
        $this->assignProfile($company, $profile, ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => $second->id, 'effective_from' => '2026-08-03'], $actor);

        $batch = $this->batch($company, $center, '2026-08-03', '2026-08-07');
        $this->generate($actor, $company, $batch);

        $firstDay = DailyScheduleAssignment::query()->where('employment_relationship_id', $first->id)->whereDate('work_date', '2026-08-03')->firstOrFail();
        $secondDay = DailyScheduleAssignment::query()->where('employment_relationship_id', $second->id)->whereDate('work_date', '2026-08-03')->firstOrFail();

        $this->assertSame(3, $firstDay->source_reference['cycle_day']);
        $this->assertSame('rest', $firstDay->day_type);
        $this->assertSame(1, $secondDay->source_reference['cycle_day']);
        $this->assertSame('shift', $secondDay->day_type);

        $nightDay = DailyScheduleAssignment::query()->with('segments')->where('employment_relationship_id', $second->id)->whereDate('work_date', '2026-08-04')->firstOrFail();
        $this->assertSame($night->id, $nightDay->shift_template_id);
        $this->assertSame(1, $nightDay->segments[0]->end_day_offset);
    }

    public function test_calendar_flexible_on_call_and_missing_profile_convert_to_expected_draft_day_types(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $actor = $this->userWithCompanyRole($company, RoleKey::RH);
        $calendar = $this->relationship($company, $center, 'CAL', '2026-08-01');
        $flex = $this->relationship($company, $center, 'FLEX', '2026-08-01');
        $call = $this->relationship($company, $center, 'CALL', '2026-08-01');
        $none = $this->relationship($company, $center, 'NONE', '2026-08-01');

        $this->assignProfile($company, $this->profile($company, 'CALPROF', 'calendar'), ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => $calendar->id, 'effective_from' => '2026-08-01'], $actor);
        $this->assignProfile($company, $this->flexibleProfile($company), ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => $flex->id, 'effective_from' => '2026-08-01'], $actor);
        $this->assignProfile($company, $this->onCallProfile($company), ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => $call->id, 'effective_from' => '2026-08-01'], $actor);

        $batch = $this->batch($company, $center, '2026-08-03', '2026-08-03');
        $this->generate($actor, $company, $batch);

        $this->assertGenerated($calendar, 'unassigned', 'profile', 'calendar_requires_daily_definition');
        $flexDay = $this->assertGenerated($flex, 'flexible', 'profile');
        $this->assertSame(480, $flexDay->required_minutes);
        $this->assertSame('07:00:00', $flexDay->window_start_local_time);
        $callDay = $this->assertGenerated($call, 'on_call', 'profile');
        $this->assertSame('06:00:00', $callDay->availability_start_local_time);
        $this->assertSame(480, $callDay->max_work_minutes);
        $this->assertGenerated($none, 'unassigned', 'system', 'no_effective_schedule_profile');
    }

    public function test_missing_only_is_idempotent_and_refresh_preserves_ids_and_external_sources(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $actor = $this->userWithCompanyRole($company, RoleKey::RH);
        $template = $this->template($company, 'DAY', '08:00', '16:00');
        $profile = $this->assignWeeklyCompanyProfile($company, $template, '2026-08-01');
        $batch = $this->batch($company, $center, '2026-08-03', '2026-08-05');

        $first = $this->generate($actor, $company, $batch);
        $ids = DailyScheduleAssignment::query()->pluck('id')->all();
        $second = $this->generate($actor, $company, $batch);

        $this->assertSame(7, $first->assignmentsCreated);
        $this->assertSame(0, $second->assignmentsCreated);
        $this->assertSame($ids, DailyScheduleAssignment::query()->pluck('id')->all());

        $manual = DailyScheduleAssignment::query()->whereDate('work_date', '2026-08-04')->firstOrFail();
        $manual->forceFill(['source_type' => 'manual', 'source_reference' => ['manual' => true]])->save();

        app(ReplaceScheduleProfileWeeklyRulesAction::class)->handle($company, $profile, [
            ['day_of_week' => 1, 'day_type' => 'rest'],
            ['day_of_week' => 2, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 3, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 4, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 5, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 6, 'day_type' => 'rest'],
            ['day_of_week' => 7, 'day_type' => 'rest'],
        ]);

        $generated = DailyScheduleAssignment::query()->whereDate('work_date', '2026-08-03')->firstOrFail();
        $refresh = $this->generate($actor, $company, $batch, GenerateDraftScheduleBatchFromProfilesAction::MODE_REFRESH_PROFILE_GENERATED);

        $this->assertSame(6, $refresh->assignmentsRefreshed);
        $this->assertSame($generated->id, DailyScheduleAssignment::query()->whereDate('work_date', '2026-08-03')->value('id'));
        $this->assertSame('rest', DailyScheduleAssignment::query()->whereDate('work_date', '2026-08-03')->value('day_type'));
        $this->assertSame('manual', DailyScheduleAssignment::query()->whereDate('work_date', '2026-08-04')->value('source_type'));
    }

    public function test_refresh_replaces_generated_unassigned_when_profile_appears_and_preserves_system_foreign_generator(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $actor = $this->userWithCompanyRole($company, RoleKey::RH);
        $batch = $this->batch($company, $center, '2026-08-03', '2026-08-03');

        $this->generate($actor, $company, $batch);
        $unassigned = DailyScheduleAssignment::query()->firstOrFail();
        $this->assertSame('system', $unassigned->source_type);

        $this->assignWeeklyCompanyProfile($company, $this->template($company), '2026-08-01');
        $this->generate($actor, $company, $batch, GenerateDraftScheduleBatchFromProfilesAction::MODE_REFRESH_PROFILE_GENERATED);
        $this->assertSame($unassigned->id, DailyScheduleAssignment::query()->firstOrFail()->id);
        $this->assertSame('shift', DailyScheduleAssignment::query()->firstOrFail()->day_type);

        $foreignSystem = DailyScheduleAssignment::query()->firstOrFail();
        $foreignSystem->forceFill(['source_type' => 'system', 'source_reference' => ['generator' => 'other_process']])->save();
        $this->generate($actor, $company, $batch, GenerateDraftScheduleBatchFromProfilesAction::MODE_REFRESH_PROFILE_GENERATED);
        $this->assertSame('other_process', DailyScheduleAssignment::query()->firstOrFail()->source_reference['generator']);
    }

    public function test_invalid_template_generation_is_atomic_and_keeps_existing_days(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $actor = $this->userWithCompanyRole($company, RoleKey::RH);
        $template = $this->template($company);
        $this->assignWeeklyCompanyProfile($company, $template, '2026-08-01');
        $batch = $this->batch($company, $center, '2026-08-03', '2026-08-05');
        $this->generate($actor, $company, $batch);
        $before = app(BuildScheduleBatchSnapshotAction::class)->handle($batch)['canonical_json'];

        $template->forceFill(['status' => 'inactive'])->save();

        $this->assertInvalid(fn () => $this->generate($actor, $company, $batch, GenerateDraftScheduleBatchFromProfilesAction::MODE_REFRESH_PROFILE_GENERATED));
        $this->assertSame(7, DailyScheduleAssignment::query()->count());
        $this->assertSame($before, app(BuildScheduleBatchSnapshotAction::class)->handle($batch)['canonical_json']);
        $this->assertSame($relationship->id, DailyScheduleAssignment::query()->firstOrFail()->employment_relationship_id);
    }

    public function test_source_reference_is_stable_and_snapshot_hash_changes_after_profile_refresh(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $actor = $this->userWithCompanyRole($company, RoleKey::RH);
        $relationship = $this->relationship($company, $center, 'REF', '2026-08-01');
        $template = $this->template($company, 'AA', '08:00', '16:00');
        $profile = $this->assignWeeklyCompanyProfile($company, $template, '2026-08-01');
        $batch = $this->batch($company, $center, '2026-08-03', '2026-08-03');

        $this->generate($actor, $company, $batch);
        $assignment = DailyScheduleAssignment::query()->firstOrFail();
        $reference = $assignment->source_reference;
        $this->assertSame('schedule_profile_generation', $reference['generator']);
        $this->assertArrayNotHasKey('generated_at', $reference);
        $this->assertSame($profile->id, $reference['schedule_profile_id']);
        $firstHash = app(BuildScheduleBatchSnapshotAction::class)->handle($batch)['sha256'];

        app(ReplaceScheduleProfileWeeklyRulesAction::class)->handle($company, $profile, [
            ['day_of_week' => 1, 'day_type' => 'rest'],
            ['day_of_week' => 2, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 3, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 4, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 5, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 6, 'day_type' => 'rest'],
            ['day_of_week' => 7, 'day_type' => 'rest'],
        ]);
        $this->generate($actor, $company, $batch, GenerateDraftScheduleBatchFromProfilesAction::MODE_REFRESH_PROFILE_GENERATED);

        $this->assertNotSame($firstHash, app(BuildScheduleBatchSnapshotAction::class)->handle($batch)['sha256']);
        $this->assertNull($batch->refresh()->snapshot_sha256);
    }

    public function test_daily_schedule_scenario_seeder_is_idempotent_and_creates_only_draft_generation_scenarios(): void
    {
        Artisan::call('db:seed', ['--class' => 'VeraTimeDailyScheduleScenarioSeeder']);
        Artisan::call('db:seed', ['--class' => 'VeraTimeDailyScheduleScenarioSeeder']);

        $companies = Company::query()->whereIn('tax_id', ['VTSP-OFFICE', 'VTSP-CYCLE', 'VTSP-FLEX', 'VTSP-ONCALL', 'VTSP-STORE', 'VTSP-NOPROFILE'])->get();

        $this->assertCount(6, $companies);
        $this->assertSame(6, ScheduleBatch::query()->whereIn('company_id', $companies->pluck('id'))->count());
        $this->assertSame(6, ScheduleBatch::query()->whereIn('company_id', $companies->pluck('id'))->where('status', 'draft')->count());
        $this->assertSame(0, ScheduleBatch::query()->whereIn('company_id', $companies->pluck('id'))->whereNotNull('snapshot_sha256')->count());
        $this->assertGreaterThan(0, DailyScheduleAssignment::query()->where('day_type', 'shift')->count());
        $this->assertGreaterThan(0, DailyScheduleAssignment::query()->where('day_type', 'flexible')->count());
        $this->assertGreaterThan(0, DailyScheduleAssignment::query()->where('day_type', 'on_call')->count());
        $this->assertGreaterThan(0, DailyScheduleAssignment::query()->where('source_type', 'system')->where('day_type', 'unassigned')->count());
        $this->assertTrue(Schema::hasTable('work_days'));
        $this->assertFalse(Schema::hasTable('work_day_calculations'));
        $this->assertFalse(Schema::hasTable('on_call_activations'));
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

    private function assertGenerated(EmploymentRelationship $relationship, string $dayType, string $sourceType, ?string $reason = null): DailyScheduleAssignment
    {
        $assignment = DailyScheduleAssignment::query()->where('employment_relationship_id', $relationship->id)->firstOrFail();
        $this->assertSame($dayType, $assignment->day_type);
        $this->assertSame($sourceType, $assignment->source_type);
        $this->assertSame('schedule_profile_generation', $assignment->source_reference['generator']);
        if ($reason) {
            $this->assertSame($reason, $assignment->source_reference['reason']);
        }

        return $assignment;
    }

    private function companyAndCenter(string $timezone = 'America/Mexico_City'): array
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['status' => 'active', 'timezone' => $timezone]);

        return [$company, $center];
    }

    private function relationshipContext(string $timezone = 'America/Mexico_City'): array
    {
        [$company, $center] = $this->companyAndCenter($timezone);
        $relationship = $this->relationship($company, $center, 'EMP', '2026-08-01');

        return [$company, $center, $relationship];
    }

    private function relationship(Company $company, Center $center, string $code, string $start, ?string $end = null): EmploymentRelationship
    {
        $worker = Worker::factory()->for($company)->create(['employee_code' => $code, 'status' => 'active']);

        return EmploymentRelationship::factory()->for($company)->for($worker)->for($center)->create([
            'started_at' => $start,
            'ended_at' => $end,
            'status' => 'active',
        ]);
    }

    private function batch(Company $company, Center $center, string $start = '2026-08-03', string $end = '2026-08-09'): ScheduleBatch
    {
        return app(CreateScheduleBatchAction::class)->handle($company, $center, [
            'period_start' => $start,
            'period_end' => $end,
            'creation_source' => 'profile',
        ]);
    }

    private function generate(User $actor, Company $company, ScheduleBatch $batch, string $mode = GenerateDraftScheduleBatchFromProfilesAction::MODE_MISSING_ONLY)
    {
        return app(GenerateDraftScheduleBatchFromProfilesAction::class)->handle($actor, $company, $batch, $mode);
    }

    private function template(Company $company, string $code = 'DAY', string $start = '08:00', string $end = '16:00', int $startOffset = 0, int $endOffset = 0): ShiftTemplate
    {
        return app(CreateShiftTemplateAction::class)->handle($company, [
            'code' => $code,
            'name' => 'Turno '.$code,
        ], [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => $start, 'end_local_time' => $end, 'start_day_offset' => $startOffset, 'end_day_offset' => $endOffset, 'sort_order' => 1],
        ]);
    }

    private function templateWithBreaks(Company $company): ShiftTemplate
    {
        return app(CreateShiftTemplateAction::class)->handle($company, [
            'code' => 'SPLIT',
            'name' => 'Turno con descansos',
        ], [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '13:00', 'sort_order' => 1],
            ['segment_type' => 'break', 'timing_mode' => 'fixed', 'start_local_time' => '13:00', 'end_local_time' => '14:00', 'is_paid' => false, 'sort_order' => 2],
            ['segment_type' => 'break', 'timing_mode' => 'duration', 'duration_minutes' => 30, 'is_paid' => false, 'sort_order' => 3],
        ]);
    }

    private function assignWeeklyCompanyProfile(Company $company, ShiftTemplate $template, string $effectiveFrom): ScheduleProfile
    {
        $actor = $this->userWithCompanyRole($company, RoleKey::RH);
        $profile = $this->profile($company, 'WEEKLY'.substr((string) $template->id, -4), 'pattern', 'weekly', $this->weeklyRules($template));
        $this->assignProfile($company, $profile, ['assignment_scope' => 'company', 'effective_from' => $effectiveFrom], $actor);

        return $profile;
    }

    private function profile(Company $company, string $code, string $type, ?string $patternMode = null, array $rules = []): ScheduleProfile
    {
        return app(CreateScheduleProfileAction::class)->handle($company, [
            'code' => $code,
            'name' => 'Perfil '.$code,
            'profile_type' => $type,
            'pattern_mode' => $patternMode,
        ], $rules);
    }

    private function cycleProfile(Company $company, ShiftTemplate $morning, ShiftTemplate $night): ScheduleProfile
    {
        $profile = $this->profile($company, 'CYCLE', 'pattern', 'cycle');
        app(ReplaceScheduleProfileCycleRulesAction::class)->handle($company, $profile, [
            ['cycle_day' => 1, 'day_type' => 'shift', 'shift_template_id' => $morning->id],
            ['cycle_day' => 2, 'day_type' => 'shift', 'shift_template_id' => $night->id],
            ['cycle_day' => 3, 'day_type' => 'rest'],
        ]);

        return $profile;
    }

    private function flexibleProfile(Company $company): ScheduleProfile
    {
        $profile = $this->profile($company, 'FLEX', 'flexible');
        app(ReplaceScheduleProfileFlexibleRulesAction::class)->handle($company, $profile, $this->weeklyRules(null, 'work'));

        return $profile;
    }

    private function onCallProfile(Company $company): ScheduleProfile
    {
        $profile = $this->profile($company, 'CALL', 'on_call');
        app(ReplaceScheduleProfileOnCallRulesAction::class)->handle($company, $profile, $this->weeklyRules(null, 'on_call'));

        return $profile;
    }

    private function weeklyRules(?ShiftTemplate $template, string $mode = 'shift'): array
    {
        $rules = [];
        for ($day = 1; $day <= 7; $day++) {
            if ($day >= 6) {
                $rules[] = ['day_of_week' => $day, 'day_type' => 'rest'];
                continue;
            }

            $rules[] = match ($mode) {
                'work' => ['day_of_week' => $day, 'day_type' => 'work', 'required_minutes' => 480, 'window_start_local_time' => '07:00', 'window_end_local_time' => '20:00'],
                'on_call' => ['day_of_week' => $day, 'day_type' => 'on_call', 'availability_start_local_time' => '06:00', 'availability_end_local_time' => '22:00', 'max_work_minutes' => 480],
                default => ['day_of_week' => $day, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            };
        }

        return $rules;
    }

    private function assignProfile(Company $company, ScheduleProfile $profile, array $data, User $actor): ScheduleProfileAssignment
    {
        return app(AssignScheduleProfileAction::class)->handle($company, $profile, ['source' => 'system'] + $data, $actor);
    }

    private function unit(Company $company, Center $center, string $code): OrganizationalUnit
    {
        return OrganizationalUnit::factory()->for($company)->for($center)->create([
            'code' => $code,
            'name' => 'Unidad '.$code,
            'type' => 'area',
            'status' => 'active',
        ]);
    }

    private function primaryUnit(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, string $from, ?string $to = null): void
    {
        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, [
            'effective_from' => $from,
            'effective_to' => $to,
            'source' => 'system',
        ]);
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

    private function utc(string $workDate, string $time, int $offset, string $timezone): string
    {
        return CarbonImmutable::parse($workDate, $timezone)->addDays($offset)->setTimeFromTimeString($time)->utc()->toDateTimeString();
    }
}
