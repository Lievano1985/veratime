<?php

namespace Tests\Feature\WorkDays;

use App\Models\Center;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Company;
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
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
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
