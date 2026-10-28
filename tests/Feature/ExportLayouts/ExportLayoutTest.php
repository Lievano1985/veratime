<?php

use App\Domains\ExportLayouts\Actions\BuildLayoutMatrixAction;
use App\Domains\ExportLayouts\Actions\BuildPeriodLayoutAction;
use App\Domains\ExportLayouts\Actions\DownloadAttendancePeriodLayoutAction;
use App\Domains\ExportLayouts\Actions\DownloadLayoutMatrixAction;
use App\Domains\ExportLayouts\Actions\SaveExportLayoutAction;
use App\Domains\ExportLayouts\Actions\SetDefaultExportLayoutAction;
use App\Domains\ExportLayouts\Support\LayoutDefinition;
use App\Domains\ExportLayouts\Support\LayoutExample;
use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\ExportLayout;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

function layoutContext(string $roleKey = RoleKey::ADMIN_EMPRESA): array
{
    $company = Company::factory()->create();
    $user = User::factory()->create(['status' => 'active']);
    $role = Role::query()->where('key', $roleKey)->first() ?? Role::factory()->create(['key' => $roleKey]);
    $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

    return [$company, $user];
}

function layoutClosedPeriod(Company $company): AttendancePeriod
{
    $period = AttendancePeriod::factory()->forCompany($company)->create([
        'period_start' => '2026-10-12', 'period_end' => '2026-10-18', 'status' => AttendancePeriod::STATUS_CLOSED,
    ]);
    $rows = LayoutExample::rows();
    $json = json_encode(['company_id' => $company->id, 'period_id' => $period->id,
        'payroll_export' => ['schema_version' => 'payroll_rows.v1', 'company_id' => $company->id, 'period_id' => $period->id,
            'columns' => array_keys($rows[0]), 'rows' => $rows]], JSON_THROW_ON_ERROR);
    $period->forceFill(['snapshot_schema_version' => 'attendance_period.v2', 'snapshot_canonical_json' => $json, 'snapshot_sha256' => hash('sha256', $json)])->save();

    return $period;
}

it('builds the DYH data block with codes as text and no vendor header rows', function (): void {
    $matrix = app(BuildLayoutMatrixAction::class)->handle(LayoutDefinition::defaults(), LayoutExample::rows(), '2026-10-12', '2026-10-18');
    expect($matrix['headers'])->toHaveCount(8)->and($matrix['headers'][0])->toBe('Código Empleado')
        ->and($matrix['headers'][1])->toBe('12/10/2026')
        ->and($matrix['rows'][0][0])->toBe('0001')->and($matrix['rows'][0][1])->toBe('1FINJ')
        ->and($matrix['rows'][0][2])->toBe('2.5HE2')->and($matrix['rows'][0][3])->toBe('')
        ->and($matrix['rows'][1][3])->toBe('0.5RET')->and($matrix['errors'])->toBe([]);
});

it('transposes workers and dates without changing incidence values', function (): void {
    $definition = LayoutDefinition::defaults();
    $definition['orientation'] = 'dates_rows';
    $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, LayoutExample::rows(), '2026-10-12', '2026-10-18');
    expect($matrix['headers'])->toBe(['Fecha', '0001', '0002'])->and($matrix['rows'])->toHaveCount(7)
        ->and($matrix['rows'][0])->toBe(['12/10/2026', '1FINJ', '1PSS'])
        ->and($matrix['rows'][1][1])->toBe('2.5HE2');
});

it('changes nomenclature units and included incidences independently', function (): void {
    $definition = LayoutDefinition::defaults();
    $definition['rules'][0]['code'] = 'F';
    $definition['rules'][1]['enabled'] = false;
    $definition['rules'][3]['unit'] = 'minutes';
    $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, LayoutExample::rows(), '2026-10-12', '2026-10-18');
    expect($matrix['rows'][0][1])->toBe('1F')->and($matrix['rows'][0][4])->toBe('')
        ->and($matrix['rows'][1][3])->toBe('30RET')->and($matrix['warnings'])->not->toBeEmpty();
});

it('blocks conflicts instead of picking the first incidence for a worker and date', function (): void {
    $rows = LayoutExample::rows();
    $rows[0]['horas_extra_dobles'] = '2';
    $definition = LayoutDefinition::defaults();
    $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, $rows, '2026-10-12', '2026-10-18');
    expect(implode(' ', $matrix['errors']))->toContain('1FINJ, 2HE2');
    expect(fn () => app(DownloadLayoutMatrixAction::class)->handle($definition, $matrix, 'test'))->toThrow(ValidationException::class);
});

