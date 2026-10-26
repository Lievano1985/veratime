<?php

namespace Tests\Feature\Companies;

use App\Domains\Attendance\Actions\ValidateAttendancePeriodForClosingAction;
use App\Domains\Companies\Actions\GenerateCompanyDemoScenarioAction;
use App\Domains\Companies\Actions\RequestCompanyDemoScenarioAction;
use App\Domains\Dashboard\Actions\BuildOperationalDashboardAction;
use App\Domains\WorkDays\Jobs\RecalculateWorkDayFromTimeEventJob;
use App\Models\Alert;
use App\Models\AttendanceIncident;
use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanyDemoScenario;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Database\Seeders\AlertTypeSeeder;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyDemoScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The demo explicitly processes its days; event jobs run separately in production.
        Queue::fake([RecalculateWorkDayFromTimeEventJob::class]);

        $this->seed(LegalRuleSeeder::class);
        $this->seed(AlertTypeSeeder::class);
    }

    public function test_requesting_a_demo_scenario_runs_the_generator_directly(): void
    {
        [$company, $admin] = $this->companyWithAdmin();

        $generator = Mockery::mock(GenerateCompanyDemoScenarioAction::class);
        $generator->shouldReceive('handle')
            ->once()
            ->withArgs(fn (CompanyDemoScenario $scenario): bool => $scenario->company_id === $company->id)
            ->andReturnUsing(function (CompanyDemoScenario $scenario): CompanyDemoScenario {
                $scenario->forceFill(['status' => CompanyDemoScenario::STATUS_COMPLETED])->save();

                return $scenario->fresh();
            });
        $this->app->instance(GenerateCompanyDemoScenarioAction::class, $generator);

        $scenario = app(RequestCompanyDemoScenarioAction::class)->handle($company, $admin);
        $this->assertSame(CompanyDemoScenario::STATUS_COMPLETED, $scenario->status);

        $this->expectException(InvalidArgumentException::class);

        app(RequestCompanyDemoScenarioAction::class)->handle($company, $admin);
    }

    #[DataProvider('demoDates')]
    public function test_demo_scenario_creates_operational_data_and_populates_dashboard_indicators(string $date): void
    {
        $this->travelTo(CarbonImmutable::parse($date, 'America/Mexico_City'));

        [$company, $admin] = $this->companyWithAdmin();
        $scenario = CompanyDemoScenario::query()->create([
            'company_id' => $company->id,
            'requested_by_user_id' => $admin->id,
            'status' => CompanyDemoScenario::STATUS_PENDING,
        ]);

        app(GenerateCompanyDemoScenarioAction::class)->handle($scenario);

        $scenario->refresh();
        $this->assertSame(CompanyDemoScenario::STATUS_COMPLETED, $scenario->status);
        $this->assertNotNull($scenario->period_start);
        $this->assertSame(2, Center::query()->where('company_id', $company->id)->count());
        $this->assertSame(10, Worker::query()->where('company_id', $company->id)->count());
        $this->assertSame(4, ShiftTemplate::query()->where('company_id', $company->id)->count());
        $this->assertSame(6, ScheduleBatch::query()->where('company_id', $company->id)->where('status', 'published')->count());
        $this->assertSame(6, AttendanceIncident::query()->where('company_id', $company->id)->where('status', 'approved')->count());
        $this->assertSame(2, AttendancePeriod::query()->where('company_id', $company->id)->count());

        $openAlerts = Alert::query()
            ->where('company_id', $company->id)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->get();

        $this->assertSame(14, $scenario->summary['pending_review_cases']);
        $this->assertGreaterThan(8, $openAlerts->count());
        $expectedRuleCodes = [
            'daily_limit_exceeded',
            'early_departure_detected',
            'incomplete_work_day',
            'late_arrival_detected',
            'minimum_break_missing',
            'overtime_detected',
            'weekly_overtime_days_exceeded',
            'weekly_overtime_exceeded',
            'weekly_rest_missing',
        ];
        $this->assertTrue(collect($expectedRuleCodes)->every(
            fn (string $ruleCode): bool => $openAlerts->pluck('rule_code')->contains($ruleCode),
        ));
        $this->assertEmpty($openAlerts->whereIn('rule_code', ['daily_overtime_over_three_hours', 'long_work_day', 'twelve_hours_exceeded', 'weekly_sunday_work']));

        $nightPeriod = AttendancePeriod::query()
            ->where('company_id', $company->id)
            ->whereHas('center', fn ($query) => $query->where('code', 'DEMO-NOC'))
            ->firstOrFail();
        $validation = app(ValidateAttendancePeriodForClosingAction::class)->handle($company, $nightPeriod, $admin);

        $nightAlerts = Alert::query()->where('company_id', $company->id)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->whereHas('workDay', fn ($query) => $query->where('center_id', $nightPeriod->center_id))
            ->with('workDay:id,work_date')->get();
        $this->assertSame(0, $validation['blockers']['open_alerts'], $nightAlerts->map(fn (Alert $alert): array => [
            'rule' => $alert->rule_code, 'date' => $alert->workDay?->work_date?->toDateString(),
        ])->toJson());
    }

    public function test_demo_scenario_includes_current_week_calendar_marks_and_incidents(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00:00', 'America/Mexico_City'));
        [$company, $admin] = $this->companyWithAdmin();
        $scenario = CompanyDemoScenario::query()->create([
            'company_id' => $company->id,
            'requested_by_user_id' => $admin->id,
            'status' => CompanyDemoScenario::STATUS_PENDING,
        ]);

        app(GenerateCompanyDemoScenarioAction::class)->handle($scenario);

        $this->assertTrue(ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', '2026-10-05')
            ->whereDate('period_end', '2026-10-11')
            ->where('status', 'published')
            ->exists());
        $this->assertTrue(AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->where('reference', 'PER-DEMO-CURRENT-01')
            ->whereDate('start_date', '2026-10-06')
            ->exists());
        $this->assertTrue(AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->where('reference', 'AUS-DEMO-CURRENT-01')
            ->whereDate('start_date', '2026-10-08')
            ->exists());
        $this->assertTrue(TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereDate('occurred_local_date', '2026-10-09')
            ->exists());
        $this->assertFalse(TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereDate('occurred_local_date', '>', '2026-10-10')
            ->exists());

        $openAlertsByWeek = Alert::query()
            ->where('company_id', $company->id)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->with('workDay:id,work_date')
            ->get()
            ->groupBy(fn (Alert $alert): string => $alert->workDay->work_date->gte('2026-10-05') ? 'current' : 'previous');

        $this->assertSame(14, $scenario->fresh()->summary['pending_review_cases']);
        $this->assertGreaterThanOrEqual(8, $openAlertsByWeek->get('current', collect())->count());
        $this->assertGreaterThanOrEqual(8, $openAlertsByWeek->get('previous', collect())->count());

        $dashboard = app(BuildOperationalDashboardAction::class)->handle($company, $admin, '2026-10-10');
        $daily = collect($dashboard['pending_alerts'])->keyBy('key');
        $laborCompliance = $dashboard['labor_compliance'];

        foreach ([
            'late_arrival_detected',
            'early_departure_detected',
            'incomplete_work_day',
            'daily_limit_exceeded',
            'overtime_detected',
            'minimum_break_missing',
        ] as $ruleCode) {
            $this->assertGreaterThanOrEqual(1, $daily->get($ruleCode)['count']);
        }

        $this->assertGreaterThan(0, $laborCompliance['overtime_limit_exceeded_workers']);
        $this->assertGreaterThan(0, $laborCompliance['over_twelve_hours_workers']);
        $this->assertGreaterThan(0, $laborCompliance['pending_workers']);
    }

    public function test_current_day_demo_advances_with_local_time_without_future_or_duplicate_events(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 11:30:00', 'America/Mexico_City'));
        [$company, $admin] = $this->companyWithAdmin();
        $scenario = CompanyDemoScenario::query()->create([
            'company_id' => $company->id,
            'requested_by_user_id' => $admin->id,
            'status' => CompanyDemoScenario::STATUS_PENDING,
        ]);
        $action = app(GenerateCompanyDemoScenarioAction::class);
        $scenario = $action->handle($scenario);
        $dashboard = app(BuildOperationalDashboardAction::class)->handle($company, $admin, '2026-10-19');
        $this->assertSame(2, $dashboard['metrics']['working_now']);
        $this->assertSame(3, $dashboard['metrics']['on_break']);
        $this->assertSame(0, $dashboard['metrics']['completed_work_days']);
        $count = TimeEvent::query()->where('company_id', $company->id)->count();
        $action->refreshCurrentDay($scenario);
        $this->assertSame($count, TimeEvent::query()->where('company_id', $company->id)->count());

        $this->travelTo(CarbonImmutable::parse('2026-10-19 19:00:00', 'America/Mexico_City'));
        $action->refreshCurrentDay($scenario->fresh());
        $dashboard = app(BuildOperationalDashboardAction::class)->handle($company, $admin, '2026-10-19');
        $this->assertSame(3, $dashboard['metrics']['working_now']);
        $this->assertSame(0, $dashboard['metrics']['on_break']);
        $this->assertSame(5, $dashboard['metrics']['completed_work_days']);
        $this->assertFalse(TimeEvent::query()->where('company_id', $company->id)
            ->where('occurred_at_utc', '>', now()->utc())->exists());
    }

    public static function demoDates(): array
    {
        return [
            'monday_after_closed_week' => ['2026-10-19 12:00:00'],
            'sunday' => ['2026-10-18 12:00:00'],
        ];
    }

    public function test_a_processing_scenario_is_not_generated_twice(): void
    {
        [$company, $admin] = $this->companyWithAdmin();
        $scenario = CompanyDemoScenario::query()->create([
            'company_id' => $company->id,
            'requested_by_user_id' => $admin->id,
            'status' => CompanyDemoScenario::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $result = app(GenerateCompanyDemoScenarioAction::class)->handle($scenario);

        $this->assertSame(CompanyDemoScenario::STATUS_PROCESSING, $result->status);
        $this->assertSame(0, Center::query()->where('company_id', $company->id)->count());
    }

    public function test_user_cannot_request_a_demo_for_another_company(): void
    {
        [, $admin] = $this->companyWithAdmin();
        [$otherCompany] = $this->companyWithAdmin();

        $this->expectException(InvalidArgumentException::class);

        app(RequestCompanyDemoScenarioAction::class)->handle($otherCompany, $admin);
    }

    public function test_demo_cannot_be_requested_for_a_company_with_operational_data(): void
    {
        [$company, $admin] = $this->companyWithAdmin();
        Center::factory()->create(['company_id' => $company->id]);

        $this->expectException(InvalidArgumentException::class);

        app(RequestCompanyDemoScenarioAction::class)->handle($company, $admin);
    }

    /** @return array{0: Company, 1: User} */
    private function companyWithAdmin(): array
    {
        $role = Role::query()->firstOrCreate(
            ['key' => RoleKey::ADMIN_EMPRESA],
            ['name' => 'Administrador de empresa', 'description' => 'Rol de prueba', 'is_system' => true],
        );
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $this->seed(ProductSeeder::class);
        $company->setting()->create(Company::defaultSettings());
        $admin = User::factory()->create(['status' => 'active']);
        $admin->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return [$company, $admin];
    }
}
