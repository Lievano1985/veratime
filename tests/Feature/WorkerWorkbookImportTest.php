<?php

namespace Tests\Feature;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Workers\Actions\GenerateWorkerWorkbookAction;
use App\Domains\Workers\Actions\ImportWorkerWorkbookAction;
use App\Domains\Workers\Actions\ReadWorkerWorkbookAction;
use App\Livewire\Workers\ImportWorkbook;
use App\Models\Center;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class WorkerWorkbookImportTest extends TestCase
{
    use RefreshDatabase;

    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function scenario(): array
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['code' => 'CENTRO-01', 'status' => 'active']);
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        return [$company, $center, $user];
    }

    private function workbook(array $rows, string $type = 'Xlsx', ?Spreadsheet $book = null): string
    {
        $book ??= new Spreadsheet;
        $book->getActiveSheet()->setTitle('Trabajadores')->fromArray(ReadWorkerWorkbookAction::HEADERS, null, 'A1');
        foreach ($rows as $i => $row) {
            $row[5] ??= 'worker-'.strtolower($row[0]).'@example.test';
            foreach ($row as $j => $value) {
                $book->getActiveSheet()->setCellValueExplicit([$j + 1, $i + 2], $value, DataType::TYPE_STRING);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'worker-import-');
        $this->paths[] = $path;
        IOFactory::createWriter($book, $type)->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    public function test_email_is_required_and_duplicates_block_preview(): void
    {
        [$company, $center, $user] = $this->scenario();
        $path = $this->workbook([
            ['E1', 'Sin correo', $center->code, '2026-10-30', '', ''],
            ['E2', 'Con correo', $center->code, '2026-10-30', '', 'same@example.test'],
            ['E3', 'Repetido', $center->code, '2026-10-30', '', 'SAME@example.test'],
        ]);
        $rows = app(ReadWorkerWorkbookAction::class)->handle($user, $company, $path);
        $this->assertNotEmpty($rows[0]['errors']);
        $this->assertSame([], $rows[1]['errors']);
        $this->assertContains('El correo se repite en el archivo.', $rows[2]['errors']);
        $this->expectException(ValidationException::class);
        app(ImportWorkerWorkbookAction::class)->handle($user, $company, $path, hash_file('sha256', $path));
    }

    public function test_both_excel_formats_preserve_codes_and_create_initial_relationships(): void
    {
        [$company, $center, $user] = $this->scenario();
        foreach (['Xls' => '0001', 'Xlsx' => '0002'] as $type => $code) {
            $path = $this->workbook([[$code, 'Ana Pérez', $center->code, '30/10/2026', '']], $type);
            $rows = app(ReadWorkerWorkbookAction::class)->handle($user, $company, $path);
            $this->assertSame([], $rows[0]['errors']);
            $this->assertSame('2026-10-30', $rows[0]['data']['started_at']);
            $this->assertSame(1, app(ImportWorkerWorkbookAction::class)->handle($user, $company, $path, hash_file('sha256', $path)));
            $worker = $company->workers()->where('employee_code', $code)->firstOrFail();
            $this->assertSame('excel', $worker->source);
            $this->assertSame($center->id, $worker->activeEmploymentRelationship->center_id);
            $this->assertNull($worker->credential);
        }
    }

    public function test_template_round_trip_and_excel_date_cells(): void
    {
        [$company, $center, $user] = $this->scenario();
        $book = app(GenerateWorkerWorkbookAction::class)->build($user, $company);
        $this->assertSame($center->code, $book->getSheetByName('Centros')->getCell('A2')->getValue());
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('D2', Date::PHPToExcel(new \DateTime('2026-10-30')));
        $path = $this->workbook([['003', 'Luis Pérez', $center->code]], book: $book);
        $rows = app(ReadWorkerWorkbookAction::class)->handle($user, $company, $path);
        $this->assertCount(1, $rows);
        $this->assertSame([], $rows[0]['errors']);
        $this->assertSame('2026-10-30', $rows[0]['data']['started_at']);
        $this->get(route('workers.xlsx.template'))->assertOk()->assertDownload('vera-time-plantilla-trabajadores.xlsx');
    }

    public function test_errors_block_entire_import_and_existing_workers_are_preserved(): void
    {
        [$company, $center, $user] = $this->scenario();
        Worker::factory()->for($company)->create(['employee_code' => 'EXISTENTE', 'full_name' => 'Conservar']);
        $otherCenter = Center::factory()->create(['code' => 'OTRA-EMPRESA']);
        $path = $this->workbook([
            ['NUEVO', 'Persona válida', $center->code, '2026-10-30', ''],
            ['EXISTENTE', 'No sobrescribir', $center->code, '2026-10-30', ''],
            ['NUEVO', 'Duplicado', $center->code, '2026-10-30', ''],
            ['OTRO', 'Centro ajeno', $otherCenter->code, '31/02/2026', ''],
        ]);
        $rows = app(ReadWorkerWorkbookAction::class)->handle($user, $company, $path);
        $this->assertEmpty($rows[0]['errors']);
        foreach (array_slice($rows, 1) as $row) {
            $this->assertNotEmpty($row['errors']);
        }
        try {
            app(ImportWorkerWorkbookAction::class)->handle($user, $company, $path, hash_file('sha256', $path));
            $this->fail('Import should be rejected');
        } catch (ValidationException) {
            $this->assertSame(1, $company->workers()->count());
            $this->assertSame('Conservar', $company->workers()->first()->full_name);
        }
    }

    public function test_formula_cells_are_not_evaluated_or_imported(): void
    {
        [$company, $center, $user] = $this->scenario();
        $book = new Spreadsheet;
        $book->getActiveSheet()->setCellValue('B2', '=1+1');
        $path = $this->workbook([['004']], book: $book);
        $rows = app(ReadWorkerWorkbookAction::class)->handle($user, $company, $path);
        $this->assertContains('Usa valores, sin fórmulas ni errores de Excel.', $rows[0]['errors']);
        $this->assertSame(0, $company->workers()->count());
    }

    public function test_livewire_requires_preview_and_revalidates_before_applying(): void
    {
        [$company, $center] = $this->scenario();
        $path = $this->workbook([['005', 'Persona nueva', $center->code, '2026-10-30', '']]);
        $upload = UploadedFile::fake()->createWithContent('trabajadores.xlsx', file_get_contents($path));
        $component = Livewire::test(ImportWorkbook::class)->call('openPanel')->set('file', $upload)
            ->call('apply')->assertHasErrors('file')->call('preview')->assertHasNoErrors()->assertSet('previewCompanyId', $company->id);
        Worker::factory()->for($company)->create(['employee_code' => '005']);
        $component->call('apply')->assertHasErrors('file');
        $this->assertSame(1, $company->workers()->count());
    }

    public function test_livewire_success_and_worker_role_cannot_import_or_download(): void
    {
        [$company, $center] = $this->scenario();
        $path = $this->workbook([['006', 'Importación desde pantalla', $center->code, '2026-10-30', '']]);
        Livewire::test(ImportWorkbook::class)->call('openPanel')
            ->set('file', UploadedFile::fake()->createWithContent('trabajadores.xlsx', file_get_contents($path)))
            ->call('preview')->assertHasNoErrors()->call('apply')->assertHasNoErrors()->assertRedirect(route('workers.index'));
        $this->assertSame(1, $company->workers()->count());
        $role = Role::query()->firstOrCreate(['key' => RoleKey::TRABAJADOR], ['name' => 'Trabajador', 'is_system' => true]);
        $workerUser = User::factory()->create(['status' => 'active']);
        $workerUser->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $this->actingAs($workerUser);
        Livewire::test(ImportWorkbook::class)->call('openPanel')->assertForbidden();
        $this->get(route('workers.xlsx.template'))->assertForbidden();
    }

    public function test_preview_cannot_be_applied_to_another_company(): void
    {
        [$company, $center, $user] = $this->scenario();
        $other = Company::factory()->create(['status' => 'active']);
        $role = Role::where('key', RoleKey::ADMIN_EMPRESA)->firstOrFail();
        $user->companies()->attach($other, ['role_id' => $role->id, 'status' => 'active']);
        $path = $this->workbook([['007', 'Empresa original', $center->code, '2026-10-30', '']]);
        $component = Livewire::test(ImportWorkbook::class)->call('openPanel')
            ->set('file', UploadedFile::fake()->createWithContent('trabajadores.xlsx', file_get_contents($path)))
            ->call('preview')->assertHasNoErrors();
        session(['current_company_id' => $other->id]);
        app(CurrentCompany::class)->set($other);
        $component->call('apply')->assertHasErrors('file');
        $this->assertSame(0, Worker::count());
    }

    public function test_modified_file_and_oversized_or_incorrect_workbooks_are_rejected(): void
    {
        [$company, $center, $user] = $this->scenario();
        $path = $this->workbook([['008', 'Archivo cambiado', $center->code, '2026-10-30', '']]);
        try {
            app(ImportWorkerWorkbookAction::class)->handle($user, $company, $path, str_repeat('0', 64));
            $this->fail('Changed file should be rejected');
        } catch (ValidationException) {
            $this->assertSame(0, Worker::count());
        }
        foreach (['headers', 'rows'] as $invalid) {
            $book = new Spreadsheet;
            if ($invalid === 'rows') {
                $book->getActiveSheet()->setCellValue('A502', 'Demasiadas filas');
            }
            $path = $this->workbook([], book: $book);
            if ($invalid === 'headers') {
                $book = IOFactory::load($path);
                $book->getActiveSheet()->setCellValue('A1', 'Encabezado incorrecto');
                IOFactory::createWriter($book, 'Xlsx')->save($path);
                $book->disconnectWorksheets();
            }
            try {
                app(ReadWorkerWorkbookAction::class)->handle($user, $company, $path);
                $this->fail('Invalid workbook should be rejected');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }
    }
}
