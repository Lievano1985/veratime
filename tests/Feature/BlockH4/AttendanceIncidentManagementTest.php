<?php

namespace Tests\Feature\BlockH4;

use App\Domains\Attendance\Actions\CreateAttendancePeriodAction;
use App\Domains\Attendance\Actions\ValidateAttendancePeriodForClosingAction;
use App\Domains\AttendanceIncidents\Actions\CreateAttendanceIncidentAction;
use App\Domains\WorkDays\Actions\CalculateWorkDayAction;
use App\Models\AttendanceIncident;
use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AttendanceIncidentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_can_create_attendance_incident_from_ui(): void
    {
        [$company, $user, $worker] = $this->companyUserWorker(RoleKey::RH);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('attendance-incidents.index')
            ->set('form.worker_id', (string) $worker->id)
            ->set('form.start_date', '2026-08-03')
            ->set('form.end_date', '2026-08-05')
            ->set('form.incident_type', AttendanceIncident::TYPE_VACATION)
            ->set('form.payment_status', AttendanceIncident::PAYMENT_PAID)
            ->set('form.reference', 'VAC-001')
            ->set('form.notes', 'Vacaciones autorizadas por RH.')
            ->call('createIncident')
            ->assertSee('Incidencia registrada');

        $this->assertDatabaseHas('attendance_incidents', [
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'incident_type' => AttendanceIncident::TYPE_VACATION,
            'payment_status' => AttendanceIncident::PAYMENT_PAID,
            'status' => AttendanceIncident::STATUS_APPROVED,
            'reference' => 'VAC-001',
        ]);
    }

    public function test_supervisor_cannot_access_attendance_incidents(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::SUPERVISOR);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->get(route('attendance-incidents.index'))->assertForbidden();
    }

    public function test_cannot_create_incident_for_worker_from_another_company(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::RH);
        [$otherCompany, , $otherWorker] = $this->companyUserWorker(RoleKey::RH);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('attendance-incidents.index')
            ->set('form.worker_id', (string) $otherWorker->id)
            ->set('form.start_date', '2026-08-03')
            ->set('form.end_date', '2026-08-03')
            ->set('form.incident_type', AttendanceIncident::TYPE_INCAPACITY)
            ->set('form.payment_status', AttendanceIncident::PAYMENT_PAID)
            ->call('createIncident')
            ->assertHasErrors(['form.worker_id']);

        $this->assertDatabaseMissing('attendance_incidents', [
            'company_id' => $otherCompany->id,
            'worker_id' => $otherWorker->id,
        ]);
    }

    public function test_attendance_incident_converts_scheduled_absence_into_calculated_absence(): void
    {
        [$company, $user, $worker, $relationship, $center] = $this->companyUserWorker(RoleKey::RH);
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'work_date' => '2026-08-04',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
        ]);

        app(CreateAttendanceIncidentAction::class)->handle($company, $user, [
            'worker_id' => $worker->id,
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-04',
            'incident_type' => AttendanceIncident::TYPE_JUSTIFIED_UNPAID_ABSENCE,
            'payment_status' => AttendanceIncident::PAYMENT_UNPAID,
            'reference' => 'RH-123',
            'notes' => 'Falta justificada sin goce.',
        ]);

        $calculation = app(CalculateWorkDayAction::class)->handle($company, $workDay, $user, reason: 'Prueba H4');

        $workDay->refresh();

        $this->assertNotNull($calculation);
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->status);
        $this->assertSame(0, $calculation->total_work_minutes);
        $this->assertSame(AttendanceIncident::TYPE_JUSTIFIED_UNPAID_ABSENCE, data_get($calculation->result_snapshot, 'attendance_incident.incident_type'));
        $this->assertSame(AttendanceIncident::PAYMENT_UNPAID, data_get($workDay->metadata, 'attendance_incident.payment_status'));
    }

    public function test_period_validation_does_not_block_approved_attendance_incident(): void
    {
        [$company, $user, $worker, $relationship, $center] = $this->companyUserWorker(RoleKey::RH);
        $period = app(CreateAttendancePeriodAction::class)->handle($company, $center, [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
        ], [], $user);
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'work_date' => '2026-08-04',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
        ]);

        app(CreateAttendanceIncidentAction::class)->handle($company, $user, [
            'worker_id' => $worker->id,
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-04',
            'incident_type' => AttendanceIncident::TYPE_INCAPACITY,
            'payment_status' => AttendanceIncident::PAYMENT_PAID,
        ]);
        app(CalculateWorkDayAction::class)->handle($company, $workDay, $user, reason: 'Prueba H4');

        $summary = app(ValidateAttendancePeriodForClosingAction::class)->handle($company, $period, $user);

        $this->assertTrue($summary['ready_to_close']);
        $this->assertSame(0, $summary['blockers']['total']);
        $this->assertSame(AttendancePeriod::STATUS_READY, $period->refresh()->status);
    }

    /**
     * @return array{0: Company, 1: User, 2: Worker, 3: EmploymentRelationship, 4: Center}
     */
    private function companyUserWorker(string $roleKey): array
    {
        [$company, $user] = $this->companyUser($roleKey);
        $center = Center::factory()->for($company)->create(['timezone' => 'America/Mexico_City']);
        $worker = Worker::factory()->for($company)->create([
            'status' => 'active',
            'employee_code' => 'VT-501',
            'full_name' => 'Persona Demo H4',
        ]);
        $relationship = EmploymentRelationship::factory()->for($company)->for($worker)->for($center)->create([
            'status' => 'active',
            'started_at' => '2026-01-01',
            'ended_at' => null,
        ]);

        return [$company, $user, $worker, $relationship, $center];
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
