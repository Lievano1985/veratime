<?php

namespace Tests\Feature\BlockF5A;

use App\Domains\Scheduling\Actions\ApplyDailyScheduleCsvImportAction;
use App\Domains\Scheduling\Actions\CreateCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateDailyScheduleCsvImportAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\ValidateDailyScheduleCsvImportAction;
use App\Domains\Scheduling\Exceptions\DailyScheduleCsvHeaderException;
use App\Domains\Scheduling\Exceptions\DailyScheduleCsvStalePreviewException;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\ImportBatch;
use App\Models\ScheduleBatch;
use App\Models\User;
use Database\Seeders\VeraTimeDailyScheduleCsvScenarioSeeder;
use Database\Seeders\VeraTimeDailyScheduleScenarioSeeder;
use Database\Seeders\VeraTimePublishedScheduleScenarioSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DailyScheduleCsvImportDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_registers_validates_and_applies_daily_schedule_csv_to_draft_batch(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);
        $this->putCsv('valid.csv', [
            ['STR-001', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Carga CSV valida.'],
            ['STR-002', '2026-08-03', 'descanso', '', '', '', '', '', '', '', '', '', '', '', 'Descanso CSV.'],
            ['STR-003', '2026-08-03', 'flexible', '', '420', '09:00', '18:00', '0', '0', '', '', '', '', '', 'Flexible CSV.'],
            ['STR-004', '2026-08-03', 'guardia', '', '', '', '', '', '', '08:00', '20:00', '0', '0', '240', 'Guardia CSV.'],
        ]);

        $import = $this->createImport($rh, $company, $batch, 'valid.csv')->importBatch;
        $validated = app(ValidateDailyScheduleCsvImportAction::class)->handle($rh, $import)->importBatch;

        $this->assertSame('validated', $validated->status);
        $this->assertSame(4, $validated->total_rows);
        $this->assertSame(0, $validated->invalid_rows);
        $this->assertNotNull($validated->validation_sha256);

        $result = app(ApplyDailyScheduleCsvImportAction::class)->handle($rh, $validated);

        $this->assertSame('applied', $result->importBatch->status);
        $this->assertSame(4, $result->appliedRows);
        $this->assertTrue(DailyScheduleAssignment::query()
            ->where('schedule_batch_id', $batch->id)
            ->where('source_type', 'csv')
            ->whereDate('work_date', '2026-08-03')
            ->exists());
        $this->assertSame('mixed', $batch->fresh()->creation_source);
    }

    public function test_header_schema_must_match_exact_version_one_headers(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);
        Storage::disk('local')->put('imports/bad-header.csv', "clave_empleado;fecha;tipo_dia\nSTR-001;2026-08-03;turno\n");
        $import = $this->createImport($rh, $company, $batch, 'bad-header.csv')->importBatch;

        $this->expectException(DailyScheduleCsvHeaderException::class);
        app(ValidateDailyScheduleCsvImportAction::class)->handle($rh, $import);
    }

    public function test_invalid_rows_block_application_and_do_not_write_daily_assignments(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);
        $before = $batch->dailyAssignments()->where('source_type', 'csv')->count();
        $this->putCsv('invalid.csv', [
            ['NO-EXISTE', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Empleado no existe.'],
            ['STR-001', '2026-08-03', 'turno', 'NO-TURNO', '', '', '', '', '', '', '', '', '', '', 'Turno no existe.'],
        ]);

        $validated = app(ValidateDailyScheduleCsvImportAction::class)
            ->handle($rh, $this->createImport($rh, $company, $batch, 'invalid.csv')->importBatch)
            ->importBatch;

        $this->assertSame('invalid', $validated->status);
        $this->assertSame(2, $validated->invalid_rows);
        $this->expectException(\App\Domains\Scheduling\Exceptions\DailyScheduleCsvImportStateException::class);

        try {
            app(ApplyDailyScheduleCsvImportAction::class)->handle($rh, $validated);
        } finally {
            $this->assertSame($before, $batch->fresh()->dailyAssignments()->where('source_type', 'csv')->count());
        }
    }

    public function test_duplicate_worker_date_inside_csv_is_invalid(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);
        $this->putCsv('duplicate.csv', [
            ['STR-001', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Uno.'],
            ['STR-001', '2026-08-03', 'descanso', '', '', '', '', '', '', '', '', '', '', '', 'Dos.'],
        ]);

        $validated = app(ValidateDailyScheduleCsvImportAction::class)
            ->handle($rh, $this->createImport($rh, $company, $batch, 'duplicate.csv')->importBatch)
            ->importBatch;

        $this->assertSame('invalid', $validated->status);
        $this->assertSame(1, $validated->invalid_rows);
    }

    public function test_stale_preview_blocks_apply_when_daily_assignment_changed_after_validation(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);
        $this->putCsv('stale.csv', [
            ['STR-001', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Carga CSV.'],
        ]);
        $validated = app(ValidateDailyScheduleCsvImportAction::class)
            ->handle($rh, $this->createImport($rh, $company, $batch, 'stale.csv')->importBatch)
            ->importBatch;
        $assignment = DailyScheduleAssignment::query()
            ->with('employmentRelationship')
            ->where('schedule_batch_id', $batch->id)
            ->whereDate('work_date', '2026-08-03')
            ->firstOrFail();

        app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $batch, $assignment->employmentRelationship, [
            'work_date' => '2026-08-03',
            'day_type' => 'rest',
            'timezone' => $assignment->timezone,
            'source_type' => 'manual',
            'source_reference' => ['schema_version' => 1, 'reason' => 'Cambio manual paralelo.'],
        ]);

        $this->expectException(DailyScheduleCsvStalePreviewException::class);
        app(ApplyDailyScheduleCsvImportAction::class)->handle($rh, $validated);
    }

    public function test_preserve_existing_policy_skips_existing_rows(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);
        $this->putCsv('preserve.csv', [
            ['STR-001', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Preservar existente.'],
        ]);

        $import = $this->createImport($rh, $company, $batch, 'preserve.csv', policy: 'preserve_existing')->importBatch;
        $validated = app(ValidateDailyScheduleCsvImportAction::class)->handle($rh, $import)->importBatch;
        $result = app(ApplyDailyScheduleCsvImportAction::class)->handle($rh, $validated);

        $this->assertSame(0, $result->appliedRows);
        $this->assertSame(1, $result->skippedRows);
        $this->assertFalse($batch->fresh()->dailyAssignments()->where('source_type', 'csv')->exists());
    }

    public function test_corrective_draft_csv_can_only_replace_existing_cloned_coverage(): void
    {
        Storage::fake('local');
        Artisan::call('db:seed', ['--class' => VeraTimePublishedScheduleScenarioSeeder::class]);
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $published = ScheduleBatch::query()->where('company_id', $company->id)->where('status', 'published')->firstOrFail();
        $draft = app(CreateCorrectiveScheduleBatchAction::class)->handle($rh, $company, $published, 'Correccion CSV.')->correctiveBatch;
        DailyScheduleAssignment::query()
            ->where('schedule_batch_id', $draft->id)
            ->whereDate('work_date', '2026-08-03')
            ->orderBy('id')
            ->firstOrFail()
            ->delete();
        $this->putCsv('correction.csv', [
            ['OFF-001', '2026-08-03', 'descanso', '', '', '', '', '', '', '', '', '', '', '', 'Intento agregar cobertura.'],
        ]);

        $validated = app(ValidateDailyScheduleCsvImportAction::class)
            ->handle($rh, $this->createImport($rh, $company, $draft, 'correction.csv')->importBatch)
            ->importBatch;

        $this->assertSame('invalid', $validated->status);
        $this->assertSame(1, $validated->invalid_rows);
    }

    public function test_supervisor_and_foreign_user_cannot_create_csv_import(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        [, $foreignRh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        [, $supervisor] = $this->companyAndUser('VTSP-CONSTRUCT', 'supervisor.construction.demo@veratime.local');
        $batch = $this->draftBatch($company);
        $this->putCsv('auth.csv', [
            ['STR-001', '2026-08-03', 'descanso', '', '', '', '', '', '', '', '', '', '', '', 'Auth.'],
        ]);

        try {
            $this->createImport($foreignRh, $company, $batch, 'auth.csv');
            $this->fail('Usuario de otra empresa debio bloquearse.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->expectException(AuthorizationException::class);
        $this->createImport($supervisor, $company, $batch, 'auth.csv');
    }

    public function test_csv_scenario_seeder_is_idempotent_and_does_not_create_future_modules(): void
    {
        Storage::fake('local');
        Artisan::call('db:seed', ['--class' => VeraTimeDailyScheduleCsvScenarioSeeder::class]);
        Artisan::call('db:seed', ['--class' => VeraTimeDailyScheduleCsvScenarioSeeder::class]);

        [$company] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');

        $this->assertSame(2, ImportBatch::query()->where('company_id', $company->id)->count());
        $this->assertTrue(ImportBatch::query()->where('company_id', $company->id)->where('status', 'applied')->exists());
        $this->assertTrue(ImportBatch::query()->where('company_id', $company->id)->where('status', 'invalid')->exists());
        $this->assertTrue(Schema::hasTable('work_days'));
        $this->assertTrue(Schema::hasTable('alerts'));
        $this->assertFalse(Schema::hasTable('incidents'));
        $this->assertFalse(Schema::hasTable('reports'));
    }

    private function seedDailyScenarios(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimeDailyScheduleScenarioSeeder::class]);
    }

    private function companyAndUser(string $taxId, string $email): array
    {
        return [
            Company::query()->where('tax_id', $taxId)->firstOrFail(),
            User::query()->where('email', $email)->firstOrFail(),
        ];
    }

    private function draftBatch(Company $company): ScheduleBatch
    {
        return ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', '2026-08-03')
            ->whereDate('period_end', '2026-08-16')
            ->where('status', 'draft')
            ->firstOrFail();
    }

    private function createImport(User $actor, Company $company, ScheduleBatch $batch, string $filename, string $policy = 'replace_existing'): \App\Domains\Scheduling\Data\CreateDailyScheduleCsvImportResult
    {
        return app(CreateDailyScheduleCsvImportAction::class)->handle($actor, $company, $batch, [
            'storage_disk' => 'local',
            'storage_path' => "imports/{$filename}",
            'original_filename' => $filename,
            'existing_assignment_policy' => $policy,
            'reason' => 'Prueba automatizada de importacion CSV.',
            'idempotency_key' => 'test-'.$filename,
        ]);
    }

    /**
     * @param list<list<string>> $rows
     */
    private function putCsv(string $filename, array $rows): void
    {
        Storage::disk('local')->put("imports/{$filename}", $this->csv($rows));
    }

    /**
     * @param list<list<string>> $rows
     */
    private function csv(array $rows): string
    {
        $headers = [
            'clave_empleado',
            'fecha',
            'tipo_dia',
            'codigo_turno',
            'minutos_requeridos',
            'inicio_ventana',
            'fin_ventana',
            'offset_inicio_ventana',
            'offset_fin_ventana',
            'inicio_disponibilidad',
            'fin_disponibilidad',
            'offset_inicio_disponibilidad',
            'offset_fin_disponibilidad',
            'maximo_minutos_trabajo',
            'motivo',
        ];

        return implode("\n", [implode(',', $headers), ...array_map(fn (array $row): string => implode(',', $row), $rows)])."\n";
    }
}
