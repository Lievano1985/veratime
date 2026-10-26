<?php

namespace Tests\Feature\Dashboard;

use App\Domains\Dashboard\Actions\BuildAttendanceCoverageAction;
use App\Domains\Dashboard\Actions\BuildOperationalDashboardAction;
use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 18:00', 'America/Mexico_City'));
    }

    public function test_coverage_is_one_hundred_with_eight_finished_and_two_still_working(): void
    {
        [$company, $user] = $this->company();
        for ($index = 0; $index < 10; $index++) {
            $relationship = $this->scheduled($company);
            $this->event($relationship, 'clock_in', '09:00');
            if ($index < 8) {
                $this->event($relationship, 'clock_out', '17:00');
                $day = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $relationship->worker_id,
                    'employment_relationship_id' => $relationship->id, 'center_id' => $relationship->center_id,
                    'work_date' => '2026-10-20', 'valid_time_event_count' => 2]);
                $calculation = WorkDayCalculation::factory()->create(['company_id' => $company->id, 'work_day_id' => $day->id,
                    'total_work_minutes' => 480, 'result_snapshot' => ['issues' => []]]);
                $day->update(['active_calculation_id' => $calculation->id]);
            }
        }
        $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, '2026-10-20');
        $this->assertSame(100, $summary['attendance_coverage']['percentage']);
        $this->assertSame(10, $summary['attendance_coverage']['attended_workers']);
        $this->assertSame(10, $summary['attendance_coverage']['expected_workers']);
        $this->assertSame(2, $summary['metrics']['working_now']);
        $this->assertSame(8, $summary['metrics']['completed_work_days']);
    }

    public function test_authorized_absence_is_excluded_but_unjustified_absence_remains_expected(): void
    {
        [$company, $user] = $this->company();
        $present = $this->scheduled($company);
        $authorized = $this->scheduled($company);
        $absent = $this->scheduled($company);
        $this->event($present, 'clock_in', '09:00');
        foreach ([$authorized->id => 'vacation', $absent->id => 'unjustified_absence'] as $id => $type) {
            $relationship = EmploymentRelationship::findOrFail($id);
            AttendanceIncident::factory()->create(['company_id' => $company->id, 'worker_id' => $relationship->worker_id,
                'employment_relationship_id' => $id, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20',
                'status' => 'approved', 'incident_type' => $type]);
        }
        $coverage = $this->coverage($company, $user);
        $this->assertSame(3, $coverage['scheduled_workers']);
        $this->assertSame(2, $coverage['expected_workers']);
        $this->assertSame(50, $coverage['percentage']);
    }

    public function test_coverage_deduplicates_people_and_excludes_unscheduled_invalid_and_future_entries(): void
    {
        [$company, $user] = $this->company();
        $present = $this->scheduled($company);
        $invalid = $this->scheduled($company);
        $future = $this->scheduled($company);
        $unscheduled = EmploymentRelationship::factory()->forCompany($company)->create();
        $this->event($present, 'clock_in', '09:00');
        $this->event($present, 'clock_in', '09:01');
        $this->event($invalid, 'clock_in', '09:00')->update(['status' => 'voided']);
        $this->event($future, 'clock_in', '22:00');
        $this->event($unscheduled, 'clock_in', '09:00');
        $coverage = $this->coverage($company, $user);
        $this->assertSame(3, $coverage['expected_workers']);
        $this->assertSame(1, $coverage['attended_workers']);
        $this->assertSame(33, $coverage['percentage']);
    }

    public function test_scope_center_company_and_empty_schedule_are_respected(): void
    {
        [$company, $manager] = $this->company();
        [$foreign, $foreignManager] = $this->company();
        $allowed = $this->scheduled($company);
        $outside = $this->scheduled($company);
        $this->event($allowed, 'clock_in', '09:00');
        $this->event($this->scheduled($foreign), 'clock_in', '09:00');
        $operator = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['key' => RoleKey::RH_OPERATIVO], ['name' => 'RH']);
        $operator->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active']);
        app(AssignOperationalScopeAction::class)->handle($company, $operator, ['effective_from' => '2026-10-01'], center: $allowed->center);
        $coverage = $this->coverage($company, $operator);
        $this->assertSame(1, $coverage['expected_workers']);
        $this->assertSame(100, $coverage['percentage']);
        $blocked = app(BuildOperationalDashboardAction::class)->handle($company, $operator, '2026-10-20', $outside->center_id);
        $this->assertNull($blocked['attendance_coverage']['percentage']);
        $empty = app(BuildAttendanceCoverageAction::class)->handle($company, $manager, '2026-10-19');
        $this->assertSame(0, $empty['expected_workers']);
        $this->assertNull($empty['percentage']);
    }

    private function company(): array
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['key' => RoleKey::ADMIN_EMPRESA], ['name' => 'Administrador']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        return [$company, $user];
    }

    private function scheduled(Company $company): EmploymentRelationship
    {
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create(['started_at' => '2026-10-01']);
        $batch = ScheduleBatch::factory()->create(['company_id' => $company->id, 'center_id' => $relationship->center_id,
            'status' => 'published', 'period_start' => '2026-10-19', 'period_end' => '2026-10-25']);
        DailyScheduleAssignment::factory()->create(['company_id' => $company->id, 'schedule_batch_id' => $batch->id,
            'employment_relationship_id' => $relationship->id, 'work_date' => '2026-10-20', 'day_type' => 'shift']);

        return $relationship;
    }

    private function event(EmploymentRelationship $relationship, string $type, string $time): TimeEvent
    {
        return TimeEvent::factory()->create(['company_id' => $relationship->company_id, 'worker_id' => $relationship->worker_id,
            'employment_relationship_id' => $relationship->id, 'center_id' => $relationship->center_id,
            'event_type' => $type, 'source' => 'job', 'status' => 'valid', 'timezone' => 'America/Mexico_City',
            'occurred_local_date' => '2026-10-20', 'occurred_local_time' => $time,
            'occurred_at_utc' => CarbonImmutable::parse('2026-10-20 '.$time, 'America/Mexico_City')->utc(), 'received_at' => now()]);
    }

    private function coverage(Company $company, User $user): array
    {
        return app(BuildAttendanceCoverageAction::class)->handle($company, $user, '2026-10-20');
    }
}
