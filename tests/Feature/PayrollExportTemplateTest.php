<?php

use App\Domains\Attendance\Actions\ExportAttendancePeriodPayrollCsvAction;
use App\Domains\Attendance\Actions\SavePayrollExportTemplateAction;
use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use App\Models\PayrollExportTemplate;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('creates the base CSV de periodos template for a company', function (): void {
    [$company, $user] = payrollTemplateCompanyUser();

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->get(route('payroll-export-templates.index'))
        ->assertOk()
        ->assertSee('CSV de períodos');

    $template = PayrollExportTemplate::query()->where('company_id', $company->id)->where('name', 'CSV de períodos')->firstOrFail();

    expect($template->is_system)->toBeTrue()
        ->and($template->is_default)->toBeTrue()
        ->and($template->columns()->count())->toBe(count(ExportAttendancePeriodPayrollCsvAction::defaultColumns()));
});

it('exports a closed period with a company custom column order and headers', function (): void {
    [$company, $user] = payrollTemplateCompanyUser();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $period = AttendancePeriod::factory()->create([
        'company_id' => $company->id,
        'center_id' => $center->id,
        'status' => AttendancePeriod::STATUS_CLOSED,
    ]);
    $template = app(SavePayrollExportTemplateAction::class)->handle($company, [
        'name' => 'Proveedor de prueba',
        'status' => PayrollExportTemplate::STATUS_ACTIVE,
        'is_default' => false,
        'delimiter' => ';',
        'columns' => [
            ['source_key' => 'numero_empleado', 'header' => 'CLAVE'],
            ['source_key' => 'horas_ordinarias', 'header' => 'HORAS NORMALES'],
        ],
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id]);

    $response = $this->get(route('attendance-periods.payroll-csv', ['attendancePeriod' => $period, 'template_id' => $template->id]));

    $response->assertOk();
    expect($response->streamedContent())->toContain('CLAVE;"HORAS NORMALES"');
});

it('exports a closed period as Excel using the selected company template', function (): void {
    [$company, $user] = payrollTemplateCompanyUser();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $period = AttendancePeriod::factory()->create([
        'company_id' => $company->id,
        'center_id' => $center->id,
        'status' => AttendancePeriod::STATUS_CLOSED,
    ]);
    $template = app(SavePayrollExportTemplateAction::class)->handle($company, [
        'name' => 'Excel proveedor',
        'status' => PayrollExportTemplate::STATUS_ACTIVE,
        'is_default' => false,
        'delimiter' => ',',
        'columns' => [
            ['source_key' => 'numero_empleado', 'header' => 'CLAVE'],
            ['source_key' => 'horas_ordinarias', 'header' => 'HORAS NORMALES'],
        ],
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->get(route('attendance-periods.payroll-xlsx', ['attendancePeriod' => $period, 'template_id' => $template->id]));

    $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'vera-time-xlsx-');
    file_put_contents($path, $response->streamedContent());

    try {
        $sheet = IOFactory::load($path)->getActiveSheet();

        expect($sheet->getCell('A1')->getValue())->toBe('CLAVE')
            ->and($sheet->getCell('B1')->getValue())->toBe('HORAS NORMALES');
    } finally {
        @unlink($path);
    }
});

it('saves a custom template from the configuration component', function (): void {
    [$company, $user] = payrollTemplateCompanyUser();
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('payroll-export-templates.index')
        ->call('openCreate')
        ->set('form.name', 'Layout del despacho')
        ->call('addColumn', 'numero_empleado')
        ->call('addColumn', 'horas_ordinarias')
        ->set('form.columns.1.header', 'HORAS BASE')
        ->set('form.is_default', true)
        ->call('save')
        ->assertSee('Plantilla de CSV guardada.');

    $this->assertDatabaseHas('payroll_export_templates', [
        'company_id' => $company->id,
        'name' => 'Layout del despacho',
        'is_default' => true,
    ]);
    $this->assertDatabaseHas('payroll_export_template_columns', [
        'source_key' => 'horas_ordinarias',
        'header' => 'HORAS BASE',
    ]);
});

it('does not resolve an export template from another company', function (): void {
    [$company, $user] = payrollTemplateCompanyUser();
    [$otherCompany] = payrollTemplateCompanyUser();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $period = AttendancePeriod::factory()->create(['company_id' => $company->id, 'center_id' => $center->id, 'status' => AttendancePeriod::STATUS_CLOSED]);
    $foreignTemplate = app(SavePayrollExportTemplateAction::class)->handle($otherCompany, [
        'name' => 'Solo otra empresa',
        'status' => PayrollExportTemplate::STATUS_ACTIVE,
        'is_default' => false,
        'delimiter' => ',',
        'columns' => [['source_key' => 'numero_empleado', 'header' => 'CLAVE']],
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->get(route('attendance-periods.payroll-csv', ['attendancePeriod' => $period, 'template_id' => $foreignTemplate->id]))
        ->assertNotFound();
});

function payrollTemplateCompanyUser(): array
{
    $company = Company::factory()->create(['status' => 'active']);
    $role = Role::query()->firstOrCreate(
        ['key' => RoleKey::RH_ADMIN],
        ['name' => 'RH administrador', 'description' => null, 'is_system' => true],
    );
    $user = User::factory()->create(['status' => 'active']);
    $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

    return [$company, $user];
}