it('rejects duplicate records and employee identifiers instead of merging them', function (): void {
    $rows = LayoutExample::rows();
    $rows[] = $rows[0];
    $rows[] = array_replace($rows[1], ['empleado_id' => '3']);
    $matrix = app(BuildLayoutMatrixAction::class)->handle(LayoutDefinition::defaults(), $rows, '2026-10-12', '2026-10-18');
    expect(implode(' ', $matrix['errors']))->toContain('más de un registro')->toContain('repetida');
});

it('validates mappings and never invents unsupported concepts', function (): void {
    $definition = LayoutDefinition::defaults();
    $definition['rules'][0]['code'] = '';
    expect(fn () => LayoutDefinition::validate($definition))->toThrow(ValidationException::class);
    $definition = LayoutDefinition::defaults();
    $definition['rules'][1]['code'] = 'FINJ';
    expect(fn () => LayoutDefinition::validate($definition))->toThrow(ValidationException::class);
    $definition = LayoutDefinition::defaults();
    $definition['rules'][0]['unit'] = 'minutes';
    expect(fn () => LayoutDefinition::validate($definition))->toThrow(ValidationException::class);
    $definition = LayoutDefinition::defaults();
    $definition['rules'][11]['enabled'] = true;
    expect(fn () => LayoutDefinition::validate($definition))->toThrow(ValidationException::class);
});

it('does not round a positive incidence to zero silently', function (): void {
    $definition = LayoutDefinition::defaults();
    $definition['precision'] = 0;
    $rows = LayoutExample::rows();
    $rows[4]['minutos_retardo'] = 1;
    $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, $rows, '2026-10-12', '2026-10-18');
    expect(implode(' ', $matrix['errors']))->toContain('en cero');
});

it('opens a separate builder and saves edits and duplicate layouts within the company', function (): void {
    [$company, $user] = layoutContext();
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    $screen = Volt::test('export-layouts.index')->assertSee('Layouts de exportación')->assertDontSee('El generador anterior sigue en')
        ->assertDontSee('los cálculos de asistencia se conservan')->assertDontSee('hoja oficial')
        ->assertSee('2.5HE2')->call('setOrientation', 'dates_rows')->assertSet('form.orientation', 'dates_rows')
        ->set('form.rules.0.code', 'F')->call('saveLayout')->assertHasNoErrors();
    $layout = ExportLayout::firstOrFail();
    expect($layout->company_id)->toBe($company->id)->and($layout->configuration['rules'][0]['code'])->toBe('F');
    $screen->set('form.name', 'Nuevo nombre')->call('saveLayout')->assertHasNoErrors();
    expect($layout->fresh()->revision)->toBe(2);
    $screen->call('duplicateLayout', $layout->id)->assertSet('editingLayoutId', null)->call('saveLayout')->assertHasNoErrors();
    expect(ExportLayout::count())->toBe(2);
});

it('supports blank layouts and accessible ordering controls without requiring drag and drop', function (): void {
    [$company, $user] = layoutContext();
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('export-layouts.index')->call('startBlank')->assertSet('form.name', '')
        ->set('form.name', 'Propio')->call('saveLayout')->assertHasErrors('form.rules')
        ->set('form.rules.0.enabled', true)->call('moveRule', 0, 2)->assertSet('form.rules.2.key', 'unjustified_absence')
        ->call('saveLayout')->assertHasNoErrors();
});

it('rejects stale revisions and unauthorized writes', function (): void {
    [$company, $user] = layoutContext();
    $action = app(SaveExportLayoutAction::class);
    $layout = $action->handle($user, $company, LayoutDefinition::defaults());
    $action->handle($user, $company, $layout->configuration, $layout, 1);
    expect(fn () => $action->handle($user, $company, $layout->configuration, $layout, 1))->toThrow(ValidationException::class);
    [$otherCompany, $otherUser] = layoutContext();
    expect(fn () => $action->handle($otherUser, $otherCompany, $layout->configuration, $layout, 1))->toThrow(HttpException::class);
});

