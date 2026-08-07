<?php

namespace Tests\Feature\BlockF5B;

use App\Domains\Scheduling\Actions\CreateCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Livewire\Scheduling\DailyScheduleCsvImport;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\ImportBatch;
use App\Models\ScheduleBatch;
use App\Models\User;
use Database\Seeders\VeraTimeDailyScheduleCsvScenarioSeeder;
use Database\Seeders\VeraTimeDailyScheduleScenarioSeeder;
use Database\Seeders\VeraTimePublishedScheduleScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DailyScheduleCsvImportUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_csv_import_panel_only_for_editable_draft_batch(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.daily')
            ->call('selectBatch', $batch->id)
            ->assertSee('Importar CSV')
            ->assertDontSee('Historial de importaciones');

        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->call('openPanel')
            ->assertSee('Importacion CSV')
            ->assertSee('Descargar plantilla')
            ->assertDontSee('Historial de importaciones');
    }

    public function test_manager_downloads_csv_template_with_version_one_headers(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        $this->get(route('scheduling.daily.csv.template'))
            ->assertOk()
            ->assertDownload('vera-time-programacion-diaria-v1.csv');
    }

    public function test_manager_downloads_contextual_csv_template_with_filtered_pending_days(): void
    {
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);
        $unit = $company->organizationalUnits()->where('name', 'Piso de venta')->firstOrFail();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        $response = $this->get(route('scheduling.daily.csv.template', [
            'schedule_batch_id' => $batch->id,
            'organizational_unit_id' => $unit->id,
        ]))
            ->assertOk()
            ->assertDownload('vera-time-programacion-diaria-pendientes-v1.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('codigo_empleado,nombre_trabajador,2026-08-03,2026-08-04', $content);
        $this->assertStringContainsString('STR-001,"Tienda Demo Ana"', $content);
        $this->assertStringContainsString('Tienda Demo Ana', $content);
        $this->assertStringContainsString('STR-002,"Tienda Demo Bruno"', $content);
        $this->assertStringNotContainsString('STR-003', $content);
    }

    public function test_manager_uploads_valid_csv_reviews_preview_and_applies_to_draft_batch(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->call('openPanel')
            ->set('file', UploadedFile::fake()->createWithContent('programacion.csv', $this->csv([
                ['STR-001', '2026-08-03', 'descanso', '', '', '', '', '', '', '', '', '', '', '', 'Carga desde UI.'],
            ])))
            ->set('existingAssignmentPolicy', 'replace_existing')
            ->call('uploadAndValidate')
            ->assertHasNoErrors()
            ->assertSee('Archivo validado')
            ->assertSee('STR-001')
            ->assertSee('2026-08-03')
            ->assertSee('Descanso')
            ->assertDontSee('Sin observaciones')
            ->set('confirmApply', true)
            ->call('applyImport')
            ->assertHasNoErrors()
            ->assertSee('Importacion aplicada');

        $this->assertTrue(ImportBatch::query()
            ->where('company_id', $company->id)
            ->where('status', 'applied')
            ->exists());

        $import = ImportBatch::query()
            ->where('company_id', $company->id)
            ->where('status', 'applied')
            ->latest()
            ->firstOrFail();

        $this->assertSame('local', $import->storage_disk);
        $this->assertStringStartsWith("imports/{$company->id}/daily-schedules/", (string) $import->storage_path);
        $this->assertStringNotContainsString('public', (string) $import->storage_path);
        Storage::disk('local')->assertExists((string) $import->storage_path);

        $this->assertTrue(DailyScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->where('schedule_batch_id', $batch->id)
            ->where('source_type', 'csv')
            ->whereDate('work_date', '2026-08-03')
            ->exists());
        $this->assertSame('draft', $batch->fresh()->status);
    }

    public function test_manager_uploads_horizontal_shift_template_and_applies_turns(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->call('openPanel')
            ->set('file', UploadedFile::fake()->createWithContent('programacion-horizontal.csv', implode("\n", [
                "codigo_empleado\tnombre_trabajador\t03/08/2026\t04/08/2026\t05/08/2026",
                "STR-001\tTienda Demo Ana\tOPEN-08-16\tMID-11-19\tDESCANSO",
                '',
            ])))
            ->set('existingAssignmentPolicy', 'replace_existing')
            ->call('uploadAndValidate')
            ->assertHasNoErrors()
            ->assertSee('Archivo validado')
            ->assertSee('Codigo')
            ->assertSee('Trabajador')
            ->assertSee('STR-001')
            ->assertSee('Tienda Demo Ana')
            ->assertSee('2026-08-03')
            ->assertSee('2026-08-04')
            ->assertSee('OPEN-08-16')
            ->assertSee('MID-11-19')
            ->assertSee('Descanso')
            ->assertDontSee('Reemplazar')
            ->assertDontSee('Sin observaciones')
            ->set('confirmApply', true)
            ->call('applyImport')
            ->assertHasNoErrors();

        $this->assertTrue(DailyScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->where('schedule_batch_id', $batch->id)
            ->where('source_type', 'csv')
            ->whereDate('work_date', '2026-08-03')
            ->whereHas('shiftTemplate', fn ($query) => $query->where('code', 'OPEN-08-16'))
            ->exists());

        $this->assertTrue(DailyScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->where('schedule_batch_id', $batch->id)
            ->where('source_type', 'csv')
            ->whereDate('work_date', '2026-08-04')
            ->whereHas('shiftTemplate', fn ($query) => $query->where('code', 'MID-11-19'))
            ->exists());

        $this->assertTrue(DailyScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->where('schedule_batch_id', $batch->id)
            ->where('source_type', 'csv')
            ->where('day_type', 'rest')
            ->whereDate('work_date', '2026-08-05')
            ->exists());
    }

    public function test_invalid_import_shows_errors_downloads_sanitized_error_report_and_does_not_modify_calendar(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);
        $beforeCount = DailyScheduleAssignment::query()->where('schedule_batch_id', $batch->id)->count();

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->call('openPanel')
            ->set('file', UploadedFile::fake()->createWithContent('errores.csv', $this->csv([
                ['=CMD', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Fila invalida.'],
                ['+SUM', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Fila invalida.'],
                ['-BAD', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Fila invalida.'],
                ['@BAD', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Fila invalida.'],
            ])))
            ->call('uploadAndValidate')
            ->assertHasNoErrors()
            ->assertSee('Con errores')
            ->assertSee('Descargar errores');

        $import = ImportBatch::query()->where('company_id', $company->id)->latest()->firstOrFail();
        $this->assertSame('invalid', $import->status);
        $this->assertSame($beforeCount, DailyScheduleAssignment::query()->where('schedule_batch_id', $batch->id)->count());

        $response = $this->get(route('scheduling.daily.imports.errors', $import))
            ->assertOk()
            ->assertDownload("vera-time-importacion-{$import->id}-errores.csv");
        $content = $response->streamedContent();

        $this->assertStringContainsString("'=CMD", $content);
        $this->assertStringContainsString("'+SUM", $content);
        $this->assertStringContainsString("'-BAD", $content);
        $this->assertStringContainsString("'@BAD", $content);
    }

    public function test_stale_preview_blocks_ui_application(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        $component = Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->call('openPanel')
            ->set('file', UploadedFile::fake()->createWithContent('stale.csv', $this->csv([
                ['STR-001', '2026-08-03', 'descanso', '', '', '', '', '', '', '', '', '', '', '', 'Carga stale.'],
            ])))
            ->call('uploadAndValidate')
            ->assertHasNoErrors();

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
            'source_reference' => ['schema_version' => 1, 'reason' => 'Cambio paralelo.'],
        ]);

        $component
            ->set('confirmApply', true)
            ->call('applyImport')
            ->assertHasErrors(['csvImport']);
    }

    public function test_second_ui_application_is_blocked(): void
    {
        Storage::fake('local');
        $this->seedDailyScenarios();
        [$company, $rh] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        $batch = $this->draftBatch($company);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->call('openPanel')
            ->set('file', UploadedFile::fake()->createWithContent('doble.csv', $this->csv([
                ['STR-001', '2026-08-03', 'descanso', '', '', '', '', '', '', '', '', '', '', '', 'Carga doble.'],
            ])))
            ->call('uploadAndValidate')
            ->set('confirmApply', true)
            ->call('applyImport')
            ->assertHasNoErrors()
            ->set('confirmApply', true)
            ->call('applyImport')
            ->assertHasErrors(['csvImport']);
    }

    public function test_corrective_draft_import_does_not_modify_previous_published_version(): void
    {
        Storage::fake('local');
        Artisan::call('db:seed', ['--class' => VeraTimePublishedScheduleScenarioSeeder::class]);
        [$company, $rh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $published = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('status', 'published')
            ->firstOrFail();
        $previousHash = $published->snapshot_sha256;
        $previousCsvCount = DailyScheduleAssignment::query()
            ->where('schedule_batch_id', $published->id)
            ->where('source_type', 'csv')
            ->count();
        $draft = app(CreateCorrectiveScheduleBatchAction::class)
            ->handle($rh, $company, $published, 'Correccion CSV desde UI.')
            ->correctiveBatch;

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $draft->id])
            ->call('openPanel')
            ->set('file', UploadedFile::fake()->createWithContent('correccion.csv', $this->csv([
                ['OFF-001', '2026-08-03', 'descanso', '', '', '', '', '', '', '', '', '', '', '', 'Correccion desde CSV.'],
            ])))
            ->call('uploadAndValidate')
            ->set('confirmApply', true)
            ->call('applyImport')
            ->assertHasNoErrors();

        $this->assertSame('published', $published->fresh()->status);
        $this->assertSame($previousHash, $published->fresh()->snapshot_sha256);
        $this->assertSame($previousCsvCount, DailyScheduleAssignment::query()
            ->where('schedule_batch_id', $published->id)
            ->where('source_type', 'csv')
            ->count());
        $this->assertTrue(DailyScheduleAssignment::query()
            ->where('schedule_batch_id', $draft->id)
            ->where('source_type', 'csv')
            ->whereDate('work_date', '2026-08-03')
            ->exists());
    }

    public function test_supervisor_and_foreign_company_cannot_use_csv_import_ui_or_downloads(): void
    {
        Storage::fake('local');
        Artisan::call('db:seed', ['--class' => VeraTimeDailyScheduleCsvScenarioSeeder::class]);
        [$company] = $this->companyAndUser('VTSP-STORE', 'rh.store.demo@veratime.local');
        [, $supervisor] = $this->companyAndUser('VTSP-CONSTRUCT', 'supervisor.construction.demo@veratime.local');
        [, $foreignRh] = $this->companyAndUser('VTSP-OFFICE', 'rh.office.demo@veratime.local');
        $batch = $this->draftBatch($company);
        $import = ImportBatch::query()->where('company_id', $company->id)->firstOrFail();

        $this->actingAs($supervisor)->withSession(['current_company_id' => $supervisor->activeCompanies()->firstOrFail()->id]);
        $this->get(route('scheduling.daily.csv.template'))->assertForbidden();

        $this->actingAs($foreignRh)->withSession(['current_company_id' => $foreignRh->activeCompanies()->firstOrFail()->id]);
        $this->get(route('scheduling.daily.imports.errors', $import))->assertForbidden();

        $this->actingAs($foreignRh)->withSession(['current_company_id' => $foreignRh->activeCompanies()->firstOrFail()->id]);

        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->assertForbidden();
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
            ->whereDate('period_end', '2026-08-09')
            ->where('status', 'draft')
            ->firstOrFail();
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
