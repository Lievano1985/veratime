<?php

namespace Tests\Feature\BlockF3A;

use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\GenerateDraftScheduleBatchFromProfilesAction;
use App\Domains\Scheduling\Actions\PublishScheduleBatchAction;
use App\Domains\Scheduling\Actions\RemoveDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\ResolveDailyScheduleForRelationshipDateAction;
use App\Domains\Scheduling\Actions\UpdateDraftScheduleBatchAction;
use App\Domains\Scheduling\Actions\ValidateScheduleBatchForPublicationAction;
use App\Domains\Scheduling\Actions\VerifyPublishedScheduleBatchSnapshotAction;
use App\Domains\Scheduling\Exceptions\ScheduleBatchPublicationValidationException;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ScheduleBatchPublicationDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_and_rh_can_publish_while_unauthorized_users_are_blocked(): void
    {
        $this->seedDailyScenarios();

        $this->assertSame('published', $this->publishScenario('VTSP-OFFICE', 'admin.office.demo@veratime.local')->status);
        $this->assertSame('published', $this->publishScenario('VTSP-CYCLE', 'admin.cycle.demo@veratime.local')->status);
        $this->assertSame('published', $this->publishScenario('VTSP-FLEX', 'rh.flex.demo@veratime.local')->status);

        $company = $this->company('VTSP-ONCALL');
        $batch = $this->batch($company);
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);
        $this->assertFalse($supervisor->can('publish', $batch));
        $this->assertInvalid(fn () => app(PublishScheduleBatchAction::class)->handle($supervisor, $company, $batch));

        $foreign = $this->user('rh.store.demo@veratime.local');
        $this->assertInvalid(fn () => app(PublishScheduleBatchAction::class)->handle($foreign, $company, $batch));

        $rh = $this->user('rh.oncall.demo@veratime.local');
        $rh->companies()->updateExistingPivot($company->id, ['status' => 'inactive']);
        $this->assertInvalid(fn () => app(PublishScheduleBatchAction::class)->handle($rh->refresh(), $company, $batch));

        $rh->companies()->updateExistingPivot($company->id, ['status' => 'active']);
        $company->forceFill(['status' => 'inactive'])->save();
        $this->assertFalse($rh->refresh()->can('publish', $batch->refresh()));
        $this->assertInvalid(fn () => app(PublishScheduleBatchAction::class)->handle($rh->refresh(), $company->refresh(), $batch->refresh()));
    }

    public function test_valid_draft_publishes_snapshot_and_resolver_returns_published_schedule(): void
    {
        $this->seedDailyScenarios();
        $company = $this->company('VTSP-OFFICE');
        $batch = $this->batch($company);
        $actor = $this->user('rh.office.demo@veratime.local');
        $assignment = $batch->dailyAssignments()->with('employmentRelationship')->where('day_type', 'shift')->firstOrFail();

        $missing = app(ResolveDailyScheduleForRelationshipDateAction::class)->handle($company, $assignment->employmentRelationship, $assignment->work_date->toDateString());
        $this->assertSame('missing', $missing['resolution_status']);

        $result = app(PublishScheduleBatchAction::class)->handle($actor, $company, $batch);

        $this->assertSame('published', $result->scheduleBatch->status);
        $this->assertSame($actor->id, $result->publishedBy);
        $this->assertNotNull($result->publishedAt);
        $this->assertSame(\App\Domains\Scheduling\Actions\BuildScheduleBatchSnapshotAction::SCHEMA_VERSION, $result->snapshotSchemaVersion);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result->snapshotSha256);
        $this->assertSame(21, $result->assignmentCount);
        $this->assertGreaterThan(0, $result->segmentCount);
        $this->assertSame(['shift' => 15, 'rest' => 6, 'flexible' => 0, 'on_call' => 0], $result->countsByDayType);

        $verification = app(VerifyPublishedScheduleBatchSnapshotAction::class)->handle($company, $result->scheduleBatch);
        $this->assertTrue($verification->valid);

        $published = app(ResolveDailyScheduleForRelationshipDateAction::class)->handle($company, $assignment->employmentRelationship, $assignment->work_date->toDateString());
        $this->assertSame('published', $published['resolution_status']);
        $this->assertSame(1, $published['batch_version']);
        $this->assertSame($result->snapshotSha256, $published['snapshot_sha256']);
        $this->assertSame('shift', $published['day_type']);
        $this->assertGreaterThan(0, $published['segments']->count());
    }

    public function test_publication_requires_initial_draft_complete_and_without_unassigned_days(): void
    {
        $this->seedDailyScenarios();
        $company = $this->company('VTSP-STORE');
        $actor = $this->user('rh.store.demo@veratime.local');
        $batch = $this->batch($company);

        $validation = app(ValidateScheduleBatchForPublicationAction::class)->handle($actor, $company, $batch);
        $this->assertFalse($validation->valid());
        $this->assertGreaterThan(0, $validation->assignmentsUnassigned);
        $this->assertInvalid(fn () => app(PublishScheduleBatchAction::class)->handle($actor, $company, $batch));
        $this->assertSame('draft', $batch->refresh()->status);
        $this->assertNull($batch->snapshot_sha256);

        $office = $this->company('VTSP-OFFICE');
        $officeBatch = $this->batch($office);
        $officeActor = $this->user('rh.office.demo@veratime.local');
        $assignment = $officeBatch->dailyAssignments()->firstOrFail();
        $assignment->delete();
        $missing = app(ValidateScheduleBatchForPublicationAction::class)->handle($officeActor, $office, $officeBatch);
        $this->assertFalse($missing->valid());
        $this->assertSame(1, $missing->assignmentsMissing);

        $cycle = $this->batch($this->company('VTSP-CYCLE'));
        $flex = $this->batch($this->company('VTSP-FLEX'));
        $flex->forceFill(['previous_batch_id' => $cycle->id])->save();
        $this->assertInvalid(fn () => app(PublishScheduleBatchAction::class)->handle($this->user('rh.flex.demo@veratime.local'), $flex->company, $flex->refresh()));
    }

    public function test_shift_flexible_on_call_and_rest_validation_blocks_incompatible_shapes(): void
    {
        $this->seedDailyScenarios();

        $office = $this->company('VTSP-OFFICE');
        $officeBatch = $this->batch($office);
        $segment = $officeBatch->dailyAssignments()->where('day_type', 'shift')->firstOrFail()->segments()->firstOrFail();
        $segment->forceFill(['starts_at_utc' => null])->save();
        $this->assertPublicationInvalid($office, $officeBatch, 'rh.office.demo@veratime.local', 'Un segmento de turno no contiene instantes UTC completos.');

        $cycle = $this->company('VTSP-CYCLE');
        $cycleBatch = $this->batch($cycle);
        $nightSegment = $cycleBatch->dailyAssignments()->where('day_type', 'shift')->whereHas('segments', fn ($query) => $query->where('end_day_offset', 1))->firstOrFail()->segments()->firstOrFail();
        $nightSegment->forceFill(['ends_at_utc' => $nightSegment->ends_at_utc->addHour()])->save();
        $this->assertPublicationInvalid($cycle, $cycleBatch, 'rh.cycle.demo@veratime.local', 'Los instantes UTC del segmento no coinciden con la hora local congelada.');

        $flex = $this->company('VTSP-FLEX');
        $flexBatch = $this->batch($flex);
        $flexBatch->dailyAssignments()->where('day_type', 'flexible')->firstOrFail()->forceFill(['required_minutes' => 0])->save();
        $this->assertPublicationInvalid($flex, $flexBatch, 'rh.flex.demo@veratime.local', 'Un dia flexible requiere minutos entre 1 y 1440.');

        $onCall = $this->company('VTSP-ONCALL');
        $onCallBatch = $this->batch($onCall);
        $onCallBatch->dailyAssignments()->where('day_type', 'on_call')->firstOrFail()->forceFill(['availability_start_local_time' => null])->save();
        $this->assertPublicationInvalid($onCall, $onCallBatch, 'rh.oncall.demo@veratime.local', 'Un dia bajo demanda requiere disponibilidad inicial y final.');
    }

    public function test_conflicting_published_assignment_blocks_second_batch_and_preserves_draft(): void
    {
        $this->seedDailyScenarios();
        $company = $this->company('VTSP-OFFICE');
        $actor = $this->user('rh.office.demo@veratime.local');
        $batch = $this->batch($company);
        app(PublishScheduleBatchAction::class)->handle($actor, $company, $batch);

        $second = app(CreateScheduleBatchAction::class)->handle($company, $batch->center, [
            'period_start' => '2026-08-03',
            'period_end' => '2026-08-03',
            'creation_source' => 'profile',
        ], $actor);
        app(GenerateDraftScheduleBatchFromProfilesAction::class)->handle($actor, $company, $second);

        $this->assertPublicationInvalid($company, $second, 'rh.office.demo@veratime.local', 'Ya existe programacion publicada para una persona y fecha.');
        $this->assertSame('draft', $second->refresh()->status);
        $this->assertNull($second->snapshot_sha256);
        $this->assertSame(1, ScheduleBatch::query()->where('company_id', $company->id)->where('status', 'published')->count());
    }

    public function test_snapshot_verification_uses_persisted_json_and_detects_tampering(): void
    {
        $this->seedDailyScenarios();
        $company = $this->company('VTSP-CYCLE');
        $batch = $this->batch($company);
        $actor = $this->user('rh.cycle.demo@veratime.local');
        $published = app(PublishScheduleBatchAction::class)->handle($actor, $company, $batch)->scheduleBatch;
        $json = $published->snapshot_canonical_json;
        $hash = $published->snapshot_sha256;

        $published->dailyAssignments()->firstOrFail()->shiftTemplate->forceFill(['name' => 'Nombre cambiado despues de publicar'])->save();
        $this->assertSame($json, $published->refresh()->snapshot_canonical_json);
        $this->assertSame($hash, $published->snapshot_sha256);
        $this->assertTrue(app(VerifyPublishedScheduleBatchSnapshotAction::class)->handle($company, $published)->valid);

        $published->forceFill(['snapshot_canonical_json' => str_replace('Ciclo', 'Manipulado', $json)])->save();
        $this->assertFalse(app(VerifyPublishedScheduleBatchSnapshotAction::class)->handle($company, $published->refresh())->valid);

        $published->forceFill(['snapshot_canonical_json' => $json, 'snapshot_sha256' => str_repeat('a', 64)])->save();
        $this->assertFalse(app(VerifyPublishedScheduleBatchSnapshotAction::class)->handle($company, $published->refresh())->valid);
    }

    public function test_published_batches_are_immutable_through_draft_actions(): void
    {
        $this->seedDailyScenarios();
        $company = $this->company('VTSP-OFFICE');
        $batch = $this->batch($company);
        $actor = $this->user('rh.office.demo@veratime.local');
        $published = app(PublishScheduleBatchAction::class)->handle($actor, $company, $batch)->scheduleBatch;
        $assignment = $published->dailyAssignments()->with('segments', 'employmentRelationship')->where('day_type', 'shift')->firstOrFail();

        $this->assertInvalid(fn () => app(UpdateDraftScheduleBatchAction::class)->handle($published, ['notes' => 'No permitido']));
        $this->assertInvalid(fn () => app(GenerateDraftScheduleBatchFromProfilesAction::class)->handle($actor, $company, $published));
        $this->assertInvalid(fn () => app(RemoveDraftDailyScheduleAssignmentAction::class)->handle($assignment));
        $this->assertInvalid(fn () => app(PublishScheduleBatchAction::class)->handle($actor, $company, $published));
        $this->assertInvalid(fn () => app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle(
            $company,
            $published,
            $assignment->employmentRelationship,
            $assignment->only(['work_date', 'day_type', 'timezone', 'source_type', 'source_reference', 'shift_template_id']),
            $assignment->segments->map(fn (DailyScheduleSegment $segment): array => $segment->toArray())->all(),
        ));

        $this->assertSame('published', $published->refresh()->status);
        $this->assertSame($assignment->segments()->count(), $assignment->refresh()->segments()->count());
    }

    public function test_published_schedule_scenario_seeder_is_idempotent_and_keeps_invalid_scenarios_draft(): void
    {
        Artisan::call('db:seed', ['--class' => 'VeraTimePublishedScheduleScenarioSeeder']);
        $firstHashes = ScheduleBatch::query()->where('status', 'published')->pluck('snapshot_sha256', 'id')->all();

        Artisan::call('db:seed', ['--class' => 'VeraTimePublishedScheduleScenarioSeeder']);
        $secondHashes = ScheduleBatch::query()->where('status', 'published')->pluck('snapshot_sha256', 'id')->all();

        $publishableCompanies = Company::query()->whereIn('tax_id', ['VTSP-OFFICE', 'VTSP-CYCLE', 'VTSP-FLEX', 'VTSP-ONCALL'])->pluck('id');
        $draftCompanies = Company::query()->whereIn('tax_id', ['VTSP-STORE', 'VTSP-NOPROFILE'])->pluck('id');

        $this->assertSame($firstHashes, $secondHashes);
        $this->assertSame(4, ScheduleBatch::query()->whereIn('company_id', $publishableCompanies)->where('status', 'published')->count());
        $this->assertSame(2, ScheduleBatch::query()->whereIn('company_id', $draftCompanies)->where('status', 'draft')->count());
        $this->assertSame(0, ScheduleBatch::query()->where('version', 2)->count());
        $this->assertTrue(Schema::hasTable('work_days'));
        $this->assertTrue(Schema::hasTable('work_day_calculations'));
        $this->assertFalse(Schema::hasTable('on_call_activations'));
    }

    private function seedDailyScenarios(): void
    {
        Artisan::call('db:seed', ['--class' => 'VeraTimeDailyScheduleScenarioSeeder']);
    }

    private function publishScenario(string $taxId, string $email): ScheduleBatch
    {
        $company = $this->company($taxId);

        return app(PublishScheduleBatchAction::class)
            ->handle($this->user($email), $company, $this->batch($company))
            ->scheduleBatch;
    }

    private function assertPublicationInvalid(Company $company, ScheduleBatch $batch, string $email, string $expectedError): void
    {
        try {
            app(PublishScheduleBatchAction::class)->handle($this->user($email), $company, $batch);
            $this->fail('Publicacion invalida aceptada.');
        } catch (ScheduleBatchPublicationValidationException $exception) {
            $this->assertContains($expectedError, $exception->result->errors);
        }
    }

    private function assertInvalid(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Operacion invalida aceptada.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    private function company(string $taxId): Company
    {
        return Company::query()->where('tax_id', $taxId)->firstOrFail();
    }

    private function batch(Company $company): ScheduleBatch
    {
        return ScheduleBatch::query()
            ->with(['company', 'center', 'dailyAssignments.segments'])
            ->where('company_id', $company->id)
            ->whereDate('period_start', '2026-08-03')
            ->whereDate('period_end', '2026-08-09')
            ->whereNull('version')
            ->where('status', 'draft')
            ->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
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
