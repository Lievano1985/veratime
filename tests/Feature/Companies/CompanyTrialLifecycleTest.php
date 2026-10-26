<?php

namespace Tests\Feature\Companies;

use App\Domains\Companies\Actions\CreateCompanyAction;
use App\Domains\Companies\Actions\SuspendExpiredTrialCompaniesAction;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTrialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_company_starts_active_and_expires_fifteen_days_after_creation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 09:30:00', 'UTC'));

        try {
            $company = app(CreateCompanyAction::class)->handle(User::factory()->create(), [
                'name' => 'Empresa de prueba',
                'legal_name' => 'Empresa de prueba SA de CV',
                'tax_id' => 'PRU261010AA1',
                'timezone' => 'America/Mexico_City',
                'status' => 'inactive',
                'is_trial' => true,
            ]);

            $this->assertTrue($company->is_trial);
            $this->assertSame('active', $company->status);
            $this->assertTrue($company->trial_ends_at->equalTo('2026-10-25 09:30:00'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_expired_trial_command_suspends_only_expired_active_trials(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 09:30:00', 'UTC'));

        try {
            $expiredTrial = Company::factory()->create([
                'is_trial' => true,
                'trial_ends_at' => now()->subMinute(),
            ]);
            $currentTrial = Company::factory()->create([
                'is_trial' => true,
                'trial_ends_at' => now()->addMinute(),
            ]);
            $regularCompany = Company::factory()->create([
                'is_trial' => false,
                'trial_ends_at' => now()->subMinute(),
            ]);
            $inactiveTrial = Company::factory()->create([
                'is_trial' => true,
                'status' => 'inactive',
                'trial_ends_at' => now()->subMinute(),
            ]);

            $this->artisan('companies:suspend-expired-trials')
                ->expectsOutput('Empresas de prueba suspendidas: 1.')
                ->assertExitCode(0);

            $this->assertSame('suspended', $expiredTrial->fresh()->status);
            $this->assertSame('active', $currentTrial->fresh()->status);
            $this->assertSame('active', $regularCompany->fresh()->status);
            $this->assertSame('inactive', $inactiveTrial->fresh()->status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_expired_trial_action_reports_the_number_of_suspended_companies(): void
    {
        $expiredTrials = Company::factory()->count(2)->create([
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertSame(2, app(SuspendExpiredTrialCompaniesAction::class)->handle());
        $this->assertTrue($expiredTrials->every(fn (Company $company): bool => $company->fresh()->status === 'suspended'));
    }
}
