<?php

namespace Tests\Feature\BlockD2;

use App\Domains\Scheduling\Actions\ResolveScheduleProfileForRelationshipAction;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\OperationalScopeAssignment;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\User;
use Database\Seeders\VeraTimeScheduleProfileScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduleProfileScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_profile_scenario_seeder_is_idempotent_and_creates_expected_companies(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimeScheduleProfileScenarioSeeder::class]);

        $companyIds = Company::query()
            ->whereIn('tax_id', ['VTSP-OFFICE', 'VTSP-STORE', 'VTSP-CONSTRUCT', 'VTSP-NOPROFILE'])
            ->pluck('id');

        $firstCounts = [
            'companies' => $companyIds->count(),
            'users' => User::query()->where('email', 'like', '%.demo@veratime.local')->count(),
            'profiles' => ScheduleProfile::query()->whereIn('company_id', $companyIds)->count(),
            'assignments' => ScheduleProfileAssignment::query()->whereIn('company_id', $companyIds)->count(),
            'scopes' => OperationalScopeAssignment::query()->whereIn('company_id', $companyIds)->count(),
        ];

        Artisan::call('db:seed', ['--class' => VeraTimeScheduleProfileScenarioSeeder::class]);

        $companyIds = Company::query()
            ->whereIn('tax_id', ['VTSP-OFFICE', 'VTSP-STORE', 'VTSP-CONSTRUCT', 'VTSP-NOPROFILE'])
            ->pluck('id');

        $this->assertSame(4, $companyIds->count());
        $this->assertSame($firstCounts['companies'], $companyIds->count());
        $this->assertSame($firstCounts['profiles'], ScheduleProfile::query()->whereIn('company_id', $companyIds)->count());
        $this->assertSame($firstCounts['assignments'], ScheduleProfileAssignment::query()->whereIn('company_id', $companyIds)->count());
        $this->assertSame($firstCounts['scopes'], OperationalScopeAssignment::query()->whereIn('company_id', $companyIds)->count());
    }

    public function test_schedule_profile_scenario_seeder_creates_only_pattern_weekly_and_calendar_profiles(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimeScheduleProfileScenarioSeeder::class]);

        $companyIds = Company::query()
            ->whereIn('tax_id', ['VTSP-OFFICE', 'VTSP-STORE', 'VTSP-CONSTRUCT', 'VTSP-NOPROFILE'])
            ->pluck('id');

        $this->assertDatabaseHas('schedule_profiles', [
            'code' => 'OFFICE-WEEKLY',
            'profile_type' => 'pattern',
            'pattern_mode' => 'weekly',
        ]);
        $this->assertDatabaseHas('schedule_profiles', [
            'code' => 'STORE-CALENDAR',
            'profile_type' => 'calendar',
            'pattern_mode' => null,
        ]);

        $this->assertSame(0, ScheduleProfile::query()->whereIn('company_id', $companyIds)->whereIn('profile_type', ['fixed', 'variable', 'flexible', 'on_call'])->count());
        $this->assertSame(0, ScheduleProfile::query()->whereIn('company_id', $companyIds)->where('pattern_mode', 'cycle')->count());
    }

    public function test_schedule_profile_scenario_seeder_resolves_expected_inheritance_levels(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimeScheduleProfileScenarioSeeder::class]);

        $resolver = app(ResolveScheduleProfileForRelationshipAction::class);

        $office = Company::query()->where('tax_id', 'VTSP-OFFICE')->firstOrFail();
        $store = Company::query()->where('tax_id', 'VTSP-STORE')->firstOrFail();
        $construction = Company::query()->where('tax_id', 'VTSP-CONSTRUCT')->firstOrFail();
        $noProfile = Company::query()->where('tax_id', 'VTSP-NOPROFILE')->firstOrFail();

        $officeResolved = $resolver->handle($office, $this->relationship($office, 'OFF-001'), '2026-08-17');
        $this->assertSame('company', $officeResolved['assignment_scope']);
        $this->assertSame('OFFICE-WEEKLY', $officeResolved['schedule_profile']->code);
        $this->assertSame('pattern', $officeResolved['schedule_profile']->profile_type);
        $this->assertSame('weekly', $officeResolved['schedule_profile']->pattern_mode);

        $storeResolved = $resolver->handle($store, $this->relationship($store, 'STR-001'), '2026-08-17');
        $this->assertSame('company', $storeResolved['assignment_scope']);
        $this->assertSame('STORE-CALENDAR', $storeResolved['schedule_profile']->code);
        $this->assertSame('calendar', $storeResolved['schedule_profile']->profile_type);
        $this->assertNull($storeResolved['schedule_profile']->pattern_mode);

        $this->assertResolution($resolver, $construction, 'CON-001', 'company', 'CONST-BASE');
        $this->assertResolution($resolver, $construction, 'CON-002', 'center', 'CONST-CALENDAR');
        $this->assertResolution($resolver, $construction, 'CON-003', 'organizational_unit', 'CONST-WAREHOUSE');
        $this->assertResolution($resolver, $construction, 'CON-004', 'employment_relationship', 'CONST-DIRECT-CAL');

        $emptyResolved = $resolver->handle($noProfile, $this->relationship($noProfile, 'NOP-001'), '2026-08-17');
        $this->assertNull($emptyResolved['schedule_profile']);
        $this->assertNull($emptyResolved['assignment_scope']);
    }

    public function test_schedule_profile_scenario_seeder_preserves_tenant_isolation_and_supervisor_scope(): void
    {
        Artisan::call('db:seed', ['--class' => VeraTimeScheduleProfileScenarioSeeder::class]);

        $office = Company::query()->where('tax_id', 'VTSP-OFFICE')->firstOrFail();
        $store = Company::query()->where('tax_id', 'VTSP-STORE')->firstOrFail();
        $construction = Company::query()->where('tax_id', 'VTSP-CONSTRUCT')->firstOrFail();

        $this->assertNotSame(
            ScheduleProfile::query()->where('company_id', $office->id)->where('code', 'OFFICE-WEEKLY')->firstOrFail()->id,
            ScheduleProfile::query()->where('company_id', $store->id)->where('code', 'STORE-CALENDAR')->firstOrFail()->id,
        );

        $supervisor = User::query()->where('email', 'supervisor.construction.demo@veratime.local')->firstOrFail();
        $scope = OperationalScopeAssignment::query()
            ->with('organizationalUnit.center')
            ->where('company_id', $construction->id)
            ->where('user_id', $supervisor->id)
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertNull($scope->center_id);
        $this->assertSame('Area Construccion', $scope->organizationalUnit->name);
        $this->assertSame('Obra Norte', $scope->organizationalUnit->center->name);
        $this->assertFalse($supervisor->belongsToCompany($office));
        $this->assertFalse($supervisor->belongsToCompany($store));
    }

    private function assertResolution(
        ResolveScheduleProfileForRelationshipAction $resolver,
        Company $company,
        string $employeeCode,
        string $expectedScope,
        string $expectedProfileCode,
    ): void {
        $resolved = $resolver->handle($company, $this->relationship($company, $employeeCode), '2026-08-17');

        $this->assertSame($expectedScope, $resolved['assignment_scope']);
        $this->assertSame($expectedProfileCode, $resolved['schedule_profile']->code);
    }

    private function relationship(Company $company, string $employeeCode): EmploymentRelationship
    {
        return EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->whereHas('worker', fn ($query) => $query->where('employee_code', $employeeCode))
            ->firstOrFail();
    }
}
