<?php

namespace Tests\Feature\Dashboard;

use App\Domains\Dashboard\Actions\BuildOperationalDashboardAction;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildOperationalDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_only_the_active_company_data(): void
    {
        $date = CarbonImmutable::now('America/Mexico_City')->toDateString();
        $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $otherCompany = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        [$worker, $relationship, $center] = $this->activeRelationship($company, $date);
        $this->validEvent($company, $worker, $relationship, $center, 'clock_in', 'web', $date, '08:00:00');
        $this->validEvent($company, $worker, $relationship, $center, 'manual_entry', 'admin_manual', $date, '08:05:00');
        $completedWorkDay = $this->workDay($company, $worker, $relationship, $center, $date);
        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id,
            'work_day_id' => $completedWorkDay->id,
            'status' => WorkDayCalculation::STATUS_ACTIVE,
            'total_work_minutes' => 480,
        ]);
        $completedWorkDay->update([
            'status' => WorkDay::STATUS_WITH_ALERTS,
            'active_calculation_id' => $calculation->id,
        ]);

        [$otherWorker, $otherRelationship, $otherCenter] = $this->activeRelationship($otherCompany, $date);
        $this->validEvent($otherCompany, $otherWorker, $otherRelationship, $otherCenter, 'clock_in', 'web', $date, '08:00:00');

        $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, $date);

        $taskKeys = collect($summary['pending_alerts'])->pluck('key');
        $this->assertSame($taskKeys->count(), $taskKeys->unique()->count());
        $this->assertTrue($taskKeys->contains('daily_limit_exceeded'));
        $this->assertFalse($taskKeys->contains('long_work_day'));
        $this->assertFalse($taskKeys->contains('twelve_hours_exceeded'));
        $this->assertFalse($taskKeys->contains('daily_overtime_over_three_hours'));
        $this->assertSame(1, $summary['metrics']['active_workers']);
        $this->assertSame(1, $summary['metrics']['working_now']);
        $this->assertSame(1, $summary['metrics']['completed_work_days']);
        $this->assertSame(1, $summary['segments']['evidence']);
    }

    public function test_dashboard_does_not_carry_an_unfinished_daytime_event_into_today(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-08 10:00:00', 'America/Mexico_City'));

        try {
            $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
            $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
            $user = User::factory()->create(['status' => 'active']);
            $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
            [$worker, $relationship, $center] = $this->activeRelationship($company, '2026-10-08');

            $this->validEvent($company, $worker, $relationship, $center, 'break_end', 'web', '2026-10-07', '17:00:00');

            $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, '2026-10-08');

            $this->assertSame(0, $summary['metrics']['working_now']);
            $this->assertSame(0, $summary['metrics']['on_break']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_dashboard_historical_date_does_not_show_live_attendance_states(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 10:00:00', 'America/Mexico_City'));

        try {
            $date = '2026-10-01';
            $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
            $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
            $user = User::factory()->create(['status' => 'active']);
            $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
            [$worker, $relationship, $center] = $this->activeRelationship($company, $date);
            $this->validEvent($company, $worker, $relationship, $center, 'clock_in', 'web', $date, '08:00:00');
            DailyScheduleAssignment::factory()->create([
                'company_id' => $company->id,
                'employment_relationship_id' => $relationship->id,
                'work_date' => $date,
                'day_type' => 'shift',
            ]);

            $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, $date);

            $this->assertSame(0, $summary['metrics']['working_now']);
            $this->assertSame(0, $summary['metrics']['on_break']);
            $this->assertSame(0, $summary['metrics']['pending_entry']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_dashboard_carries_an_open_overnight_shift_into_today(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-08 01:00:00', 'America/Mexico_City'));

        try {
            $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
            $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
            $user = User::factory()->create(['status' => 'active']);
            $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
            [$worker, $relationship, $center] = $this->activeRelationship($company, '2026-10-08');
            $assignment = DailyScheduleAssignment::factory()->create([
                'company_id' => $company->id,
                'employment_relationship_id' => $relationship->id,
                'work_date' => '2026-10-07',
                'day_type' => 'shift',
            ]);
            DailyScheduleSegment::factory()->create([
                'company_id' => $company->id,
                'daily_schedule_assignment_id' => $assignment->id,
                'starts_at_utc' => CarbonImmutable::parse('2026-10-07 22:00:00', 'America/Mexico_City')->utc(),
                'ends_at_utc' => CarbonImmutable::parse('2026-10-08 06:00:00', 'America/Mexico_City')->utc(),
            ]);
            $this->validEvent($company, $worker, $relationship, $center, 'clock_in', 'web', '2026-10-07', '22:00:00');

            $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, '2026-10-08');

            $this->assertSame(1, $summary['metrics']['working_now']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_dashboard_builds_annual_type_trends_from_the_first_recorded_week(): void
    {
        $date = '2026-10-08';
        $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $otherCompany = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $type = AlertType::query()->create([
            'code' => 'late_arrival_detected',
            'name' => 'Retardo',
            'description' => 'Retardo calculado.',
            'default_severity' => AlertType::SEVERITY_WARNING,
            'category' => 'daily',
            'status' => AlertType::STATUS_ACTIVE,
        ]);

        $this->incident($company, $type, '2026-09-14 12:00:00', Alert::STATUS_CLOSED, 'retardo-cerrado');
        $this->incident($company, $type, '2026-09-15 12:00:00', Alert::STATUS_NEW, 'retardo-abierto');
        $this->incident($otherCompany, $type, '2026-09-14 12:00:00', Alert::STATUS_CLOSED, 'retardo-ajeno');

        CarbonImmutable::setTestNow('2026-10-08 12:00:00', 'America/Mexico_City');

        try {
            $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, $date);

            $this->assertSame('Retardo', $summary['incidence_trends']['series'][0]['label']);
            $this->assertSame(2, $summary['incidence_trends']['series'][0]['total']);
            $this->assertSame(['2026-09-14'], array_column($summary['incidence_trends']['weeks'], 'key'));
            $this->assertSame(2026, CarbonImmutable::now('America/Mexico_City')->year);
            $historicalSummary = app(BuildOperationalDashboardAction::class)->handle($company, $user, '2025-10-08');

            $this->assertSame(2026, $historicalSummary['incidence_trends']['year']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_dashboard_pending_incidents_accumulate_without_date_filter(): void
    {
        $date = '2026-10-07';
        $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        [$worker, $relationship, $center] = $this->activeRelationship($company, $date);
        $dailyType = AlertType::query()->create([
            'code' => 'daily_limit_exceeded',
            'name' => 'Jornada excedida',
            'description' => 'El tiempo trabajado supera el límite diario aplicable.',
            'default_severity' => AlertType::SEVERITY_WARNING,
            'category' => 'daily',
            'status' => AlertType::STATUS_ACTIVE,
        ]);
        $lateType = AlertType::query()->create([
            'code' => 'late_arrival_detected',
            'name' => 'Entrada tardía',
            'description' => 'Entrada posterior al horario publicado.',
            'default_severity' => AlertType::SEVERITY_WARNING,
            'category' => 'daily',
            'status' => AlertType::STATUS_ACTIVE,
        ]);
        $earlyType = AlertType::query()->create([
            'code' => 'early_departure_detected',
            'name' => 'Salida anticipada',
            'description' => 'Salida previa al horario publicado.',
            'default_severity' => AlertType::SEVERITY_WARNING,
            'category' => 'daily',
            'status' => AlertType::STATUS_ACTIVE,
        ]);
        $previousDay = $this->workDay($company, $worker, $relationship, $center, '2026-10-06');
        $selectedDay = $this->workDay($company, $worker, $relationship, $center, $date);
        $previousWeek = $this->workDay($company, $worker, $relationship, $center, '2026-09-29');
        $olderWeek = $this->workDay($company, $worker, $relationship, $center, '2026-09-27');
        $completedCalculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id, 'work_day_id' => $olderWeek->id,
            'total_work_minutes' => 600, 'ordinary_minutes' => 480, 'overtime_minutes' => 120,
            'result_snapshot' => ['issues' => []],
        ]);
        $olderWeek->update(['active_calculation_id' => $completedCalculation->id]);

        $this->incident($company, $dailyType, '2026-10-07 10:00:00', Alert::STATUS_NEW, 'daily-previous-day', $previousDay);
        $this->incident($company, $dailyType, '2026-10-07 10:00:00', Alert::STATUS_NEW, 'daily-selected-day', $selectedDay);
        $this->incident($company, $dailyType, '2026-09-28 10:00:00', Alert::STATUS_CLOSED, 'daily-closed', $previousWeek);
        $this->incident($company, $lateType, '2026-10-07 10:00:00', Alert::STATUS_NEW, 'late-current-day', $selectedDay);
        $this->incident($company, $lateType, '2026-10-07 10:00:00', Alert::STATUS_NEW, 'late-previous-day', $previousDay);
        $this->incident($company, $earlyType, '2026-10-07 10:00:00', Alert::STATUS_NEW, 'early-previous-week', $previousWeek);
        $this->incident($company, $dailyType, '2026-10-07 10:00:00', Alert::STATUS_NEW, 'older-open-alert', $olderWeek);

        $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, $date);

        // Attendance is still pending on the two recent days; only the completed historical day enters calculated review.
        $this->assertSame(1, collect($summary['pending_alerts'])->firstWhere('key', 'daily_limit_exceeded')['count']);
        $this->assertSame(2, collect($summary['pending_alerts'])->firstWhere('key', 'late_arrival_detected')['count']);
        $this->assertSame(1, $summary['labor_compliance']['older_open_alerts']);
    }

    public function test_dashboard_builds_labor_compliance_for_the_last_closed_week(): void
    {
        $this->seed(LegalRuleSeeder::class);

        $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        [$workerWithinLimit, $relationshipWithinLimit, $center] = $this->activeRelationship($company, '2026-10-12');
        [$workerOverLimit, $relationshipOverLimit] = $this->activeRelationship($company, '2026-10-12');

        foreach (range(0, 5) as $offset) {
            $this->complianceWorkDay(
                $company,
                $workerWithinLimit,
                $relationshipWithinLimit,
                $center,
                CarbonImmutable::parse('2026-10-05')->addDays($offset)->toDateString(),
                totalMinutes: 540,
                ordinaryMinutes: 480,
                overtimeMinutes: 60,
            );
        }

        foreach (range(0, 6) as $offset) {
            $this->complianceWorkDay(
                $company,
                $workerOverLimit,
                $relationshipOverLimit,
                $relationshipOverLimit->center,
                CarbonImmutable::parse('2026-10-05')->addDays($offset)->toDateString(),
                totalMinutes: $offset === 6 ? 780 : 480,
                ordinaryMinutes: 480,
                overtimeMinutes: $offset === 6 ? 300 : 0,
            );
        }

        $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, '2026-10-12');

        $this->assertSame('2026-10-05', $summary['labor_compliance']['week_start']);
        $this->assertSame('2026-10-11', $summary['labor_compliance']['week_end']);
        $this->assertSame(2, $summary['labor_compliance']['evaluated_workers']);
        $this->assertSame(1, $summary['labor_compliance']['compliant_workers']);
        $this->assertSame(1, $summary['labor_compliance']['exceeded_workers']);
        $this->assertSame(50, $summary['labor_compliance']['compliance_percentage']);
        $this->assertSame(0, $summary['labor_compliance']['overtime_limit_exceeded_workers']);
        $this->assertSame(540, $summary['labor_compliance']['weekly_overtime_limit_minutes']);
        $this->assertSame(1, $summary['labor_compliance']['over_twelve_hours_workers']);
        $this->assertSame(2880, $summary['labor_compliance']['weekly_limit_minutes']);
        $this->assertSame(2760, $summary['labor_compliance']['next_weekly_limit_minutes']);
        $this->assertSame('2027-01-01', $summary['labor_compliance']['next_weekly_limit_effective_from']);
    }

    public function test_dashboard_analytics_group_alerts_by_the_affected_work_day_week(): void
    {
        $date = '2026-10-10';
        $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        [$worker, $relationship, $center] = $this->activeRelationship($company, $date);
        $type = AlertType::query()->create([
            'code' => 'late_arrival_detected',
            'name' => 'Entrada tardía',
            'description' => 'Entrada posterior al horario publicado.',
            'default_severity' => AlertType::SEVERITY_WARNING,
            'category' => 'daily',
            'status' => AlertType::STATUS_ACTIVE,
        ]);
        $previousWeekWorkDay = $this->workDay($company, $worker, $relationship, $center, '2026-09-30');
        $currentWeekWorkDay = $this->workDay($company, $worker, $relationship, $center, '2026-10-06');

        $this->incident($company, $type, '2026-10-10 10:00:00', Alert::STATUS_NEW, 'detected-later-previous-week', $previousWeekWorkDay);
        $this->incident($company, $type, '2026-10-10 10:00:00', Alert::STATUS_NEW, 'detected-later-current-week', $currentWeekWorkDay);

        $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, $date);
        $trendSeries = $summary['incidence_trends']['series'][0];
        $trendValues = array_combine(
            array_column($summary['incidence_trends']['weeks'], 'key'),
            $trendSeries['values'],
        );

        $this->assertSame(['2026-09-28', '2026-10-05'], array_column($summary['incidence_trends']['weeks'], 'key'));
        $this->assertSame(1, $trendValues['2026-09-28']);
        $this->assertSame(1, $trendValues['2026-10-05']);
    }

    /** @return array{0: Worker, 1: EmploymentRelationship, 2: Center} */
    private function activeRelationship(Company $company, string $date): array
    {
        $center = Center::factory()->create(['company_id' => $company->id]);
        $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $relationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'started_at' => CarbonImmutable::parse($date)->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        return [$worker, $relationship, $center];
    }

    private function validEvent(Company $company, Worker $worker, EmploymentRelationship $relationship, Center $center, string $eventType, string $source, string $date, string $time): void
    {
        $local = CarbonImmutable::parse("{$date} {$time}", 'America/Mexico_City');

        TimeEvent::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'event_type' => $eventType,
            'occurred_at_utc' => $local->utc(),
            'occurred_local_date' => $date,
            'occurred_local_time' => $time,
            'timezone' => 'America/Mexico_City',
            'received_at' => $local->utc(),
            'source' => $source,
            'status' => 'valid',
            'metadata' => [],
        ]);
    }

    private function workDay(Company $company, Worker $worker, EmploymentRelationship $relationship, Center $center, string $date): WorkDay
    {
        return WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'work_date' => $date,
        ]);
    }

    private function complianceWorkDay(Company $company, Worker $worker, EmploymentRelationship $relationship, Center $center, string $date, int $totalMinutes, int $ordinaryMinutes, int $overtimeMinutes): WorkDayCalculation
    {
        $workDay = $this->workDay($company, $worker, $relationship, $center, $date);
        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id,
            'work_day_id' => $workDay->id,
            'status' => WorkDayCalculation::STATUS_ACTIVE,
            'classification' => WorkDayCalculation::CLASSIFICATION_DIURNAL,
            'total_work_minutes' => $totalMinutes,
            'ordinary_minutes' => $ordinaryMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'result_snapshot' => ['issues' => []],
        ]);
        $workDay->forceFill([
            'status' => WorkDay::STATUS_CALCULATED,
            'active_calculation_id' => $calculation->id,
        ])->save();

        return $calculation;
    }

    private function incident(Company $company, AlertType $type, string $detectedAt, string $status, string $fingerprint, ?WorkDay $workDay = null): void
    {
        Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $type->id,
            'work_day_id' => $workDay?->id,
            'severity' => AlertType::SEVERITY_WARNING,
            'status' => $status,
            'title' => $type->name,
            'description' => $type->description,
            'rule_code' => $type->code,
            'detected_at' => $detectedAt,
            'fingerprint' => hash('sha256', $fingerprint),
            'metadata' => [],
        ]);
    }
}
