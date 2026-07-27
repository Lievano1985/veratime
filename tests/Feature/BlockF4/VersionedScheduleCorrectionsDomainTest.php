<?php

namespace Tests\Feature\BlockF4;

use App\Domains\Scheduling\Actions\CompareScheduleBatchVersionsAction;
use App\Domains\Scheduling\Actions\CreateCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\GenerateDraftScheduleBatchFromProfilesAction;
use App\Domains\Scheduling\Actions\PublishCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\ResolveDailyScheduleForRelationshipDateAction;
use App\Domains\Scheduling\Actions\ValidateCorrectiveScheduleBatchForPublicationAction;
use App\Domains\Scheduling\Actions\VerifyPublishedScheduleBatchSnapshotAction;
use App\Domains\Scheduling\Exceptions\ScheduleCorrectionAlreadyExistsException;
use App\Domains\Scheduling\Exceptions\ScheduleCorrectionHasNoChangesException;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\ScheduleBatch;
use App\Models\User;
use Database\Seeders\VeraTimeCorrectedScheduleScenarioSeeder;
use Database\Seeders\VeraTimePublishedScheduleScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VersionedScheduleCorrectionsDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_creates_correction_and_supervisor_is_blocked(): void
    {
        $this->seedPublishedScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $published = $this->publishedBatch($company);

        $result = app(CreateCorrectiveScheduleBatchAction::class)->handle($rh, $company, $published, 'Ajuste operativo validado.');

        $this->assertNull($result->correctiveBatch->version);
        $this->assertSame($published->id, $result->correctiveBatch->previous_batch_id);
        $this->assertSame('mixed', $result->correctiveBatch->creation_source);
        $this->assertSame($published->dailyAssignments()->count(), $result->correctiveBatch->dailyAssignments()->count());

        [$cycle] = $this->companyAndUser('VTSP-CYCLE', 'rh.cycle.demo@veratime.local');
        [, $supervisor] = $this->companyAndUser('VTSP-CONSTRUCT', 'supervisor.construction.demo@veratime.local');
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(CreateCorrectiveScheduleBatchAction::class)->handle($supervisor, $cycle, $this->publishedBatch($cycle), 'Intento supervisor.');
    }

    public function test_parallel_correction_and_noop_publication_are_blocked(): void
    {
        $this->seedPublishedScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $published = $this->publishedBatch($company);

        $draft = app(CreateCorrectiveScheduleBatchAction::class)->handle($rh, $company, $published, 'Primer borrador correctivo.')->correctiveBatch;

        try {
            app(CreateCorrectiveScheduleBatchAction::class)->handle($rh, $company, $published, 'Segundo borrador.');
            $this->fail('La segunda correccion paralela debio bloquearse.');
        } catch (ScheduleCorrectionAlreadyExistsException) {
            $this->assertTrue(true);
        }

        try {
            app(PublishCorrectiveScheduleBatchAction::class)->handle($rh, $company, $draft);
            $this->fail('La correccion sin cambios debio bloquearse.');
        } catch (ScheduleCorrectionHasNoChangesException) {
            $this->assertSame('published', $published->fresh()->status);
            $this->assertSame('draft', $draft->fresh()->status);
        }
    }

    public function test_superseded_batch_cannot_start_a_new_correction(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimeCorrectedScheduleScenarioSeeder::class]);
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $superseded = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('version', 1)
            ->where('status', 'superseded')
            ->firstOrFail();

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(CreateCorrectiveScheduleBatchAction::class)->handle($rh, $company, $superseded, 'Intento desde version sustituida.');
    }

    public function test_user_from_another_company_cannot_create_correction(): void
    {
        $this->seedPublishedScenarios();
        [$company] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        [, $foreignRh] = $this->companyAndUser('VTSP-CYCLE', 'rh.cycle.demo@veratime.local');
        $published = $this->publishedBatch($company);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(CreateCorrectiveScheduleBatchAction::class)->handle($foreignRh, $company, $published, 'Intento desde otra empresa.');
    }

    public function test_corrective_draft_cannot_regenerate_from_profiles(): void
    {
        $this->seedPublishedScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $draft = app(CreateCorrectiveScheduleBatchAction::class)
            ->handle($rh, $company, $this->publishedBatch($company), 'Correccion para validar bloqueo de regeneracion.')
            ->correctiveBatch;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Una correccion versionada no puede regenerarse desde perfiles.');

        app(GenerateDraftScheduleBatchFromProfilesAction::class)->handle(
            $rh,
            $company,
            $draft,
            GenerateDraftScheduleBatchFromProfilesAction::MODE_MISSING_ONLY,
        );
    }

    public function test_corrective_publication_supersedes_previous_and_resolver_uses_new_version(): void
    {
        $this->seedPublishedScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $published = $this->publishedBatch($company);
        $oldHash = $published->snapshot_sha256;
        $draft = app(CreateCorrectiveScheduleBatchAction::class)->handle($rh, $company, $published, 'Cambio autorizado de un dia.')->correctiveBatch;
        $assignment = $this->firstShift($draft);

        app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $draft, $assignment->employmentRelationship, [
            'work_date' => $assignment->work_date->toDateString(),
            'day_type' => 'rest',
            'timezone' => $assignment->timezone,
            'source_type' => 'manual',
            'source_reference' => ['schema_version' => 1, 'correction' => true, 'reason' => 'Descanso autorizado'],
        ]);

        $comparison = app(CompareScheduleBatchVersionsAction::class)->handle($published, $draft);
        $this->assertSame(1, $comparison->changedDays);
        $this->assertSame(0, $comparison->addedDays);
        $this->assertSame(0, $comparison->removedDays);

        $validation = app(ValidateCorrectiveScheduleBatchForPublicationAction::class)->handle($rh, $company, $draft);
        $this->assertTrue($validation->valid(), implode(' | ', $validation->errors));

        $result = app(PublishCorrectiveScheduleBatchAction::class)->handle($rh, $company, $draft);
        $this->assertSame('superseded', $result->previousBatch->status);
        $this->assertSame('published', $result->correctiveBatch->status);
        $this->assertSame($draft->id, $result->previousBatch->superseded_by);
        $this->assertSame($oldHash, $published->fresh()->snapshot_sha256);
        $this->assertNotSame($oldHash, $result->snapshotSha256);
        $this->assertTrue(app(VerifyPublishedScheduleBatchSnapshotAction::class)->handle($company, $published->fresh())->valid);
        $this->assertTrue(app(VerifyPublishedScheduleBatchSnapshotAction::class)->handle($company, $draft->fresh())->valid);

        $resolved = app(ResolveDailyScheduleForRelationshipDateAction::class)->handle($company, $assignment->employmentRelationship, $assignment->work_date->toDateString());
        $this->assertSame(2, $resolved['batch_version']);
        $this->assertSame($result->snapshotSha256, $resolved['snapshot_sha256']);
        $this->assertSame('rest', $resolved['day_type']);
    }

    public function test_corrected_scenario_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimeCorrectedScheduleScenarioSeeder::class]);
        Artisan::call('db:seed', ['--class' => VeraTimeCorrectedScheduleScenarioSeeder::class]);

        [$office] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        [$cycle] = $this->companyAndUser('VTSP-CYCLE', 'rh.cycle.demo@veratime.local');

        $this->assertTrue(ScheduleBatch::query()->where('company_id', $office->id)->where('version', 1)->where('status', 'superseded')->exists());
        $this->assertTrue(ScheduleBatch::query()->where('company_id', $office->id)->where('version', 2)->where('status', 'published')->exists());
        $this->assertTrue(ScheduleBatch::query()->where('company_id', $cycle->id)->whereNull('version')->where('status', 'draft')->exists());
        $this->assertSame(2, ScheduleBatch::query()->where('company_id', $office->id)->count());
        $this->assertFalse(Schema::hasTable('work_days'));
        $this->assertFalse(Schema::hasTable('alerts'));
    }

    private function seedPublishedScenarios(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimePublishedScheduleScenarioSeeder::class]);
    }

    private function companyAndUser(string $taxId, string $email): array
    {
        return [
            Company::query()->where('tax_id', $taxId)->firstOrFail(),
            User::query()->where('email', $email)->firstOrFail(),
        ];
    }

    private function publishedBatch(Company $company): ScheduleBatch
    {
        return ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('status', 'published')
            ->whereDate('period_start', '2026-08-03')
            ->whereDate('period_end', '2026-08-16')
            ->orderByDesc('version')
            ->firstOrFail();
    }

    private function firstShift(ScheduleBatch $batch): DailyScheduleAssignment
    {
        return DailyScheduleAssignment::query()
            ->with('employmentRelationship')
            ->where('schedule_batch_id', $batch->id)
            ->where('day_type', 'shift')
            ->orderBy('work_date')
            ->firstOrFail();
    }
}
