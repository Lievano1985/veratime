<?php

namespace Tests\Feature\BlockH2;

use App\Domains\Attendance\Actions\CloseAttendancePeriodAction;
use App\Domains\Attendance\Actions\CreateAttendancePeriodAction;
use App\Domains\Attendance\Actions\ValidateAttendancePeriodForClosingAction;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AttendancePeriodClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_detects_work_day_blockers_and_keeps_period_open(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::RH);
        [$period, $worker] = $this->periodWithWorker($company, $user);
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $period->center_id,
            'work_date' => '2026-08-05',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
        ]);
        $this->openAlert($company, $workDay, $worker);

        $summary = app(ValidateAttendancePeriodForClosingAction::class)->handle($company, $period, $user);

        $this->assertFalse($summary['ready_to_close']);
        $this->assertSame(1, $summary['blockers']['total']);
        $this->assertSame(AttendancePeriod::STATUS_OPEN, $period->refresh()->status);
        $this->assertSame($user->id, $period->validated_by);
    }

    public function test_validation_ignores_closed_absence_alerts_and_rest_days(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::RH);
        [$period, $worker] = $this->periodWithWorker($company, $user);

        $absence = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $period->center_id,
            'work_date' => '2026-08-05',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
        ]);
        $this->closedAlert($company, $absence, $worker, Alert::STATUS_JUSTIFIED);

        WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $period->center_id,
            'work_date' => '2026-08-06',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'rest',
            'expected_work_minutes' => null,
            'valid_time_event_count' => 0,
        ]);

        $summary = app(ValidateAttendancePeriodForClosingAction::class)->handle($company, $period, $user);

        $this->assertTrue($summary['ready_to_close']);
        $this->assertSame(0, $summary['blockers']['open_alerts']);
        $this->assertSame(0, $summary['blockers']['unresolved_work_days']);
        $this->assertSame(AttendancePeriod::STATUS_READY, $period->refresh()->status);
    }

    public function test_period_closes_with_snapshot_hash_and_base_report_when_no_blockers_exist(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::ADMIN);
        [$period, $worker] = $this->periodWithWorker($company, $user);
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $period->center_id,
            'work_date' => '2026-08-05',
            'status' => WorkDay::STATUS_CALCULATED,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 2,
        ]);
        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id,
            'work_day_id' => $workDay->id,
            'ordinary_minutes' => 480,
            'overtime_minutes' => 61,
        ]);
        $workDay->forceFill(['active_calculation_id' => $calculation->id])->save();

        $closed = app(CloseAttendancePeriodAction::class)->handle($company, $period, $user);

        $this->assertSame(AttendancePeriod::STATUS_CLOSED, $closed->status);
        $this->assertSame($user->id, $closed->closed_by);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame('attendance_period.v1', $closed->snapshot_schema_version);
        $this->assertNotEmpty($closed->snapshot_canonical_json);
        $this->assertSame(hash('sha256', $closed->snapshot_canonical_json), $closed->snapshot_sha256);
        $this->assertSame(1, data_get($closed->report_summary, 'summary.workers_included'));
        $this->assertSame(480, data_get($closed->report_summary, 'summary.ordinary_minutes'));
        $this->assertSame(61, data_get($closed->report_summary, 'summary.overtime_minutes'));
    }

    public function test_ui_can_validate_and_close_ready_period(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::OWNER);
        [$period, $worker] = $this->periodWithWorker($company, $user);
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $period->center_id,
            'work_date' => '2026-08-05',
            'status' => WorkDay::STATUS_CALCULATED,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 2,
        ]);
        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id,
            'work_day_id' => $workDay->id,
            'ordinary_minutes' => 480,
        ]);
        $workDay->forceFill(['active_calculation_id' => $calculation->id])->save();

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('attendance-periods.index')
            ->call('validatePeriod', $period->id)
            ->assertSee('Periodo listo para cierre.')
            ->call('closePeriod', $period->id)
            ->assertSee('Periodo cerrado y reporte base generado.')
            ->assertSee('Reporte')
            ->assertSee('Ordinario');

        $this->assertSame(AttendancePeriod::STATUS_CLOSED, $period->refresh()->status);
    }

    public function test_supervisor_cannot_validate_or_close_periods(): void
    {
        [$company, $manager] = $this->companyUser(RoleKey::RH);
        [$period] = $this->periodWithWorker($company, $manager);
        $supervisor = User::factory()->create(['status' => 'active']);
        $role = Role::query()->firstOrCreate(
            ['key' => RoleKey::SUPERVISOR],
            ['name' => 'Supervisor', 'description' => null, 'is_system' => true],
        );
        $supervisor->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->assertFalse($supervisor->can('validateForClosing', $period));
        $this->assertFalse($supervisor->can('close', $period));
    }

    private function openAlert(Company $company, WorkDay $workDay, Worker $worker): Alert
    {
        $type = AlertType::query()->create([
            'code' => 'scheduled_absence',
            'name' => 'Falta',
            'description' => 'Jornada programada sin eventos validos.',
            'default_severity' => 'high',
            'category' => 'attendance',
            'status' => 'active',
        ]);

        return Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $type->id,
            'worker_id' => $worker->id,
            'work_day_id' => $workDay->id,
            'severity' => 'high',
            'status' => Alert::STATUS_NEW,
            'title' => 'Falta',
            'description' => 'Pendiente de dictamen.',
            'rule_code' => 'scheduled_absence',
            'detected_at' => now(),
            'fingerprint' => 'period-blocker-'.$workDay->id,
        ]);
    }

    private function closedAlert(Company $company, WorkDay $workDay, Worker $worker, string $status): Alert
    {
        $type = AlertType::query()->create([
            'code' => 'scheduled_absence',
            'name' => 'Falta',
            'description' => 'Jornada programada sin eventos validos.',
            'default_severity' => 'high',
            'category' => 'attendance',
            'status' => 'active',
        ]);

        return Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $type->id,
            'worker_id' => $worker->id,
            'work_day_id' => $workDay->id,
            'severity' => 'high',
            'status' => $status,
            'title' => 'Falta',
            'description' => 'Dictaminada.',
            'rule_code' => 'scheduled_absence',
            'detected_at' => now(),
            'resolution' => 'Dictamen aplicado.',
            'resolved_at' => now(),
            'fingerprint' => 'period-closed-alert-'.$workDay->id,
        ]);
    }

    /**
     * @return array{0: AttendancePeriod, 1: Worker}
     */
    private function periodWithWorker(Company $company, User $user): array
    {
        $center = Center::factory()->for($company)->create(['timezone' => 'America/Mexico_City']);
        $worker = Worker::factory()->for($company)->create([
            'employee_code' => 'VT-900',
            'full_name' => 'Demo Periodo',
        ]);
        $period = app(CreateAttendancePeriodAction::class)->handle($company, $center, [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
        ], [], $user);

        return [$period, $worker];
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $roleKey): array
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => ucfirst($roleKey), 'description' => null, 'is_system' => true],
        );
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $user = User::factory()->create(['status' => 'active']);

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return [$company, $user];
    }
}
