<?php

namespace Tests\Feature\BlockF1;

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\CreateOrganizationalUnitAction;
use App\Domains\Scheduling\Actions\BuildScheduleBatchSnapshotAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\RemoveDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\ResolveDailyScheduleForRelationshipDateAction;
use App\Domains\Scheduling\Actions\UpdateDraftScheduleBatchAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\ScheduleProfile;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class DailyScheduleCoreDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_f1_schema_creates_daily_schedule_core_without_future_operational_modules(): void
    {
        $this->assertTrue(Schema::hasTable('schedule_batches'));
        $this->assertTrue(Schema::hasTable('daily_schedule_assignments'));
        $this->assertTrue(Schema::hasTable('daily_schedule_segments'));
        $this->assertTrue(Schema::hasTable('work_days'));
        $this->assertTrue(Schema::hasTable('work_day_calculations'));
        $this->assertFalse(Schema::hasTable('on_call_activations'));
    }

    public function test_batches_validate_center_period_open_draft_and_draft_mutability(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $otherCenter = Center::factory()->for($otherCompany)->create(['status' => 'active']);
        $create = app(CreateScheduleBatchAction::class);

        $batch = $create->handle($company, $center, ['period_start' => '2026-08-01', 'period_end' => '2026-08-15']);
        $this->assertSame('2026-07-27', $batch->period_start->toDateString());
        $this->assertSame('2026-08-02', $batch->period_end->toDateString());
        $this->assertNull($batch->version);
        $this->assertSame('draft', $batch->status);

        $this->assertInvalid(fn () => $create->handle($company, $center, ['period_start' => '2026-08-01', 'period_end' => '2026-08-15']));

        $this->assertInvalid(fn () => $create->handle($company, $center, ['period_start' => 'fecha-invalida']));
        $this->assertInvalid(fn () => $create->handle($company, $otherCenter, ['period_start' => '2026-08-01', 'period_end' => '2026-08-15']));

        $updated = app(UpdateDraftScheduleBatchAction::class)->handle($batch, ['notes' => 'Ajuste interno']);
        $this->assertSame('Ajuste interno', $updated->notes);

        $batch->forceFill(['status' => 'published', 'version' => 1])->save();
        $this->assertInvalid(fn () => app(UpdateDraftScheduleBatchAction::class)->handle($batch, ['notes' => 'No permitido']));
    }

    public function test_daily_assignments_support_shift_rest_flexible_on_call_and_unassigned_rules(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $batch = $this->batch($company, $center);
        $template = $this->shiftTemplate($company, 'DAY');

        $shift = $this->replaceDay($company, $batch, $relationship, [
            'work_date' => '2026-08-03',
            'day_type' => 'shift',
            'shift_template_id' => $template->id,
        ], $this->segments($template));
        $this->assertSame('shift', $shift->day_type);
        $this->assertCount(2, $shift->segments);

        $rest = $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-04', 'day_type' => 'rest', 'required_minutes' => 480]);
        $this->assertSame('rest', $rest->day_type);
        $this->assertNull($rest->required_minutes);

        $flexible = $this->replaceDay($company, $batch, $relationship, [
            'work_date' => '2026-08-05',
            'day_type' => 'flexible',
            'required_minutes' => 480,
            'window_start_local_time' => '22:00',
            'window_end_local_time' => '06:00',
            'window_end_day_offset' => 1,
        ]);
        $this->assertSame(480, $flexible->required_minutes);
        $this->assertSame(1, $flexible->window_end_day_offset);

        $onCall = $this->replaceDay($company, $batch, $relationship, [
            'work_date' => '2026-08-06',
            'day_type' => 'on_call',
            'availability_start_local_time' => '06:00',
            'availability_end_local_time' => '22:00',
            'max_work_minutes' => 480,
        ]);
        $this->assertSame('on_call', $onCall->day_type);
        $this->assertNull($onCall->required_minutes);

        $unassigned = $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-07', 'day_type' => 'unassigned']);
        $this->assertSame('unassigned', $unassigned->day_type);
    }

    public function test_daily_assignment_validation_blocks_cross_tenant_incompatible_data_and_invalid_windows(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $otherCenter = Center::factory()->for($otherCompany)->create(['status' => 'active']);
        $otherRelationship = EmploymentRelationship::factory()->forCompany($otherCompany)->create(['center_id' => $otherCenter->id]);
        $otherTemplate = $this->shiftTemplate($otherCompany, 'EXT');
        $batch = $this->batch($company, $center);
        $template = $this->shiftTemplate($company, 'DAY');
        $foreignUnit = OrganizationalUnit::factory()->forCompany($otherCompany)->create(['center_id' => $otherCenter->id]);

        foreach ([
            fn () => $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-09-01', 'day_type' => 'rest']),
            fn () => $this->replaceDay($company, $batch, $otherRelationship, ['work_date' => '2026-08-03', 'day_type' => 'rest']),
            fn () => $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-03', 'day_type' => 'shift', 'shift_template_id' => $otherTemplate->id], $this->segments($template)),
            fn () => $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-03', 'day_type' => 'rest', 'organizational_unit_id' => $foreignUnit->id]),
            fn () => $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-03', 'day_type' => 'shift', 'shift_template_id' => $template->id]),
            fn () => $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-03', 'day_type' => 'flexible', 'required_minutes' => 480, 'window_start_local_time' => '08:00']),
            fn () => $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-03', 'day_type' => 'on_call', 'availability_start_local_time' => '08:00', 'availability_end_local_time' => '08:01', 'availability_end_day_offset' => 1, 'max_work_minutes' => 480]),
        ] as $callback) {
            $this->assertInvalid($callback);
        }
    }

    public function test_shift_segment_validation_supports_fixed_break_duration_break_and_blocks_invalid_segments(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $batch = $this->batch($company, $center);
        $template = $this->shiftTemplate($company, 'NIGHT', '22:00', '06:00', 0, 1);

        $assignment = $this->replaceDay($company, $batch, $relationship, [
            'work_date' => '2026-08-03',
            'day_type' => 'shift',
            'shift_template_id' => $template->id,
        ], [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '22:00', 'end_local_time' => '06:00', 'start_day_offset' => 0, 'end_day_offset' => 1, 'is_paid' => true],
            ['segment_type' => 'break', 'timing_mode' => 'duration', 'duration_minutes' => 30, 'is_paid' => false],
        ]);

        $this->assertSame('22:00:00', (string) $assignment->segments->first()->start_local_time);
        $this->assertSame(30, $assignment->segments->last()->duration_minutes);

        foreach ([
            [['segment_type' => 'work', 'timing_mode' => 'duration', 'duration_minutes' => 480]],
            [['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '08:00']],
            [['segment_type' => 'break', 'timing_mode' => 'duration', 'duration_minutes' => 0]],
            [['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '08:01', 'end_day_offset' => 1]],
        ] as $segments) {
            $this->assertInvalid(fn () => $this->replaceDay($company, $batch, $relationship, [
                'work_date' => '2026-08-04',
                'day_type' => 'shift',
                'shift_template_id' => $template->id,
            ], $segments));
        }
    }

    public function test_replacement_is_atomic_and_remove_only_works_for_draft_batches(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $batch = $this->batch($company, $center);
        $template = $this->shiftTemplate($company, 'DAY');

        $assignment = $this->replaceDay($company, $batch, $relationship, [
            'work_date' => '2026-08-03',
            'day_type' => 'shift',
            'shift_template_id' => $template->id,
        ], $this->segments($template));

        $this->assertInvalid(fn () => $this->replaceDay($company, $batch, $relationship, [
            'work_date' => '2026-08-03',
            'day_type' => 'flexible',
            'required_minutes' => 0,
        ]));
        $this->assertDatabaseHas('daily_schedule_assignments', ['id' => $assignment->id, 'day_type' => 'shift']);
        $this->assertDatabaseCount('daily_schedule_segments', 2);

        app(RemoveDraftDailyScheduleAssignmentAction::class)->handle($assignment);
        $this->assertDatabaseMissing('daily_schedule_assignments', ['id' => $assignment->id]);

        $published = $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-04', 'day_type' => 'rest']);
        $batch->forceFill(['status' => 'published', 'version' => 1])->save();
        $this->assertInvalid(fn () => app(RemoveDraftDailyScheduleAssignmentAction::class)->handle($published));
        $this->assertInvalid(fn () => $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-04', 'day_type' => 'unassigned']));
    }

    public function test_database_unique_blocks_duplicate_day_inside_same_batch(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $batch = $this->batch($company, $center);

        DailyScheduleAssignment::factory()->for($company)->for($batch)->for($relationship)->create([
            'work_date' => '2026-08-03',
            'day_type' => 'rest',
            'timezone' => $center->timezone,
        ]);

        $this->expectException(QueryException::class);
        DailyScheduleAssignment::factory()->for($company)->for($batch)->for($relationship)->create([
            'work_date' => '2026-08-03',
            'day_type' => 'rest',
            'timezone' => $center->timezone,
        ]);
    }

    public function test_snapshot_is_deterministic_hashes_content_and_does_not_persist(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $unit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, ['code' => 'OPS', 'name' => 'Operaciones', 'type' => 'department']);
        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, ['effective_from' => '2026-08-01']);
        $batch = $this->batch($company, $center);
        $template = $this->shiftTemplate($company, 'DAY');

        $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-04', 'day_type' => 'rest', 'organizational_unit_id' => $unit->id]);
        $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-03', 'day_type' => 'shift', 'shift_template_id' => $template->id], $this->segments($template));

        $builder = app(BuildScheduleBatchSnapshotAction::class);
        $first = $builder->handle($batch);
        $second = $builder->handle($batch);

        $this->assertSame($first['canonical_json'], $second['canonical_json']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['sha256']);
        $this->assertStringContainsString('"employee_code"', $first['canonical_json']);
        $this->assertStringContainsString('"segments"', $first['canonical_json']);
        $this->assertNull($batch->refresh()->snapshot_sha256);

        $this->replaceDay($company, $batch, $relationship, [
            'work_date' => '2026-08-03',
            'day_type' => 'flexible',
            'required_minutes' => 300,
        ]);
        $changed = $builder->handle($batch);
        $this->assertNotSame($first['sha256'], $changed['sha256']);
    }

    public function test_resolver_returns_only_published_daily_schedule_without_profile_or_legacy_fallback(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $draft = $this->batch($company, $center);
        $template = $this->shiftTemplate($company, 'DAY');
        $this->replaceDay($company, $draft, $relationship, ['work_date' => '2026-08-03', 'day_type' => 'shift', 'shift_template_id' => $template->id], $this->segments($template));

        $resolver = app(ResolveDailyScheduleForRelationshipDateAction::class);
        $this->assertSame('missing', $resolver->handle($company, $relationship, '2026-08-03')['resolution_status']);

        ScheduleProfile::factory()->for($company)->create(['code' => 'PROFILE', 'profile_type' => 'calendar', 'pattern_mode' => null]);
        $this->assertSame('missing', $resolver->handle($company, $relationship, '2026-08-03')['resolution_status']);

        $snapshot = app(BuildScheduleBatchSnapshotAction::class)->handle($draft);
        $draft->forceFill([
            'version' => 1,
            'status' => 'published',
            'snapshot_schema_version' => $snapshot['schema_version'],
            'snapshot_canonical_json' => $snapshot['canonical_json'],
            'snapshot_sha256' => $snapshot['sha256'],
            'published_at' => now(),
        ])->save();

        $resolved = $resolver->handle($company, $relationship, '2026-08-03');
        $this->assertSame('published', $resolved['resolution_status']);
        $this->assertSame(1, $resolved['batch_version']);
        $this->assertSame($snapshot['sha256'], $resolved['snapshot_sha256']);
        $this->assertSame('shift', $resolved['day_type']);
        $this->assertCount(2, $resolved['segments']);

        $draft->forceFill(['status' => 'superseded'])->save();
        $this->assertSame('missing', $resolver->handle($company, $relationship, '2026-08-03')['resolution_status']);
        $draft->forceFill(['status' => 'cancelled'])->save();
        $this->assertSame('missing', $resolver->handle($company, $relationship, '2026-08-03')['resolution_status']);
    }

    public function test_resolver_returns_flexible_on_call_rest_and_detects_published_conflicts(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $resolver = app(ResolveDailyScheduleForRelationshipDateAction::class);

        $batch = $this->publishedBatchWithDay($company, $center, $relationship, ['work_date' => '2026-08-03', 'day_type' => 'flexible', 'required_minutes' => 480]);
        $this->assertSame('flexible', $resolver->handle($company, $relationship, '2026-08-03')['day_type']);

        $batch->forceFill(['status' => 'draft'])->save();
        $this->replaceDay($company, $batch, $relationship, [
            'work_date' => '2026-08-04',
            'day_type' => 'on_call',
            'availability_start_local_time' => '06:00',
            'availability_end_local_time' => '22:00',
            'max_work_minutes' => 480,
        ]);
        $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-05', 'day_type' => 'rest']);
        $batch->forceFill(['status' => 'published', 'version' => 1])->save();
        $this->assertSame('on_call', $resolver->handle($company, $relationship, '2026-08-04')['day_type']);
        $this->assertSame('rest', $resolver->handle($company, $relationship, '2026-08-05')['day_type']);

        $second = ScheduleBatch::factory()->create([
            'company_id' => $company->id,
            'center_id' => $center->id,
            'period_start' => '2026-08-03',
            'period_end' => '2026-08-09',
            'status' => 'published',
            'version' => 2,
        ]);
        DailyScheduleAssignment::factory()->create([
            'company_id' => $company->id,
            'schedule_batch_id' => $second->id,
            'employment_relationship_id' => $relationship->id,
            'work_date' => '2026-08-03',
            'day_type' => 'rest',
            'timezone' => 'America/Mexico_City',
        ]);
        $second->forceFill(['status' => 'published', 'version' => 2])->save();

        $this->expectException(LogicException::class);
        $resolver->handle($company, $relationship, '2026-08-03');
    }

    public function test_resolver_blocks_cross_tenant_relationship(): void
    {
        [$company] = $this->companyAndCenter();
        [, , $otherRelationship] = $this->relationshipContext();

        $this->assertInvalid(fn () => app(ResolveDailyScheduleForRelationshipDateAction::class)->handle($company, $otherRelationship, '2026-08-03'));
    }

    public function test_policies_allow_company_managers_and_limit_supervisor_to_explicit_scope(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $batch = $this->batch($company, $center);
        $assignment = $this->replaceDay($company, $batch, $relationship, ['work_date' => '2026-08-03', 'day_type' => 'rest']);

        foreach ([RoleKey::ADMIN_EMPRESA, RoleKey::RH_ADMIN] as $role) {
            $user = $this->userWithCompanyRole($company, $role);
            $this->assertTrue(Gate::forUser($user)->allows('create', [ScheduleBatch::class, $company]));
            $this->assertTrue(Gate::forUser($user)->allows('update', $batch));
            $this->assertTrue(Gate::forUser($user)->allows('view', $assignment));
        }

        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);
        $this->assertFalse(Gate::forUser($supervisor)->allows('create', [ScheduleBatch::class, $company]));
        $this->assertFalse(Gate::forUser($supervisor)->allows('view', $assignment));

        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => '2026-08-01'], center: $center);
        $this->assertFalse(Gate::forUser($supervisor)->allows('view', $batch));
        $this->assertFalse(Gate::forUser($supervisor)->allows('view', $assignment));

        $batch->forceFill([
            'status' => 'published',
            'version' => 1,
            'published_by' => $supervisor->id,
            'published_at' => now(),
            'snapshot_schema_version' => 'daily_schedule_batch_v1',
            'snapshot_canonical_json' => '{"demo":true}',
            'snapshot_sha256' => str_repeat('c', 64),
        ])->save();

        $this->assertTrue(Gate::forUser($supervisor)->allows('view', $batch));
        $this->assertTrue(Gate::forUser($supervisor)->allows('view', $assignment));

        $otherCompany = Company::factory()->create(['status' => 'active']);
        $foreignUser = $this->userWithCompanyRole($otherCompany, RoleKey::RH_ADMIN);
        $this->assertFalse(Gate::forUser($foreignUser)->allows('view', $assignment));
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

    private function companyAndCenter(): array
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);

        return [$company, $center];
    }

    private function relationshipContext(): array
    {
        [$company, $center] = $this->companyAndCenter();
        $worker = Worker::factory()->for($company)->create(['status' => 'active']);
        $relationship = EmploymentRelationship::factory()->for($company)->for($worker)->for($center)->create([
            'started_at' => '2026-08-01',
            'ended_at' => null,
            'status' => 'active',
        ]);

        return [$company, $center, $relationship, $worker];
    }

    private function batch(Company $company, Center $center): ScheduleBatch
    {
        return app(CreateScheduleBatchAction::class)->handle($company, $center, [
            'period_start' => '2026-08-03',
            'period_end' => '2026-08-09',
        ]);
    }

    private function publishedBatchWithDay(Company $company, Center $center, EmploymentRelationship $relationship, array $day): ScheduleBatch
    {
        $batch = $this->batch($company, $center);
        $this->replaceDay($company, $batch, $relationship, $day);
        $snapshot = app(BuildScheduleBatchSnapshotAction::class)->handle($batch);
        $batch->forceFill([
            'version' => 1,
            'status' => 'published',
            'snapshot_schema_version' => $snapshot['schema_version'],
            'snapshot_canonical_json' => $snapshot['canonical_json'],
            'snapshot_sha256' => $snapshot['sha256'],
            'published_at' => now(),
        ])->save();

        return $batch->refresh();
    }

    private function replaceDay(Company $company, ScheduleBatch $batch, EmploymentRelationship $relationship, array $day, array $segments = []): DailyScheduleAssignment
    {
        return app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $batch, $relationship, $day, $segments);
    }

    private function shiftTemplate(Company $company, string $code, string $start = '08:00', string $end = '16:00', int $startOffset = 0, int $endOffset = 0): ShiftTemplate
    {
        return app(CreateShiftTemplateAction::class)->handle($company, [
            'code' => $code,
            'name' => 'Turno '.$code,
        ], [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => $start, 'end_local_time' => $end, 'start_day_offset' => $startOffset, 'end_day_offset' => $endOffset, 'sort_order' => 1],
        ]);
    }

    private function segments(ShiftTemplate $template): array
    {
        $workSegment = $template->segments()->first();

        return [
            [
                'segment_type' => 'work',
                'timing_mode' => 'fixed',
                'start_local_time' => $workSegment->start_local_time,
                'end_local_time' => $workSegment->end_local_time,
                'start_day_offset' => $workSegment->start_day_offset,
                'end_day_offset' => $workSegment->end_day_offset,
                'is_paid' => true,
                'shift_template_segment_id' => $workSegment->id,
            ],
            ['segment_type' => 'break', 'timing_mode' => 'duration', 'duration_minutes' => 30, 'is_paid' => false],
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