it('keeps other company layouts inaccessible even with a forged identifier', function (): void {
    [$company, $user] = layoutContext();
    [$otherCompany, $otherUser] = layoutContext();
    $foreign = app(SaveExportLayoutAction::class)->handle($otherUser, $otherCompany, LayoutDefinition::defaults());
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    $this->expectException(ModelNotFoundException::class);
    Volt::test('export-layouts.index')->call('openLayout', $foreign->id);
});

it('denies the builder to supervisors and guests', function (): void {
    $this->get(route('export-layouts.index'))->assertRedirect(route('login'));
    [$company, $user] = layoutContext(RoleKey::SUPERVISOR);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('export-layouts.index')->assertForbidden();
});

it('reads only verified closed snapshots and never recalculates historical values', function (): void {
    [$company, $user] = layoutContext();
    $period = layoutClosedPeriod($company);
    $before = $period->fresh()->getRawOriginal();
    $matrix = app(BuildPeriodLayoutAction::class)->handle($user, $company, $period, LayoutDefinition::defaults());
    expect($matrix['rows'][0][0])->toBe('0001')->and($period->fresh()->getRawOriginal())->toBe($before);
    $period->update(['snapshot_canonical_json' => $period->snapshot_canonical_json.' ']);
    expect(fn () => app(BuildPeriodLayoutAction::class)->handle($user, $company, $period, LayoutDefinition::defaults()))->toThrow(ValidationException::class);
});

it('rejects periods from another company and periods not closed', function (): void {
    [$company, $user] = layoutContext();
    $period = layoutClosedPeriod(Company::factory()->create());
    expect(fn () => app(BuildPeriodLayoutAction::class)->handle($user, $company, $period, LayoutDefinition::defaults()))->toThrow(HttpException::class);
    $period = layoutClosedPeriod($company);
    $period->update(['status' => AttendancePeriod::STATUS_OPEN]);
    expect(fn () => app(BuildPeriodLayoutAction::class)->handle($user, $company, $period, LayoutDefinition::defaults()))->toThrow(AuthorizationException::class);
});

it('writes genuine Excel dates and preserves employee codes and quantity strings', function (): void {
    $definition = LayoutDefinition::defaults();
    $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, LayoutExample::rows(), '2026-10-12', '2026-10-18');
    ob_start();
    app(DownloadLayoutMatrixAction::class)->handle($definition, $matrix, 'test')->sendContent();
    $bytes = ob_get_clean();
    $path = tempnam(sys_get_temp_dir(), 'layout-test-');
    try {
        file_put_contents($path, $bytes);
        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();
        expect($sheet->getCell('A1')->getValue())->toBe('Código Empleado')
            ->and($sheet->getCell('B1')->getDataType())->toBe(DataType::TYPE_NUMERIC)
            ->and($sheet->getCell('A2')->getValue())->toBe('0001')
            ->and($sheet->getCell('A2')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('C2')->getValue())->toBe('2.5HE2');
        $book->disconnectWorksheets();
    } finally {
        unlink($path);
    }
});

it('protects CSV and TXT text from spreadsheet formulas', function (): void {
    foreach (['csv', 'txt'] as $format) {
        $definition = LayoutDefinition::defaults();
        $definition['format'] = $format;
        $rows = LayoutExample::rows();
        foreach ($rows as &$row) {
            if ($row['empleado_id'] === '1') {
                $row['numero_empleado'] = '=1+1';
            }
        }
        unset($row);
        $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, $rows, '2026-10-12', '2026-10-18');
        ob_start();
        app(DownloadLayoutMatrixAction::class)->handle($definition, $matrix, 'test')->sendContent();
        $bytes = ob_get_clean();
        expect($bytes)->toContain("'=1+1");
    }
});

it('previews and downloads an actual period through the separate builder', function (): void {
    [$company, $user] = layoutContext();
    $period = layoutClosedPeriod($company);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('export-layouts.index')->call('saveLayout')->assertHasNoErrors()
        ->set('selectedPeriodId', (string) $period->id)->call('previewPeriod')->assertHasNoErrors()->assertSee('Período real')
        ->call('downloadPeriod')->assertHasNoErrors()->assertFileDownloaded();
});

