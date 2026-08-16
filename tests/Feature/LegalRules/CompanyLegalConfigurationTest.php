<?php

namespace Tests\Feature\LegalRules;

use App\Domains\LegalRules\Actions\ResolveCompanyLegalConfigurationAction;
use App\Domains\LegalRules\Actions\ResolveLegalParameterForDateAction;
use App\Domains\LegalRules\Actions\UpdateCompanyLegalParameterAction;
use App\Models\Company;
use App\Models\LegalParameter;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanyLegalConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegalRuleSeeder::class);
    }

    public function test_resolves_mexico_base_rules_and_company_parameters(): void
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);

        $configuration = app(ResolveCompanyLegalConfigurationAction::class)->handle($company, '2026-08-03');

        $this->assertSame('MX', $configuration['country']);
        $this->assertContains('daily_limit_diurnal', collect($configuration['rules'])->pluck('code')->all());
        $this->assertContains('maximum_weekly_hours', collect($configuration['rules'])->pluck('code')->all());
        $this->assertSame(480, $configuration['parameters']['company_daily_limit_diurnal_minutes']['value']);
    }

    public function test_company_can_store_allowed_internal_legal_parameter_with_actor_and_reason(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::ADMIN_EMPRESA);

        $parameter = app(UpdateCompanyLegalParameterAction::class)->handle(
            $company,
            'company_daily_limit_diurnal_minutes',
            450,
            '2026-08-03',
            'Politica interna mas favorable',
            $user,
        );

        $this->assertSame($company->id, $parameter->company_id);
        $this->assertSame(['minutes' => 450], $parameter->value);
        $this->assertSame('Politica interna mas favorable', $parameter->reason);
        $this->assertSame($user->id, $parameter->created_by);
        $this->assertSame($user->id, $parameter->updated_by);

        $resolved = app(ResolveLegalParameterForDateAction::class)->handle($company, 'company_daily_limit_diurnal_minutes', '2026-08-04');

        $this->assertSame(['minutes' => 450], $resolved?->value);
    }

    public function test_protected_parameter_cannot_exceed_country_base_limit(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::ADMIN_EMPRESA);

        $this->expectException(ValidationException::class);

        app(UpdateCompanyLegalParameterAction::class)->handle(
            $company,
            'company_daily_limit_diurnal_minutes',
            481,
            '2026-08-03',
            'Intento de limite menos favorable',
            $user,
        );
    }

    public function test_new_effective_parameter_closes_previous_company_parameter_without_deleting_it(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::ADMIN_EMPRESA);
        app(UpdateCompanyLegalParameterAction::class)->handle($company, 'late_arrival_tolerance_minutes', 5, '2026-08-01', 'Inicial', $user);
        app(UpdateCompanyLegalParameterAction::class)->handle($company, 'late_arrival_tolerance_minutes', 10, '2026-08-10', 'Cambio operativo', $user);

        $this->assertDatabaseHas('legal_parameters', [
            'company_id' => $company->id,
            'code' => 'late_arrival_tolerance_minutes',
            'effective_from' => '2026-08-01 00:00:00',
            'effective_to' => '2026-08-09',
        ]);
        $this->assertSame(5, app(ResolveLegalParameterForDateAction::class)->handle($company, 'late_arrival_tolerance_minutes', '2026-08-05')?->value['minutes']);
        $this->assertSame(10, app(ResolveLegalParameterForDateAction::class)->handle($company, 'late_arrival_tolerance_minutes', '2026-08-12')?->value['minutes']);
    }

    public function test_company_legal_configuration_ui_updates_parameter_for_manager(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::ADMIN_EMPRESA);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('company-settings.index')
            ->assertSee('Configuracion legal')
            ->set('legalParameterForm.company_daily_limit_diurnal_minutes.value', 450)
            ->set('legalParameterForm.company_daily_limit_diurnal_minutes.effective_from', '2026-08-03')
            ->set('legalParameterForm.company_daily_limit_diurnal_minutes.reason', 'Politica interna')
            ->call('updateLegalParameter', 'company_daily_limit_diurnal_minutes')
            ->assertHasNoErrors()
            ->assertSee('Parametro legal actualizado.');

        $this->assertDatabaseHas('legal_parameters', [
            'company_id' => $company->id,
            'code' => 'company_daily_limit_diurnal_minutes',
            'value' => json_encode(['minutes' => 450]),
        ]);
    }

    public function test_supervisor_cannot_update_company_legal_configuration(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::SUPERVISOR);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->get(route('company-settings.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $roleKey): array
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => $roleKey, 'description' => null, 'is_system' => true],
        );
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $company->setting()->create(Company::defaultSettings());
        $user = User::factory()->create();
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return [$company, $user];
    }
}
