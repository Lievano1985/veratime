<?php

namespace Tests\Feature\EndToEnd;

use App\Domains\Alerts\Actions\ResolveAlertAction;
use App\Domains\Attendance\Actions\CloseAttendancePeriodAction;
use App\Domains\Attendance\Actions\CreateAttendancePeriodAction;
use App\Domains\Attendance\Actions\ValidateAttendancePeriodForClosingAction;
use App\Domains\AttendanceIncidents\Actions\CreateAttendanceIncidentAction;
use App\Domains\Companies\Actions\CreateCenterAction;
use App\Domains\Companies\Actions\CreateCompanyAction;
use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\CreateOrganizationalUnitAction;
use App\Domains\Scheduling\Actions\ApplyPlannerScheduleAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\PublishScheduleBatchAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\TimeRecords\Actions\RegisterManualTimeEventAction;
use App\Domains\WorkDays\Actions\ProcessCompanyWorkDaysAction;
use App\Domains\Workers\Actions\SaveWorkerWithEmploymentRelationshipAction;
use App\Models\Alert;
use App\Models\AttendanceIncident;
use App\Models\User;
use App\Models\WorkDay;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\AlertTypeSeeder;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CompanyToPayrollExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_company_setup_published_day_and_night_shifts_review_closure_and_frozen_exports(): void
    {
        Carbon::setTestNow('2026-11-03 23:00:00');
        CarbonImmutable::setTestNow('2026-11-03 23:00:00');
        $this->seed([RoleSeeder::class, LegalRuleSeeder::class, AlertTypeSeeder::class]);
        $actor = User::factory()->create(['status' => 'active']);
        $this->actingAs($actor);
        $company = app(CreateCompanyAction::class)->handle($actor, [
            'name' => 'Empresa prueba integral', 'tax_id' => 'E2E261101AA1', 'timezone' => 'America/Mexico_City',
        ]);
        $this->withSession(['current_company_id' => $company->id]);
        app(CurrentCompany::class)->set($company);
        $center = app(CreateCenterAction::class)->handle($company, ['code' => 'MATRIZ', 'name' => 'Matriz', 'timezone' => $company->timezone]);
        $unit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, ['code' => 'OPS', 'name' => 'Operación', 'type' => 'department']);
        $workers = [];
        foreach (['0001' => 'Ana Día', '0002' => 'Bruno Noche'] as $code => $name) {
            $worker = app(SaveWorkerWithEmploymentRelationshipAction::class)->handle($company, null, $center, [
                'employee_code' => $code, 'full_name' => $name, 'email' => $code.'@example.test', 'started_at' => '2026-11-01', 'status' => 'active',
            ], $actor);
            app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $worker->activeEmploymentRelationship, $unit, ['effective_from' => '2026-11-01']);
            $workers[] = $worker;
        }
        [$ana, $bruno] = $workers;
        $this->get(route('scheduling.planner'))->assertOk();
        $this->get(route('workers.index'))->assertOk()->assertSee('Ana Día')->assertSee('Bruno Noche');

        $day = app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'DIA', 'name' => 'Día con pausa'], [
            $this->segment('work', '08:00', '12:00', 0, 0, 1),
            $this->segment('break', '12:00', '13:00', 0, 0, 2),
            $this->segment('work', '13:00', '17:00', 0, 0, 3),
        ]);
        $night = app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'NOCHE', 'name' => 'Noche con pausa'], [
            $this->segment('work', '20:00', '00:00', 0, 1, 1),
            $this->segment('break', '00:00', '00:30', 1, 1, 2),
            $this->segment('work', '00:30', '04:00', 1, 1, 3),
        ]);
        $batch = app(CreateScheduleBatchAction::class)->handle($company, $center, ['period_start' => '2026-11-02'], $actor);
        foreach ([[$ana, $day, 2], [$bruno, $night, 1]] as [$worker, $shift, $workDays]) {
            app(ApplyPlannerScheduleAction::class)->handle($actor, $company, $batch, [
                'relationships' => [$worker->activeEmploymentRelationship->id], 'mode' => 'manual',
                'replace_days' => false, 'replace_habitual' => false,
                'rules' => array_map(fn ($i) => ['day' => $i, 'value' => $i <= $workDays ? (string) $shift->id : 'rest'], range(1, 7)),
            ]);
        }
        app(PublishScheduleBatchAction::class)->handle($actor, $company, $batch);
        $this->assertSame('published', $batch->fresh()->status);
        $this->assertNotEmpty($batch->fresh()->snapshot_sha256);

        app(CreateAttendanceIncidentAction::class)->handle($company, $actor, [
            'worker_id' => $ana->id, 'start_date' => '2026-11-03', 'end_date' => '2026-11-03',
            'incident_type' => AttendanceIncident::TYPE_PAID_PERMISSION, 'payment_status' => AttendanceIncident::PAYMENT_PAID,
            'reference' => 'PERMISO-INTEGRAL', 'notes' => 'Permiso autorizado para prueba integral',
        ]);
        foreach ([['clock_in', '08:00'], ['break_start', '12:00'], ['break_end', '13:00'], ['clock_out', '17:00']] as [$type, $time]) {
            $this->mark($company, $actor, $ana, $type, '2026-11-02', $time);
        }
        foreach ([['clock_in', '2026-11-02', '20:00'], ['break_start', '2026-11-03', '00:00'], ['break_end', '2026-11-03', '00:30']] as [$type, $date, $time]) {
            $this->mark($company, $actor, $bruno, $type, $date, $time);
        }
        app(ProcessCompanyWorkDaysAction::class)->handle($company, '2026-11-02', '2026-11-03', $center, $actor, onlyPendingOrStale: false);
        $period = app(CreateAttendancePeriodAction::class)->handle($company, $center, ['period_start' => '2026-11-02', 'period_end' => '2026-11-03'], [$unit->id], $actor);
        $validation = app(ValidateAttendancePeriodForClosingAction::class)->handle($company, $period, $actor);
        $this->assertFalse($validation['ready_to_close'], 'La salida nocturna faltante debe impedir cerrar.');

        $this->mark($company, $actor, $bruno, 'clock_out', '2026-11-03', '04:00');
        app(ProcessCompanyWorkDaysAction::class)->handle($company, '2026-11-02', '2026-11-03', $center, $actor, onlyPendingOrStale: false);
        $anaDay = WorkDay::where('worker_id', $ana->id)->whereDate('work_date', '2026-11-02')->firstOrFail();
        $nightDay = WorkDay::where('worker_id', $bruno->id)->whereDate('work_date', '2026-11-02')->firstOrFail();
        $this->assertSame(480, $anaDay->activeCalculation->total_work_minutes);
        $this->assertSame(60, $anaDay->activeCalculation->break_minutes);
        $this->assertSame(450, $nightDay->activeCalculation->total_work_minutes);
        $this->assertSame(30, $nightDay->activeCalculation->break_minutes);
        $this->assertSame('nocturnal', $nightDay->activeCalculation->classification);
        $this->assertSame(30, $nightDay->activeCalculation->overtime_minutes);
        $this->assertGreaterThan(1, $nightDay->calculations()->count());
        foreach (Alert::where('company_id', $company->id)->whereIn('status', Alert::OPEN_STATUSES)->get() as $alert) {
            app(ResolveAlertAction::class)->handle($company, $alert, $actor, ['status' => Alert::STATUS_JUSTIFIED, 'resolution' => 'Revisión documentada de prueba integral.']);
        }
        $this->assertTrue(app(ValidateAttendancePeriodForClosingAction::class)->handle($company, $period, $actor)['ready_to_close']);
        $closed = app(CloseAttendancePeriodAction::class)->handle($company, $period, $actor);
        $this->assertSame(hash('sha256', $closed->snapshot_canonical_json), $closed->snapshot_sha256);
        $snapshot = $closed->snapshot_canonical_json;
        $csv = $this->get(route('attendance-periods.payroll-csv', $period))->assertOk()->streamedContent();
        $excel = $this->excelRows($this->get(route('attendance-periods.payroll-xlsx', $period))->assertOk()->streamedContent());
        $this->assertStringContainsString('0001', $csv);
        $this->assertStringContainsString('Bruno Noche', $csv);
        $ana->update(['full_name' => 'Nombre posterior', 'employee_code' => 'CAMBIADO']);
        $this->assertSame($csv, $this->get(route('attendance-periods.payroll-csv', $period))->assertOk()->streamedContent());
        $this->assertSame($excel, $this->excelRows($this->get(route('attendance-periods.payroll-xlsx', $period))->assertOk()->streamedContent()));
        $this->assertSame($snapshot, $period->fresh()->snapshot_canonical_json);

        $other = app(CreateCompanyAction::class)->handle($actor, ['name' => 'Empresa ajena', 'timezone' => $company->timezone]);
        $this->withSession(['current_company_id' => $other->id]);
        app(CurrentCompany::class)->set($other);
        $this->get(route('attendance-periods.payroll-csv', $period))->assertNotFound();
        $this->get(route('attendance-periods.payroll-xlsx', $period))->assertNotFound();
    }

    private function segment(string $type, string $start, string $end, int $from, int $to, int $order): array
    {
        return ['segment_type' => $type, 'timing_mode' => 'fixed', 'start_local_time' => $start, 'end_local_time' => $end,
            'start_day_offset' => $from, 'end_day_offset' => $to, 'sort_order' => $order];
    }

    private function mark($company, $actor, $worker, string $type, string $date, string $time): void
    {
        app(RegisterManualTimeEventAction::class)->handle($company, $actor, $worker, [
            'event_type' => $type, 'occurred_local_date' => $date, 'occurred_local_time' => $time,
            'timezone' => $company->timezone, 'reason' => 'Captura justificada de prueba integral',
        ]);
    }

    private function excelRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'vera-e2e-');
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