it('renders the separate incident and nomenclature menus and invalidates stale period previews', function (): void {
    [$company, $user] = layoutContext();
    $period = layoutClosedPeriod($company);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('export-layouts.index')->call('saveLayout')->assertHasNoErrors()
        ->set('activeTab', 'incidents')->assertSee('¿Qué incidencias llevará este archivo?')
        ->assertDontSee('Conceptos de referencia pendientes de integración')
        ->assertDontSee('Basado en tu revisión de CONTPAQi Nóminas')
        ->set('activeTab', 'preview')->assertSee('Descargar reporte ficticio de prueba')
        ->assertDontSee('Generar desde un período cerrado')->assertDontSee('Ver período')
        ->assertDontSee('Descargar período')->call('downloadExample')->assertFileDownloaded()
        ->set('activeTab', 'codes')->assertSee('Nomenclaturas del destino')
        ->set('selectedPeriodId', (string) $period->id)->call('previewPeriod')->assertSee('Período real')
        ->set('form.rules.0.code', 'F')->assertSet('periodPreview', [])
        ->call('downloadPeriod')->assertHasErrors('export')
        ->call('saveLayout')->assertHasNoErrors()->call('downloadPeriod')->assertFileDownloaded();
});

it('keeps inactive layouts and empty incidence selections from producing a period file', function (): void {
    [$company, $user] = layoutContext();
    $period = layoutClosedPeriod($company);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('export-layouts.index')->set('form.status', 'inactive')->call('saveLayout')->assertHasNoErrors()
        ->set('selectedPeriodId', (string) $period->id)->call('downloadPeriod')->assertHasErrors('export');
    $matrix = app(BuildLayoutMatrixAction::class)->handle(LayoutDefinition::defaults(), [], '2026-10-12', '2026-10-18');
    expect(fn () => app(DownloadLayoutMatrixAction::class)->handle(LayoutDefinition::defaults(), $matrix, 'empty'))->toThrow(ValidationException::class);
});

it('rejects unknown sources and schema versions and reports missing numeric values', function (): void {
    $definition = LayoutDefinition::defaults();
    $definition['schema_version'] = 99;
    expect(fn () => LayoutDefinition::validate($definition))->toThrow(ValidationException::class);
    $definition = LayoutDefinition::defaults();
    $definition['rules'][0]['key'] = 'invented';
    expect(fn () => LayoutDefinition::validate($definition))->toThrow(ValidationException::class);
    $rows = LayoutExample::rows();
    unset($rows[0]['horas_extra_dobles']);
    $matrix = app(BuildLayoutMatrixAction::class)->handle(LayoutDefinition::defaults(), $rows, '2026-10-12', '2026-10-18');
    expect(implode(' ', $matrix['errors']))->toContain('cantidad válida');
});

it('writes transposed Excel dates in the first column and worker codes in the first row', function (): void {
    $definition = LayoutDefinition::defaults();
    $definition['orientation'] = 'dates_rows';
    $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, LayoutExample::rows(), '2026-10-12', '2026-10-18');
    ob_start();
    app(DownloadLayoutMatrixAction::class)->handle($definition, $matrix, 'vertical')->sendContent();
    $bytes = ob_get_clean();
    $path = tempnam(sys_get_temp_dir(), 'layout-vertical-');
    try {
        file_put_contents($path, $bytes);
        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();
        expect($sheet->getCell('A1')->getValue())->toBe('Fecha')
            ->and($sheet->getCell('A2')->getDataType())->toBe(DataType::TYPE_NUMERIC)
            ->and($sheet->getCell('B1')->getValue())->toBe('0001')
            ->and($sheet->getCell('B2')->getValue())->toBe('1FINJ');
        $book->disconnectWorksheets();
    } finally {
        unlink($path);
    }
});

it('recognizes a worked rest day without converting it into extra hours', function (): void {
    $definition = LayoutDefinition::defaults(false);
    $definition['name'] = 'Días trabajados';
    $definition['rules'][6]['enabled'] = true;
    $rows = [array_replace(LayoutExample::rows()[0], ['tipo_incidencia' => '', 'estatus_incidencia' => '',
        'horas_ordinarias' => '0', 'descanso_trabajado' => 'si'])];
    $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, $rows, '2026-10-12', '2026-10-18');
    expect($matrix['rows'][0][1])->toBe('1TRAB')->and($matrix['errors'])->toBe([]);
});

