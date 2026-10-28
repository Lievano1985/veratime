<?php

use App\Domains\Attendance\Actions\CloseAttendancePeriodAction;
use App\Domains\Attendance\Actions\SavePayrollExportTemplateAction;
use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use App\Models\PayrollExportTemplate;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('redirects the retired CSV editor to the new layout builder without creating legacy templates', function (): void {
    [$company, $user] = payrollTemplateCompanyUser();
    $this->actingAs($user)->withSession(['current_company_id' => $company->id])
        ->get(route('payroll-export-templates.index'))->assertRedirect(route('export-layouts.index'));
    expect(PayrollExportTemplate::where('company_id', $company->id)->count())->toBe(0);
    expect(View::exists('livewire.payroll-export-templates.index'))->toBeFalse();
    $this->get(route('export-layouts.index'))->assertOk()->assertSee('Layouts de exportación')
        ->assertDontSee('href="'.route('payroll-export-templates.index').'"', false);
});

it('exports a closed period with a company custom column order and headers', function (): void {
    [$company, $user] = payrollTemplateCompanyUser();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $period = AttendancePeriod::factory()->create([
        'company_id' => $company->id,
        'center_id' => $center->id,
        'status' => AttendancePeriod::STATUS_OPEN,
    ]);
    $period = app(CloseAttendancePeriodAction::class)->handle($company, $period, $user);
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
        'status' => AttendancePeriod::STATUS_OPEN,
    ]);
    $period = app(CloseAttendancePeriodAction::class)->handle($company, $period, $user);
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
