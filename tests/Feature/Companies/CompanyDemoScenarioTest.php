<?php

namespace Tests\Feature\Companies;

use App\Domains\Attendance\Actions\ValidateAttendancePeriodForClosingAction;
use App\Domains\Companies\Actions\GenerateCompanyDemoScenarioAction;
use App\Domains\Companies\Actions\RequestCompanyDemoScenarioAction;
use App\Models\Alert;
use App\Models\AttendanceIncident;
use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanyDemoScenario;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\AlertTypeSeeder;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class CompanyDemoScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_demo_scenario_creates_operational_data_and_leaves_only_labor_alert_cases_open(): void
    {
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
        $this->assertSame(4, ScheduleBatch::query()->where('company_id', $company->id)->where('status', 'published')->count());
        $this->assertSame(4, AttendanceIncident::query()->where('company_id', $company->id)->where('status', 'approved')->count());
        $this->assertSame(2, AttendancePeriod::query()->where('company_id', $company->id)->count());

        $openRuleCodes = Alert::query()
            ->where('company_id', $company->id)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->pluck('rule_code')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['overtime_detected', 'twelve_hours_exceeded'], $openRuleCodes);

        $nightPeriod = AttendancePeriod::query()
            ->where('company_id', $company->id)
            ->whereHas('center', fn ($query) => $query->where('code', 'DEMO-NOC'))
            ->firstOrFail();
        $validation = app(ValidateAttendancePeriodForClosingAction::class)->handle($company, $nightPeriod, $admin);

        $this->assertTrue($validation['ready_to_close']);
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