it('keeps one active default per company and clears it when inactivated', function (): void {
    [$company, $user] = layoutContext();
    $first = app(SaveExportLayoutAction::class)->handle($user, $company, LayoutDefinition::defaults());
    expect($first->is_default)->toBeTrue();
    $data = LayoutDefinition::defaults();
    $data['name'] = 'Segundo';
    $second = app(SaveExportLayoutAction::class)->handle($user, $company, $data);
    expect($second->is_default)->toBeFalse();
    app(SetDefaultExportLayoutAction::class)->handle($user, $company, $second);
    expect($first->fresh()->is_default)->toBeFalse()->and($second->fresh()->is_default)->toBeTrue();
    $data['status'] = 'inactive';
    $second = app(SaveExportLayoutAction::class)->handle($user, $company, $data, $second, $second->revision);
    expect($second->is_default)->toBeFalse();
    expect(fn () => app(SetDefaultExportLayoutAction::class)->handle($user, $company, $second))->toThrow(ValidationException::class);
});

it('does not change another company default and rejects a foreign layout', function (): void {
    [$company, $user] = layoutContext();
    [$other, $otherUser] = layoutContext();
    $layout = app(SaveExportLayoutAction::class)->handle($otherUser, $other, LayoutDefinition::defaults());
    expect(fn () => app(SetDefaultExportLayoutAction::class)->handle($user, $company, $layout))->toThrow(HttpException::class);
    expect($layout->fresh()->is_default)->toBeTrue();
});

it('exports from periods with the saved default layout and preserves the frozen closure', function (): void {
    [$company, $user] = layoutContext();
    $period = layoutClosedPeriod($company);
    $data = LayoutDefinition::defaults();
    $data['format'] = 'csv';
    $layout = app(SaveExportLayoutAction::class)->handle($user, $company, $data);
    $hash = $period->snapshot_sha256;
    $response = app(DownloadAttendancePeriodLayoutAction::class)->handle($user, $company, $period);
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    expect($content)->toContain('FINJ')->and($period->fresh()->snapshot_sha256)->toBe($hash);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('attendance-periods.index')->assertSee('Exportar con layout')
        ->call('openExportPanel', $period->id)
        ->assertSet('showExportPanel', true)->assertDontSee('Exportador CSV anterior')
        ->assertDontSee('Formato del archivo')->assertDontSee('Configurar layouts y predeterminado')
        ->call('downloadLayout')->assertFileDownloaded('incidencias-'.Str::slug($layout->name).'-20261012-r1.csv');
});

it('does not silently fall back to the old format when no default exists', function (): void {
    [$company, $user] = layoutContext();
    $period = layoutClosedPeriod($company);
    expect(fn () => app(DownloadAttendancePeriodLayoutAction::class)->handle($user, $company, $period))->toThrow(ValidationException::class);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('attendance-periods.index')->call('openExportPanel', $period->id)
        ->assertSee('Define un layout activo como predeterminado antes de exportar.');
});

it('blocks open foreign and tampered periods or layouts in the new export entry point', function (): void {
    [$company, $user] = layoutContext();
    [$other, $otherUser] = layoutContext();
    $period = layoutClosedPeriod($company);
    $foreign = layoutClosedPeriod($other);
    $layout = app(SaveExportLayoutAction::class)->handle($otherUser, $other, LayoutDefinition::defaults());
    $action = app(DownloadAttendancePeriodLayoutAction::class);
    expect(fn () => $action->handle($user, $company, $foreign))->toThrow(HttpException::class);
    expect(fn () => $action->handle($user, $company, $period, $layout->id))->toThrow(ModelNotFoundException::class);
    $period->update(['status' => 'open']);
    expect(fn () => $action->handle($user, $company, $period))->toThrow(AuthorizationException::class);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('attendance-periods.index')->call('openExportPanel', $period->id)->assertForbidden();
});

it('allows selecting the company default in the layout editor', function (): void {
    [$company, $user] = layoutContext();
    app(SaveExportLayoutAction::class)->handle($user, $company, LayoutDefinition::defaults());
    $data = LayoutDefinition::defaults();
    $data['name'] = 'NomiPAQ final';
    $layout = app(SaveExportLayoutAction::class)->handle($user, $company, $data);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('export-layouts.index')->assertSee('Usar como predeterminado')->call('makeDefault', $layout->id)->assertHasNoErrors();
    expect($layout->fresh()->is_default)->toBeTrue();
});
