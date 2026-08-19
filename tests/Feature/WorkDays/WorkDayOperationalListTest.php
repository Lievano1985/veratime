<?php

namespace Tests\Feature\WorkDays;

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Alerts\Actions\ResolveAlertAction;
use App\Models\Center;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkDayOperationalListTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_work_days_list(): void
    {
        [$company, $user, $workDay] = $this->companyUserAndWorkDay(RoleKey::ADMIN_EMPRESA);
        $normalWorkDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $workDay->worker_id,
            'center_id' => $workDay->center_id,
            'work_date' => '2026-08-04',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_CALCULATED,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 2,
        ]);
        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id,
            'work_day_id' => $normalWorkDay->id,
            'status' => WorkDayCalculation::STATUS_ACTIVE,
            'total_work_minutes' => 480,
        ]);
        $normalWorkDay->forceFill(['active_calculation_id' => $calculation->id])->save();

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index', ['from' => '2026-08-03', 'to' => '2026-08-04']))
            ->assertOk()
            ->assertSee('Jornadas')
            ->assertSee($workDay->worker->employee_code)
            ->assertSee('Programada')
            ->assertSee('Incidencia')
            ->assertSee('Falta')
            ->assertSee('Ver')
            ->assertDontSee('Alertas');
    }

    public function test_work_days_list_filters_by_company_and_search(): void
    {
        [$company, $user, $workDay] = $this->companyUserAndWorkDay(RoleKey::RH_ADMIN, 'ANA', 'Ana Demo Lopez');
        [$otherCompany] = $this->companyUserAndWorkDay(RoleKey::RH_ADMIN, 'BRU', 'Bruno Demo Perez');

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index', ['from' => '2026-08-03', 'to' => '2026-08-03', 'search' => 'ANA']))
            ->assertOk()
            ->assertSee($workDay->worker->full_name)
            ->assertDontSee('Bruno Demo Perez');

        $this->assertSame(1, WorkDay::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, WorkDay::query()->where('company_id', $otherCompany->id)->count());
    }

    public function test_work_days_list_can_show_only_rows_with_incidents(): void
    {
        [$company, $user, $workDay] = $this->companyUserAndWorkDay(RoleKey::ADMIN_EMPRESA, 'ANA', 'Ana Demo Lopez');
        $normalWorker = Worker::factory()->create([
            'company_id' => $company->id,
            'employee_code' => 'NOR',
            'full_name' => 'Bruno Normal',
        ]);
        $normalWorkDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $normalWorker->id,
            'center_id' => $workDay->center_id,
            'work_date' => '2026-08-04',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_CALCULATED,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 2,
        ]);
        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id,
            'work_day_id' => $normalWorkDay->id,
            'status' => WorkDayCalculation::STATUS_ACTIVE,
            'total_work_minutes' => 480,
        ]);
        $normalWorkDay->forceFill(['active_calculation_id' => $calculation->id])->save();

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index', ['from' => '2026-08-03', 'to' => '2026-08-04', 'incident' => 'with_incidents']))
            ->assertOk()
            ->assertSee('Todas')
            ->assertSee('Solo con incidencia')
            ->assertSee('Falta')
            ->assertSee('2026-08-03')
            ->assertDontSee('Bruno Normal');
    }

    public function test_work_days_list_caps_future_dates_to_company_today(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 12:00:00', 'America/Mexico_City'));

        try {
            [$company, $user, $workDay] = $this->companyUserAndWorkDay(RoleKey::RH_ADMIN, 'ANA', 'Ana Demo Lopez');

            foreach (['2026-08-07', '2026-08-08', '2026-08-09'] as $date) {
                WorkDay::factory()->create([
                    'company_id' => $company->id,
                    'worker_id' => $workDay->worker_id,
                    'center_id' => $workDay->center_id,
                    'work_date' => $date,
                    'timezone' => 'America/Mexico_City',
                    'status' => WorkDay::STATUS_PENDING,
                    'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
                    'day_type' => $date === '2026-08-07' ? 'shift' : 'rest',
                    'expected_work_minutes' => $date === '2026-08-07' ? 480 : null,
                    'valid_time_event_count' => 0,
                ]);
            }

            $this->actingAs($user)
                ->withSession(['current_company_id' => $company->id])
                ->get(route('work-days.index', ['from' => '2026-08-03', 'to' => '2026-08-09']))
                ->assertOk()
                ->assertSee('2026-08-07')
                ->assertDontSee('2026-08-08')
                ->assertDontSee('2026-08-09');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_today_absence_candidates_are_hidden_until_day_has_passed(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 12:00:00', 'America/Mexico_City'));

        try {
            [$company, $user, $baseWorkDay] = $this->companyUserAndWorkDay(RoleKey::RH_ADMIN, 'ANA', 'Ana Demo Lopez');

            $todayWithoutCheckout = Worker::factory()->create([
                'company_id' => $company->id,
                'employee_code' => 'VT-002',
                'full_name' => 'Bruno Sin Salida',
            ]);
            WorkDay::factory()->create([
                'company_id' => $company->id,
                'worker_id' => $todayWithoutCheckout->id,
                'center_id' => $baseWorkDay->center_id,
                'work_date' => '2026-08-07',
                'timezone' => 'America/Mexico_City',
                'status' => WorkDay::STATUS_PENDING,
                'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
                'day_type' => 'shift',
                'expected_work_minutes' => 480,
                'valid_time_event_count' => 0,
            ]);

            $todayWithCheckout = Worker::factory()->create([
                'company_id' => $company->id,
                'employee_code' => 'VT-003',
                'full_name' => 'Carla Con Salida',
            ]);
            $calculatedToday = WorkDay::factory()->create([
                'company_id' => $company->id,
                'worker_id' => $todayWithCheckout->id,
                'center_id' => $baseWorkDay->center_id,
                'work_date' => '2026-08-07',
                'timezone' => 'America/Mexico_City',
                'status' => WorkDay::STATUS_CALCULATED,
                'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
                'day_type' => 'shift',
                'expected_work_minutes' => 480,
                'valid_time_event_count' => 2,
            ]);
            $calculation = WorkDayCalculation::factory()->create([
                'company_id' => $company->id,
                'work_day_id' => $calculatedToday->id,
                'status' => WorkDayCalculation::STATUS_ACTIVE,
                'total_work_minutes' => 480,
            ]);
            $calculatedToday->forceFill(['active_calculation_id' => $calculation->id])->save();

            $yesterdayWithoutEvents = Worker::factory()->create([
                'company_id' => $company->id,
                'employee_code' => 'VT-004',
                'full_name' => 'Diego Dia Cerrado',
            ]);
            WorkDay::factory()->create([
                'company_id' => $company->id,
                'worker_id' => $yesterdayWithoutEvents->id,
                'center_id' => $baseWorkDay->center_id,
                'work_date' => '2026-08-06',
                'timezone' => 'America/Mexico_City',
                'status' => WorkDay::STATUS_PENDING,
                'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
                'day_type' => 'shift',
                'expected_work_minutes' => 480,
                'valid_time_event_count' => 0,
            ]);

            $this->actingAs($user)
                ->withSession(['current_company_id' => $company->id])
                ->get(route('work-days.index', ['from' => '2026-08-06', 'to' => '2026-08-07']))
                ->assertOk()
                ->assertSee('Carla Con Salida')
                ->assertSee('Diego Dia Cerrado')
                ->assertDontSee('Bruno Sin Salida');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_work_days_list_stacks_visible_incidents_and_hides_closed_not_applicable_alerts(): void
    {
        [$company, $user, $workDay] = $this->companyUserAndWorkDay(RoleKey::RH_ADMIN, 'ANA', 'Ana Demo Lopez');

        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id,
            'work_day_id' => $workDay->id,
            'status' => WorkDayCalculation::STATUS_ACTIVE,
            'total_work_minutes' => 541,
            'ordinary_minutes' => 480,
            'overtime_minutes' => 61,
        ]);

        $workDay->forceFill([
            'active_calculation_id' => $calculation->id,
            'status' => WorkDay::STATUS_CALCULATED,
            'valid_time_event_count' => 2,
        ])->save();

        $absenceAlertType = AlertType::query()->create([
            'code' => 'scheduled_absence',
            'name' => 'Falta',
            'description' => 'Jornada programada sin eventos validos.',
            'default_severity' => 'high',
            'category' => 'attendance',
            'status' => 'active',
        ]);

        Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $absenceAlertType->id,
            'worker_id' => $workDay->worker_id,
            'work_day_id' => $workDay->id,
            'work_day_calculation_id' => $calculation->id,
            'severity' => 'high',
            'status' => Alert::STATUS_CLOSED,
            'title' => 'Falta',
            'description' => 'La jornada estaba programada y no tenia eventos validos de asistencia.',
            'rule_code' => 'scheduled_absence',
            'detected_at' => '2026-08-06 21:35:00',
            'resolution' => 'Cerrada automaticamente por recalculo.',
            'resolved_at' => '2026-08-07 17:46:00',
            'fingerprint' => 'closed-absence-demo',
        ]);

        $overtimeAlertType = AlertType::query()->create([
            'code' => 'overtime_detected',
            'name' => 'Tiempo extra detectado',
            'description' => 'La jornada tiene minutos extraordinarios.',
            'default_severity' => 'warning',
            'category' => 'overtime',
            'status' => 'active',
        ]);

        Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $overtimeAlertType->id,
            'worker_id' => $workDay->worker_id,
            'work_day_id' => $workDay->id,
            'work_day_calculation_id' => $calculation->id,
            'severity' => 'warning',
            'status' => Alert::STATUS_JUSTIFIED,
            'title' => 'Tiempo extra detectado',
            'description' => 'Se calcularon 61 minutos extraordinarios.',
            'rule_code' => 'overtime_detected',
            'detected_at' => '2026-08-07 11:56:00',
            'resolution' => 'Hora extra aprobada.',
            'resolved_by' => $user->id,
            'resolved_at' => '2026-08-07 11:57:00',
            'fingerprint' => 'approved-overtime-demo',
        ]);

        $incompleteAlertType = AlertType::query()->create([
            'code' => 'incomplete_work_day',
            'name' => 'Jornada incompleta',
            'description' => 'La jornada tiene eventos, pero requiere revision por secuencia incompleta.',
            'default_severity' => 'high',
            'category' => 'attendance',
            'status' => 'active',
        ]);

        Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $incompleteAlertType->id,
            'worker_id' => $workDay->worker_id,
            'work_day_id' => $workDay->id,
            'work_day_calculation_id' => $calculation->id,
            'severity' => 'high',
            'status' => Alert::STATUS_NEW,
            'title' => 'Jornada incompleta',
            'description' => 'La jornada tiene eventos validos, pero requiere revision por secuencia incompleta.',
            'rule_code' => 'incomplete_work_day',
            'detected_at' => '2026-08-07 11:58:00',
            'fingerprint' => 'open-incomplete-demo',
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index', ['from' => '2026-08-03', 'to' => '2026-08-03']))
            ->assertOk()
            ->assertSee('Tiempo extra detectado')
            ->assertSee('Jornada incompleta')
            ->assertDontSee('La jornada estaba programada y no tenia eventos validos de asistencia.')
            ->assertSee('Dictaminada');
    }

    public function test_work_days_list_does_not_duplicate_absence_badge_when_alert_and_candidate_match(): void
    {
        [$company, $user, $workDay] = $this->companyUserAndWorkDay(RoleKey::RH_ADMIN, 'ANA', 'Ana Demo Lopez');

        $absenceAlertType = AlertType::query()->create([
            'code' => 'scheduled_absence',
            'name' => 'Falta',
            'description' => 'Jornada programada sin eventos validos.',
            'default_severity' => 'high',
            'category' => 'attendance',
            'status' => 'active',
        ]);

        Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $absenceAlertType->id,
            'worker_id' => $workDay->worker_id,
            'work_day_id' => $workDay->id,
            'severity' => 'high',
            'status' => Alert::STATUS_NEW,
            'title' => 'Falta',
            'description' => 'La jornada estaba programada y no tenia eventos validos de asistencia.',
            'rule_code' => 'scheduled_absence',
            'detected_at' => '2026-08-04 09:00:00',
            'fingerprint' => 'open-absence-demo',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index', ['from' => '2026-08-03', 'to' => '2026-08-03']))
            ->assertOk();

        $this->assertSame(2, substr_count((string) $response->getContent(), '>Falta<'));
    }

    public function test_supervisor_without_manager_permission_cannot_view_work_days_list(): void
    {
        [$company, $user] = $this->companyUserAndWorkDay(RoleKey::SUPERVISOR);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index'))
            ->assertForbidden();
    }

    public function test_rh_operativo_can_view_and_resolve_work_day_alerts_only_inside_assigned_center(): void
    {
        [$company, $user, $workDay] = $this->companyUserAndWorkDay(RoleKey::RH_OPERATIVO, 'ANA', 'Ana Centro Permitido');
        app(AssignOperationalScopeAction::class)->handle($company, $user, [
            'effective_from' => '2026-08-01',
            'reason' => 'RH operativo por centro completo',
        ], center: $workDay->center);

        $this->assertTrue($user->can('viewAny', [WorkDay::class, $company]));

        $otherCenter = Center::factory()->create(['company_id' => $company->id, 'name' => 'Centro fuera de alcance']);
        $otherWorker = Worker::factory()->create([
            'company_id' => $company->id,
            'employee_code' => 'OUT',
            'full_name' => 'Bruno Fuera Centro',
        ]);
        WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $otherWorker->id,
            'center_id' => $otherCenter->id,
            'work_date' => '2026-08-03',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
        ]);

        $alertType = AlertType::query()->create([
            'code' => 'incomplete_work_day',
            'name' => 'Jornada incompleta',
            'description' => 'La jornada requiere revision.',
            'default_severity' => 'high',
            'category' => 'attendance',
            'status' => 'active',
        ]);
        $alert = Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $alertType->id,
            'worker_id' => $workDay->worker_id,
            'work_day_id' => $workDay->id,
            'severity' => 'high',
            'status' => Alert::STATUS_NEW,
            'title' => 'Jornada incompleta',
            'description' => 'La jornada requiere revision.',
            'rule_code' => 'incomplete_work_day',
            'detected_at' => '2026-08-04 09:00:00',
            'fingerprint' => 'rh-operativo-alert-demo',
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->get(route('work-days.index', ['from' => '2026-08-03', 'to' => '2026-08-03']))
            ->assertOk()
            ->assertSee('Ana Centro Permitido')
            ->assertDontSee('Bruno Fuera Centro');

        $this->assertTrue($user->can('resolve', $alert));

        app(ResolveAlertAction::class)->handle($company, $alert, $user, [
            'status' => Alert::STATUS_JUSTIFIED,
            'resolution' => 'Validado por RH operativo del centro',
        ]);

        $alert->refresh();
        $this->assertSame(Alert::STATUS_JUSTIFIED, $alert->status);
        $this->assertSame($user->id, $alert->resolved_by);
    }


    public function test_supervisor_with_unit_scope_can_consult_only_scoped_work_days_without_resolving_alerts(): void
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $center = Center::factory()->create(['company_id' => $company->id, 'name' => 'Centro Operativo']);
        $allowedUnit = OrganizationalUnit::factory()->forCenter($center)->create(['name' => 'Area Permitida']);
        $blockedUnit = OrganizationalUnit::factory()->forCenter($center)->create(['name' => 'Area Fuera']);
        $supervisor = $this->userWithRole($company, RoleKey::SUPERVISOR);

        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, [
            'effective_from' => '2026-08-01',
            'reason' => 'Supervisor de unidad',
        ], unit: $allowedUnit);

        $allowedWorker = Worker::factory()->create([
            'company_id' => $company->id,
            'employee_code' => 'IN-001',
            'full_name' => 'Ana Dentro Alcance',
        ]);
        $allowedRelationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $allowedWorker->id,
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'status' => 'active',
        ]);
        EmploymentUnitAssignment::forceCreate([
            'company_id' => $company->id,
            'employment_relationship_id' => $allowedRelationship->id,
            'organizational_unit_id' => $allowedUnit->id,
            'assignment_type' => 'primary',
            'effective_from' => '2026-08-01',
            'effective_to' => null,
            'status' => 'active',
            'source' => 'manual',
            'reason' => null,
            'metadata' => [],
        ]);

        $blockedWorker = Worker::factory()->create([
            'company_id' => $company->id,
            'employee_code' => 'OUT-001',
            'full_name' => 'Bruno Fuera Alcance',
        ]);
        $blockedRelationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $blockedWorker->id,
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'status' => 'active',
        ]);
        EmploymentUnitAssignment::forceCreate([
            'company_id' => $company->id,
            'employment_relationship_id' => $blockedRelationship->id,
            'organizational_unit_id' => $blockedUnit->id,
            'assignment_type' => 'primary',
            'effective_from' => '2026-08-01',
            'effective_to' => null,
            'status' => 'active',
            'source' => 'manual',
            'reason' => null,
            'metadata' => [],
        ]);

        $allowedWorkDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $allowedWorker->id,
            'employment_relationship_id' => $allowedRelationship->id,
            'center_id' => $center->id,
            'work_date' => '2026-08-03',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
        ]);
        WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $blockedWorker->id,
            'employment_relationship_id' => $blockedRelationship->id,
            'center_id' => $center->id,
            'work_date' => '2026-08-03',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
        ]);

        $alertType = AlertType::query()->create([
            'code' => 'scheduled_absence',
            'name' => 'Falta',
            'description' => 'Jornada programada sin eventos validos.',
            'default_severity' => 'high',
            'category' => 'attendance',
            'status' => 'active',
        ]);
        $alert = Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $alertType->id,
            'worker_id' => $allowedWorker->id,
            'work_day_id' => $allowedWorkDay->id,
            'severity' => 'high',
            'status' => Alert::STATUS_NEW,
            'title' => 'Falta',
            'description' => 'Jornada programada sin eventos validos.',
            'rule_code' => 'scheduled_absence',
            'detected_at' => '2026-08-04 09:00:00',
            'fingerprint' => 'supervisor-read-only-alert',
        ]);

        $this->assertTrue($supervisor->can('viewAny', [WorkDay::class, $company]));
        $this->assertTrue($supervisor->can('view', $allowedWorkDay));
        $this->assertFalse($supervisor->can('resolve', $alert));

        $this->actingAs($supervisor)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index', ['from' => '2026-08-03', 'to' => '2026-08-03']))
            ->assertOk()
            ->assertSee('Ana Dentro Alcance')
            ->assertDontSee('Bruno Fuera Alcance')
            ->assertSee(route('kiosk.index'), false)
            ->assertDontSee(route('scheduling.shifts'), false)
            ->assertDontSee(route('time-events.manual'), false)
            ->assertSee('Ver')
            ->assertDontSee('Dictaminar');
    }

    private function userWithRole(Company $company, string $roleKey): User
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => ucfirst($roleKey), 'description' => null, 'is_system' => true],
        );
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return $user;
    }
    private function companyUserAndWorkDay(string $roleKey, string $code = 'VT-001', string $name = 'Ana Demo Lopez'): array
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => ucfirst($roleKey), 'description' => null, 'is_system' => true],
        );
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $user = User::factory()->create();
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);
        $center = Center::factory()->create(['company_id' => $company->id]);
        $worker = Worker::factory()->create([
            'company_id' => $company->id,
            'employee_code' => $code,
            'full_name' => $name,
        ]);
        $relationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'status' => 'active',
        ]);
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'work_date' => '2026-08-03',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
        ]);

        return [$company, $user, $workDay->refresh()];
    }
}
