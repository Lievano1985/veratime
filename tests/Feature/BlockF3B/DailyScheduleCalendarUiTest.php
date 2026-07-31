<?php

namespace Tests\Feature\BlockF3B;

use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\VeraTimeDailyScheduleScenarioSeeder;
use Database\Seeders\VeraTimePublishedScheduleScenarioSeeder;
use Database\Seeders\VeraTimeScheduleProfileScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DailyScheduleCalendarUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_managers_can_open_daily_scheduling_and_sidebar_entry_is_visible(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        $this->get(route('scheduling.daily'))
            ->assertOk()
            ->assertSee('Programacion semanal')
            ->assertSee('Arma o ajusta la semana lunes-domingo')
            ->assertSee('Nueva semana');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Programacion semanal');
    }

    public function test_daily_batch_list_is_filtered_by_active_company_and_translates_enums(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->assertSee('Oficinas Corporativas')
            ->assertDontSee('Demo Ciclo Rotativo')
            ->assertSee('Borrador')
            ->set('filters.status', 'all')
            ->set('filters.worker_search', 'OFF-001')
            ->assertSee('Oficinas Corporativas');
    }

    public function test_initial_batch_filter_shows_drafts_and_published_with_drafts_first(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $draft = $this->firstBatch($company);

        $published = $draft->replicate(['snapshot_canonical_json', 'snapshot_sha256', 'published_at']);
        $published->period_start = '2026-07-01';
        $published->period_end = '2026-07-07';
        $published->version = 1;
        $published->status = 'published';
        $published->published_by = $rh->id;
        $published->published_at = now();
        $published->snapshot_schema_version = 'daily_schedule_batch_v1';
        $published->snapshot_canonical_json = '{"demo":true}';
        $published->snapshot_sha256 = str_repeat('a', 64);
        $published->save();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->assertSet('filters.status', 'active_work')
            ->assertSeeInOrder(['2026-08-03 - 2026-08-09', '2026-07-01 - 2026-07-07']);
    }

    public function test_it_creates_empty_batch_and_creates_batch_generated_from_profiles(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $center = $company->centers()->firstOrFail();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('openCreatePanel')
            ->set('batchForm.center_id', (string) $center->id)
            ->set('batchForm.period_start', '2026-09-01')
            ->set('batchForm.period_end', '2026-09-07')
            ->call('createEmptyBatch')
            ->assertHasNoErrors()
            ->assertSee('Lote creado en borrador.');

        $this->assertTrue(ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->whereDate('period_start', '2026-08-31')
            ->whereDate('period_end', '2026-09-06')
            ->where('status', 'draft')
            ->where('creation_source', 'manual')
            ->exists());

        Volt::test('scheduling.daily')
            ->call('openCreatePanel')
            ->set('batchForm.center_id', (string) $center->id)
            ->set('batchForm.period_start', '2026-09-08')
            ->set('batchForm.period_end', '2026-09-14')
            ->call('createAndGenerate')
            ->assertHasNoErrors()
            ->assertSee('Generacion lista');

        $batch = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', '2026-09-07')
            ->firstOrFail();

        $this->assertGreaterThan(0, $batch->dailyAssignments()->count());
    }

    public function test_managers_can_prepare_next_week_from_selected_batch(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $current = $this->firstBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('selectBatch', $current->id)
            ->assertSee('Generar semana siguiente')
            ->call('prepareNextWeek')
            ->assertHasNoErrors()
            ->assertSee('Semana siguiente preparada')
            ->assertSet('weekStart', CarbonImmutable::parse($current->period_end)->addDay()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->toDateString());

        $expectedStart = CarbonImmutable::parse($current->period_end)->addDay()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->toDateString();
        $expectedEnd = CarbonImmutable::parse($expectedStart)->addDays(6)->toDateString();

        $next = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('center_id', $current->center_id)
            ->whereDate('period_start', $expectedStart)
            ->whereDate('period_end', $expectedEnd)
            ->where('status', 'draft')
            ->where('creation_source', 'profile')
            ->firstOrFail();

        $this->assertGreaterThan(0, $next->dailyAssignments()->count());
    }

    public function test_period_and_tenant_validation_blocks_invalid_creation(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $foreignCenter = Company::query()->where('tax_id', 'VTSP-CYCLE')->firstOrFail()->centers()->firstOrFail();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('openCreatePanel')
            ->set('batchForm.center_id', (string) $foreignCenter->id)
            ->set('batchForm.period_start', '2026-09-15')
            ->set('batchForm.period_end', '2026-09-14')
            ->call('createEmptyBatch')
            ->assertHasErrors(['batchForm.center_id']);
    }

    public function test_next_week_keeps_full_week_sequence_even_after_period_end(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $batch = $this->firstBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('selectBatch', $batch->id)
            ->call('nextWeek')
            ->assertSet('weekStart', '2026-08-10')
            ->call('nextWeek')
            ->assertSet('weekStart', '2026-08-17')
            ->assertSee('Lun. 17/08')
            ->assertSee('Dom. 23/08')
            ->assertSee('Fuera de vigencia');
    }

    public function test_calendar_starts_on_monday_even_when_batch_period_starts_midweek(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $center = $company->centers()->firstOrFail();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('openCreatePanel')
            ->set('batchForm.center_id', (string) $center->id)
            ->set('batchForm.period_start', '2026-07-29')
            ->set('batchForm.period_end', '2026-08-04')
            ->call('createAndGenerate')
            ->assertHasNoErrors();

        $batch = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', '2026-07-27')
            ->whereDate('period_end', '2026-08-02')
            ->firstOrFail();

        Volt::test('scheduling.daily')
            ->call('selectBatch', $batch->id)
            ->assertSet('weekStart', '2026-07-27')
            ->assertSee('Lun. 27/07')
            ->assertSee('Mar. 28/07')
            ->assertSee('Dom. 02/08')
            ->assertDontSee('Mar. 04/08')
            ->assertSee('Fuera de vigencia');
    }

    public function test_calendar_shows_day_types_and_manual_edit_preserves_profile_generated_days(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $batch = $this->firstBatch($company);
        $relationship = $this->firstRelationship($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('selectBatch', $batch->id)
            ->assertSee('Oficina Demo Ana')
            ->assertSee('Turno')
            ->assertSeeHtml('table-fixed')
            ->assertSeeHtml('border-sky-200')
            ->call('openDayEditor', $relationship->id, '2026-08-03')
            ->set('dayForm.day_type', 'rest')
            ->set('dayForm.reason', 'Cambio manual para prueba UI')
            ->call('saveDay')
            ->assertHasNoErrors()
            ->assertSeeHtml('border-emerald-200')
            ->call('refreshGenerated')
            ->assertSee('preservados');

        $assignment = DailyScheduleAssignment::query()
            ->where('schedule_batch_id', $batch->id)
            ->where('employment_relationship_id', $relationship->id)
            ->whereDate('work_date', '2026-08-03')
            ->firstOrFail();

        $this->assertSame('rest', $assignment->day_type);
        $this->assertSame('manual', $assignment->source_type);
    }

    public function test_bulk_change_is_transactional_and_requires_confirmation(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $batch = $this->firstBatch($company);
        $relationships = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('center_id', $batch->center_id)
            ->where('status', 'active')
            ->limit(2)
            ->pluck('id')
            ->all();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('selectBatch', $batch->id)
            ->call('openBulkPanel')
            ->set('bulkForm.employment_relationship_ids', $relationships)
            ->set('bulkForm.date_from', '2026-08-04')
            ->set('bulkForm.date_to', '2026-08-05')
            ->set('bulkForm.day_type', 'rest')
            ->set('bulkForm.reason', 'Cambio masivo justificado')
            ->call('applyBulk')
            ->assertHasErrors(['confirmBulk'])
            ->set('confirmBulk', true)
            ->call('applyBulk')
            ->assertHasNoErrors()
            ->assertSee('Cambio masivo aplicado a 4 dias.');

        $this->assertSame(4, DailyScheduleAssignment::query()
            ->where('schedule_batch_id', $batch->id)
            ->whereIn('employment_relationship_id', $relationships)
            ->where(function ($query): void {
                $query->whereDate('work_date', '2026-08-04')
                    ->orWhereDate('work_date', '2026-08-05');
            })
            ->where('day_type', 'rest')
            ->where('source_type', 'manual')
            ->count());
    }

    public function test_review_publish_and_integrity_verification_work_from_ui(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $batch = $this->firstBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('selectBatch', $batch->id)
            ->call('reviewBatch')
            ->assertSee('Listo para publicar')
            ->call('publishBatch')
            ->assertHasErrors(['confirmPublish']);

        Volt::test('scheduling.daily')
            ->call('selectBatch', $batch->id)
            ->set('confirmPublish', true)
            ->call('publishBatch')
            ->assertHasNoErrors()
            ->assertSee('Programacion publicada')
            ->call('verifyIntegrity')
            ->assertSee('Integridad verificada');

        $batch->refresh();
        $this->assertSame('published', $batch->status);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $batch->snapshot_sha256);
    }

    public function test_unassigned_batch_cannot_be_published(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->firstBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('selectBatch', $batch->id)
            ->call('reviewBatch')
            ->assertSee('Bloqueos para publicar')
            ->set('confirmPublish', true)
            ->call('publishBatch')
            ->assertHasErrors(['publication']);

        $this->assertSame('draft', $batch->refresh()->status);
    }

    public function test_managers_can_discard_draft_batches(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $draft = $this->firstBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('selectBatch', $draft->id)
            ->assertSee('Descartar borrador')
            ->call('openCancelDraftPanel')
            ->call('cancelDraftBatch')
            ->assertHasErrors(['cancelDraftForm.reason'])
            ->set('cancelDraftForm.reason', 'Lote creado por error durante prueba manual')
            ->call('cancelDraftBatch')
            ->assertHasNoErrors()
            ->assertSee('Borrador descartado')
            ->assertSet('selectedBatchId', null);

        $draft->refresh();
        $this->assertSame('cancelled', $draft->status);
        $this->assertSame($rh->id, $draft->cancelled_by);
        $this->assertSame('Lote creado por error durante prueba manual', $draft->cancellation_reason);

    }

    public function test_published_batches_are_not_discardable_from_ui(): void
    {
        $this->seedPublishedScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $published = $this->firstBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->set('filters.status', 'published')
            ->call('selectBatch', $published->id)
            ->call('openCancelDraftPanel')
            ->assertForbidden();

        $this->assertSame('published', $published->refresh()->status);
    }

    public function test_managers_can_permanently_delete_cancelled_batches_from_ui(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $cancelled = $this->firstBatch($company);
        $cancelled->forceFill([
            'status' => 'cancelled',
            'cancelled_by' => $rh->id,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Descartado previamente',
        ])->save();
        $assignmentIds = $cancelled->dailyAssignments()->pluck('id')->all();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->set('filters.status', 'cancelled')
            ->call('selectBatch', $cancelled->id)
            ->assertSee('Lote cancelado')
            ->assertSee('Descartado previamente')
            ->assertSee('Eliminar definitivo')
            ->call('deleteCancelledBatch')
            ->assertHasNoErrors()
            ->assertSee('Lote cancelado eliminado definitivamente.')
            ->assertSet('selectedBatchId', null);

        $this->assertDatabaseMissing('schedule_batches', ['id' => $cancelled->id]);
        foreach ($assignmentIds as $assignmentId) {
            $this->assertDatabaseMissing('daily_schedule_assignments', ['id' => $assignmentId]);
            $this->assertDatabaseMissing('daily_schedule_segments', ['daily_schedule_assignment_id' => $assignmentId]);
        }
    }

    public function test_published_batches_are_read_only_and_snapshot_tampering_is_detected(): void
    {
        $this->seedPublishedScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $batch = $this->firstBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->set('filters.status', 'published')
            ->call('selectBatch', $batch->id)
            ->assertSee('Publicado')
            ->assertDontSee('Generar faltantes')
            ->call('verifyIntegrity')
            ->assertSee('Integridad verificada');

        $batch->forceFill(['snapshot_canonical_json' => '{"tampered":true}'])->save();

        Volt::test('scheduling.daily')
            ->set('filters.status', 'published')
            ->call('selectBatch', $batch->id)
            ->call('verifyIntegrity')
            ->assertSee('No fue posible verificar la integridad');
    }

    public function test_published_calendar_keeps_terminated_workers_from_historical_schedule(): void
    {
        $this->seedPublishedScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $published = $this->firstBatch($company);
        $expectedWorkers = $published->dailyAssignments()
            ->distinct('employment_relationship_id')
            ->count('employment_relationship_id');
        $relationship = $published->dailyAssignments()
            ->with('employmentRelationship.worker')
            ->orderBy('employment_relationship_id')
            ->firstOrFail()
            ->employmentRelationship;
        $workerCode = $relationship->worker->employee_code;

        $relationship->worker->forceFill(['status' => 'terminated'])->save();
        $relationship->forceFill([
            'status' => 'ended',
            'ended_at' => '2026-07-01',
        ])->save();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->set('filters.status', 'published')
            ->call('selectBatch', $published->id)
            ->assertSee("{$expectedWorkers} trabajadores")
            ->assertSee("de {$expectedWorkers} trabajadores")
            ->assertSee($workerCode);
    }

    public function test_supervisor_scope_is_read_only_and_other_company_is_blocked(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-03 09:00:00'));

        Artisan::call('db:seed', ['--class' => VeraTimeScheduleProfileScenarioSeeder::class]);
        [$company, $supervisor] = $this->companyAndUser('VTSP-CONSTRUCT', 'supervisor.construction.demo@veratime.local');
        $center = $company->centers()->where('name', 'like', '%Obra%')->firstOrFail();
        $batch = app(CreateScheduleBatchAction::class)->handle($company, $center, [
            'period_start' => '2026-08-03',
            'period_end' => '2026-08-09',
            'creation_source' => 'manual',
        ], $supervisor);
        $relationship = EmploymentRelationship::query()->where('company_id', $company->id)->where('center_id', $center->id)->where('status', 'active')->firstOrFail();
        app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $batch, $relationship, [
            'work_date' => '2026-08-03',
            'day_type' => 'unassigned',
            'timezone' => $center->timezone,
            'source_type' => 'manual',
            'source_reference' => ['schema_version' => 1, 'reason' => 'Supervisor scope fixture'],
        ]);

        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id]);

        $this->get(route('scheduling.daily'))
            ->assertOk()
            ->assertSee('Demo Constructora con Herencia')
            ->assertDontSee('Generar faltantes');

        [$otherCompany] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $this->assertFalse($supervisor->can('publish', $batch));
        $this->assertFalse($supervisor->can('viewAny', [ScheduleBatch::class, $otherCompany]));
    }

    public function test_f3b_does_not_create_future_operational_tables(): void
    {
        $this->assertFalse(Schema::hasTable('work_days'));
        $this->assertFalse(Schema::hasTable('work_day_calculations'));
        $this->assertFalse(Schema::hasTable('alerts'));
        $this->assertFalse(Schema::hasTable('incidents'));
    }

    private function seedDailyScenarios(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimeDailyScheduleScenarioSeeder::class]);
    }

    private function seedPublishedScenarios(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimePublishedScheduleScenarioSeeder::class]);
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyAndUser(string $taxId, string $email): array
    {
        $company = Company::query()->where('tax_id', $taxId)->firstOrFail();
        $user = User::query()->where('email', $email)->firstOrFail();

        return [$company, $user];
    }

    private function firstBatch(Company $company): ScheduleBatch
    {
        return ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('status', 'draft')
            ->whereNull('previous_batch_id')
            ->orderBy('period_start')
            ->firstOrFail();
    }

    private function firstRelationship(Company $company): EmploymentRelationship
    {
        return EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->firstOrFail();
    }
}
