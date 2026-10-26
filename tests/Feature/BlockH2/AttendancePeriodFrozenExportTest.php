<?php

namespace Tests\Feature\BlockH2;

use App\Domains\Attendance\Actions\CloseAttendancePeriodAction;
use App\Domains\Attendance\Actions\ExportAttendancePeriodPayrollCsvAction;
use App\Domains\Attendance\Actions\ExportAttendancePeriodPayrollXlsxAction;
use App\Domains\Attendance\Actions\ResolveAttendancePeriodPayrollRowsAction;
use App\Domains\Attendance\Actions\SavePayrollExportTemplateAction;
use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AttendancePeriodFrozenExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_and_excel_keep_the_closed_data_after_calculation_identity_and_scope_changes(): void
    {
        [$company, $actor, $period, $day] = $this->fixture();
        $closed = app(CloseAttendancePeriodAction::class)->handle($company, $period, $actor);
        $before = $closed->getAttributes();
        $csv = app(ExportAttendancePeriodPayrollCsvAction::class)->handle($closed)->getCallback();
        ob_start();
        $csv();
        $originalCsv = ob_get_clean();
        $originalExcel = $this->excelRows($closed);
        $day->worker->update(['full_name' => 'Nombre cambiado después del cierre', 'employee_code' => 'CAMBIADO']);
        $company->update(['name' => 'Empresa renombrada']);
        $day->center->update(['name' => 'Centro renombrado']);
        $old = $day->activeCalculation;
        $next = $old->replicate();
        $next->forceFill(['version' => 2, 'ordinary_minutes' => 120, 'overtime_minutes' => 360])->save();
        $day->update(['active_calculation_id' => $next->id, 'first_event_at_utc' => '2026-08-05 19:00:00']);
        WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $day->worker_id,
            'employment_relationship_id' => $day->employment_relationship_id, 'center_id' => $day->center_id,
            'work_date' => '2026-08-06']);
        ob_start();
        app(ExportAttendancePeriodPayrollCsvAction::class)->handle($closed)->sendContent();
        $afterCsv = ob_get_clean();
        $this->assertSame($originalCsv, $afterCsv);
        $this->assertSame($originalExcel, $this->excelRows($closed));
        $this->assertStringContainsString('HISTORICO-001', $afterCsv);
        $this->assertStringNotContainsString('CAMBIADO', $afterCsv);
        $this->assertSame($before, $closed->fresh()->getAttributes());
        $this->assertSame(1, substr_count($afterCsv, 'HISTORICO-001'));
    }

    public function test_custom_templates_select_from_frozen_values_and_keep_csv_formula_protection(): void
    {
        [$company, $actor, $period, $day] = $this->fixture();
        $day->worker->update(['full_name' => '=SUM(1,2)']);
        $closed = app(CloseAttendancePeriodAction::class)->handle($company, $period, $actor);
        $template = app(SavePayrollExportTemplateAction::class)->handle($company, [
            'name' => 'Proveedor', 'status' => 'active', 'is_default' => false, 'delimiter' => ';',
            'columns' => [['source_key' => 'horas_extra_totales', 'header' => 'EXTRA'], ['source_key' => 'nombre_empleado', 'header' => 'NOMBRE']],
        ]);
        $day->activeCalculation->update(['overtime_minutes' => 0]);
        $day->worker->update(['full_name' => 'CAMBIADO']);
        ob_start();
        app(ExportAttendancePeriodPayrollCsvAction::class)->handle($closed, $template)->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('EXTRA;NOMBRE', $csv);
        $this->assertStringContainsString("1.00;'=SUM(1,2)", $csv);
        $this->assertStringNotContainsString('CAMBIADO', $csv);
    }

    public function test_legacy_closures_are_not_reconstructed_from_current_data(): void
    {
        [$company, $actor, $period] = $this->fixture();
        $period->update(['status' => AttendancePeriod::STATUS_CLOSED, 'snapshot_schema_version' => 'attendance_period.v1',
            'snapshot_canonical_json' => '{"schema_version":1,"report":{"summary":{"ordinary_minutes":480}}}',
            'snapshot_sha256' => hash('sha256', '{"schema_version":1,"report":{"summary":{"ordinary_minutes":480}}}')]);
        $before = $period->fresh()->getAttributes();
        foreach ([ExportAttendancePeriodPayrollCsvAction::class, ExportAttendancePeriodPayrollXlsxAction::class] as $action) {
            try {
                app($action)->handle($period);
                $this->fail('A legacy closure must not export live data.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('no conserva el detalle histórico', $exception->getMessage());
            }
        }
        $this->assertFalse($period->hasFrozenPayrollExport());
        $this->assertSame($before, $period->fresh()->getAttributes());
    }

    public function test_tampered_or_cross_company_snapshots_are_rejected_before_download(): void
    {
        [$company, $actor, $period] = $this->fixture();
        $closed = app(CloseAttendancePeriodAction::class)->handle($company, $period, $actor);
        $json = $closed->snapshot_canonical_json;
        $hash = $closed->snapshot_sha256;
        $closed->update(['snapshot_canonical_json' => $json.' ']);
        try {
            app(ExportAttendancePeriodPayrollCsvAction::class)->handle($closed);
            $this->fail('Tampered snapshot must be rejected before streaming.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('integridad', $exception->getMessage());
        }
        $foreign = AttendancePeriod::factory()->create(['status' => AttendancePeriod::STATUS_CLOSED,
            'snapshot_schema_version' => 'attendance_period.v2', 'snapshot_canonical_json' => $json, 'snapshot_sha256' => $hash]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no corresponde a esta empresa y periodo');
        app(ResolveAttendancePeriodPayrollRowsAction::class)->handle($foreign);
    }

    public function test_invalid_export_data_rolls_back_the_entire_close(): void
    {
        [$company, $actor, $period, $day] = $this->fixture();
        $day->worker->update(['metadata' => ['nss' => ['invalid']]]);
        try {
            app(CloseAttendancePeriodAction::class)->handle($company, $period, $actor);
            $this->fail('Invalid rows must not produce a partially closed period.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('dato de trabajador o jornada inválido', $exception->getMessage());
        }
        $period->refresh();
        $this->assertSame(AttendancePeriod::STATUS_OPEN, $period->status);
        $this->assertNull($period->closed_at);
        $this->assertNull($period->snapshot_canonical_json);
        $this->assertNull($period->snapshot_sha256);
    }

    public function test_legacy_period_screen_preserves_the_summary_and_explains_unavailable_exports(): void
    {
        [$company, $actor, $period] = $this->fixture();
        $period->update(['status' => AttendancePeriod::STATUS_CLOSED, 'snapshot_schema_version' => 'attendance_period.v1',
            'report_summary' => ['summary' => ['workers_included' => 1], 'workers' => []]]);
        $this->actingAs($actor)->withSession(['current_company_id' => $company->id]);
        Volt::test('attendance-periods.index')->call('selectPeriod', $period->id)
            ->assertSee('Cierre anterior: solo resumen histórico.')
            ->assertSee('no el detalle histórico para generar CSV o Excel')
            ->assertDontSee('Descargar CSV')->assertDontSee('Descargar Excel');
    }

    private function fixture(): array
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create();
        $actor = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['key' => RoleKey::ADMIN_EMPRESA], ['name' => 'Administrador']);
        $actor->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active']);
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create(['center_id' => $center->id]);
        $relationship->worker->update(['employee_code' => 'HISTORICO-001']);
        $period = AttendancePeriod::factory()->create(['company_id' => $company->id, 'center_id' => $center->id,
            'period_start' => '2026-08-03', 'period_end' => '2026-08-09']);
        $day = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $relationship->worker_id,
            'employment_relationship_id' => $relationship->id, 'center_id' => $center->id, 'work_date' => '2026-08-05',
            'status' => WorkDay::STATUS_CALCULATED, 'valid_time_event_count' => 2,
            'first_event_at_utc' => '2026-08-05 13:00:00', 'last_event_at_utc' => '2026-08-05 22:00:00']);
        $calculation = WorkDayCalculation::factory()->create(['company_id' => $company->id, 'work_day_id' => $day->id,
            'total_work_minutes' => 540, 'ordinary_minutes' => 480, 'overtime_minutes' => 60,
            'classification' => WorkDayCalculation::CLASSIFICATION_DIURNAL]);
        $day->update(['active_calculation_id' => $calculation->id]);

        return [$company, $actor, $period, $day->fresh(['activeCalculation', 'worker', 'center'])];
    }

    private function excelRows(AttendancePeriod $period): array
    {
        ob_start();
        app(ExportAttendancePeriodPayrollXlsxAction::class)->handle($period)->sendContent();
        $content = ob_get_clean();
        $path = tempnam(sys_get_temp_dir(), 'vera-frozen-export-');
        file_put_contents($path, $content);
        try {
            $book = IOFactory::load($path);
            $rows = $book->getActiveSheet()->toArray();
            $book->disconnectWorksheets();

            return $rows;
        } finally {
            unlink($path);
        }
    }
}
