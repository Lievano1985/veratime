<?php

namespace Tests\Feature;

use App\Domains\Scheduling\Actions\ApplyPlannerScheduleAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\GenerateDailyScheduleXlsxAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Support\ScheduleWorkbookFormat as Format;
use App\Livewire\Scheduling\DailyScheduleCsvImport;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ScheduleExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchanged_export_does_not_overwrite_a_day_changed_after_download(): void
    {
        [$company, , , $people, , $batch] = $this->scenario();
        $book = app(GenerateDailyScheduleXlsxAction::class)->build($company, $batch);
        app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $batch, $people[0], [
            'work_date' => '2026-11-02', 'day_type' => 'rest', 'source_type' => 'manual',
        ]);
        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->set('file', $this->upload($book))->call('uploadAndValidate')->assertHasNoErrors()->assertSee('No hay cambios para aplicar.');
        $this->assertSame(0, ImportBatch::latest('id')->firstOrFail()->total_rows);
        $this->assertSame('rest', $batch->dailyAssignments()->where('employment_relationship_id', $people[0]->id)->whereDate('work_date', '2026-11-02')->firstOrFail()->day_type);
    }

    public function test_download_route_enforces_company_and_draft_permissions(): void
    {
        [$company, $center, $user, , , $batch] = $this->scenario();
        $product = Product::firstOrCreate(['key' => 'time'], ['name' => 'VERA Time', 'status' => 'active']);
        $company->customerAccount->products()->syncWithoutDetaching([$product->id => ['status' => 'active']]);
        $this->get(route('scheduling.daily.xlsx.template', ['schedule_batch_id' => $batch->id]))
            ->assertOk()->assertDownload('vera-time-horarios-2026-W45.xlsx');
        $foreign = Company::factory()->create();
        $foreignBatch = app(CreateScheduleBatchAction::class)->handle($foreign, Center::factory()->for($foreign)->create(), ['period_start' => '2026-11-02']);
        $this->get(route('scheduling.daily.xlsx.template', ['schedule_batch_id' => $foreignBatch->id]))->assertNotFound();
        $role = Role::firstOrCreate(['key' => RoleKey::SUPERVISOR], ['name' => 'Supervisor', 'is_system' => true]);
        $supervisor = User::factory()->create(['status' => 'active']);
        $supervisor->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id])
            ->get(route('scheduling.daily.xlsx.template', ['schedule_batch_id' => $batch->id]))->assertForbidden();
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $center = Center::factory()->for($company)->create();
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['key' => RoleKey::RH_ADMIN], ['name' => 'RH', 'is_system' => true]);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $people = collect([1, 2])->map(fn ($i) => EmploymentRelationship::factory()->create([
            'company_id' => $company->id, 'center_id' => $center->id, 'started_at' => '2026-01-01',
            'worker_id' => Worker::factory()->for($company)->create(['employee_code' => '000'.$i, 'full_name' => 'Persona '.$i])->id,
        ]));
        $turn = app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'DIA', 'name' => 'Diurno', 'description' => 'Operación diurna'], [[
            'segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '07:00', 'end_local_time' => '16:00', 'sort_order' => 1,
        ]]);
        $batch = app(CreateScheduleBatchAction::class)->handle($company, $center, ['period_start' => '2026-11-02'], $user);
        app(ApplyPlannerScheduleAction::class)->handle($user, $company, $batch, [
            'relationships' => $people->pluck('id')->all(), 'mode' => 'manual', 'replace_days' => false, 'replace_habitual' => false,
            'rules' => collect(range(1, 7))->map(fn ($day) => ['day' => $day, 'value' => $day === 7 ? 'rest' : (string) $turn->id])->all(),
        ]);

        return [$company, $center, $user, $people, $turn, $batch];
    }

    private function upload(Spreadsheet $book): UploadedFile
    {
        $path = Storage::disk('local')->path('test-workbook.xlsx');
        (new Xlsx($book))->save($path);
        $content = file_get_contents($path);
        $book->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent('horarios.xlsx', $content);
    }

    public function test_excel_has_current_schedules_dropdowns_reference_sheet_and_text_employee_codes(): void
    {
        [$company, , , , , $batch] = $this->scenario();
        $book = app(GenerateDailyScheduleXlsxAction::class)->build($company, $batch);
        $file = $this->upload($book);
        $loaded = IOFactory::load($file->getRealPath());
        $sheet = $loaded->getSheetByName('Programación');
        $this->assertSame('0001', $sheet->getCell('A6')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A6')->getDataType());
        $this->assertStringContainsString('DIA · Diurno · 07:00–16:00', $sheet->getCell('C6')->getValue());
        $this->assertSame('DESCANSO', $sheet->getCell('I6')->getValue());
        $this->assertSame('OpcionesHorario', $sheet->getCell('C6')->getDataValidation()->getFormula1());
        $this->assertNotNull($loaded->getNamedRange('OpcionesHorario'));
        $this->assertSame('Operación diurna', $loaded->getSheetByName('Turnos')->getCell('E8')->getValue());
        $this->assertSame(Format::VERSION, $loaded->getSheetByName('_VeraTime')->getCell('A1')->getValue());
        $this->assertSame('C6', $sheet->getFreezePane());
        $loaded->disconnectWorksheets();
    }

    public function test_excel_round_trip_requires_review_then_updates_draft_using_shared_validation(): void
    {
        [$company, , , $people, , $batch] = $this->scenario();
        $book = app(GenerateDailyScheduleXlsxAction::class)->build($company, $batch);
        $book->getActiveSheet()->setCellValueExplicit('C6', 'DESCANSO', DataType::TYPE_STRING);
        $ui = Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->call('openPanel')->assertSee('Descargar Excel')->set('file', $this->upload($book))->call('uploadAndValidate')->assertHasNoErrors();
        $this->assertSame('shift', $batch->dailyAssignments()->where('employment_relationship_id', $people[0]->id)->whereDate('work_date', '2026-11-02')->firstOrFail()->day_type);
        $ui->call('applyImport')->assertHasErrors('confirmApply')->set('confirmApply', true)->call('applyImport')->assertHasNoErrors();
        $this->assertSame('rest', $batch->dailyAssignments()->where('employment_relationship_id', $people[0]->id)->whereDate('work_date', '2026-11-02')->firstOrFail()->day_type);
        $import = ImportBatch::latest('id')->firstOrFail();
        $this->assertSame('applied', $import->status);
        $this->assertSame('xlsx', $import->metadata['source_format']);
        $this->assertStringEndsWith('.xlsx', $import->storage_path);
        Storage::disk('local')->assertExists($import->storage_path);
        $this->assertSame('draft', $batch->fresh()->status);
    }

    public function test_wrong_context_and_formula_cells_are_rejected_before_any_schedule_change(): void
    {
        [$company, , , , , $batch] = $this->scenario();
        foreach (['context', 'formula', 'headers'] as $invalid) {
            $book = app(GenerateDailyScheduleXlsxAction::class)->build($company, $batch->fresh());
            if ($invalid === 'context') {
                $book->getSheetByName('_VeraTime')->setCellValue('A2', '999999');
            } elseif ($invalid === 'formula') {
                $book->getActiveSheet()->setCellValue('C6', '=1+1');
            } else {
                $book->getActiveSheet()->setCellValue('C5', 'Otra fecha');
            }
            Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
                ->set('file', $this->upload($book))->call('uploadAndValidate')->assertHasErrors('file');
        }
        $this->assertSame(12, $batch->dailyAssignments()->where('day_type', 'shift')->count());
        $this->assertSame(2, $batch->dailyAssignments()->where('day_type', 'rest')->count());
    }

    public function test_invalid_turn_is_shown_per_worker_and_day_and_blocks_application(): void
    {
        [$company, , , , , $batch] = $this->scenario();
        $book = app(GenerateDailyScheduleXlsxAction::class)->build($company, $batch);
        $book->getActiveSheet()->setCellValueExplicit('C6', 'NO-EXISTE', DataType::TYPE_STRING);
        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->set('file', $this->upload($book))->call('uploadAndValidate')->assertHasNoErrors()->assertSee('0001')->assertSee('NO-EXISTE')
            ->set('confirmApply', true)->call('applyImport')->assertHasErrors('csvImport');
        $this->assertSame(1, ImportBatch::latest('id')->firstOrFail()->invalid_rows);
        $this->assertSame(12, $batch->dailyAssignments()->where('day_type', 'shift')->count());
    }

    public function test_filtered_export_and_advanced_day_preservation(): void
    {
        [$company, , , $people, , $batch] = $this->scenario();
        app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $batch, $people[0], [
            'work_date' => '2026-11-02', 'day_type' => 'flexible', 'required_minutes' => 480, 'source_type' => 'manual',
        ]);
        $book = app(GenerateDailyScheduleXlsxAction::class)->build($company, $batch->fresh(), ['worker_search' => '0001']);
        $this->assertSame('0001', $book->getActiveSheet()->getCell('A6')->getValue());
        $this->assertNull($book->getActiveSheet()->getCell('A7')->getValue());
        $this->assertStringStartsWith('[ACTUAL] Flexible', $book->getActiveSheet()->getCell('C6')->getValue());
        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->set('file', $this->upload($book))->call('uploadAndValidate')->assertHasNoErrors()
            ->set('confirmApply', true)->call('applyImport')->assertHasNoErrors();
        $day = $batch->dailyAssignments()->where('employment_relationship_id', $people[0]->id)->whereDate('work_date', '2026-11-02')->firstOrFail();
        $this->assertSame('flexible', $day->day_type);
        $this->assertSame(480, $day->required_minutes);
    }

    public function test_corrupt_xlsx_and_foreign_batch_access_are_rejected(): void
    {
        [, , , , , $batch] = $this->scenario();
        Livewire::test(DailyScheduleCsvImport::class, ['scheduleBatchId' => $batch->id])
            ->set('file', UploadedFile::fake()->createWithContent('invalido.xlsx', 'not an Excel file'))->call('uploadAndValidate')->assertHasErrors('file');
        $foreign = Company::factory()->create();
        $this->expectException(\InvalidArgumentException::class);
        app(GenerateDailyScheduleXlsxAction::class)->build($foreign, $batch);
    }
}
