<?php

namespace Tests\Feature\Sprint1A;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\KioskKey;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_or_update_company_settings(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('company-settings.index')
            ->set('settingsForm.payroll_period_type', 'weekly')
            ->set('settingsForm.default_timezone', 'America/Mazatlan')
            ->set('settingsForm.default_closure_day', 5)
            ->set('settingsForm.late_arrival_tolerance_minutes', 7)
            ->set('settingsForm.early_departure_tolerance_minutes', 9)
            ->set('settingsForm.allow_worker_corrections', true)
            ->set('settingsForm.require_pin_for_kiosk', false)
            ->set('settingsForm.kiosk_key', 'KIOSK-DEMO1!')
            ->set('settingsForm.require_pin_for_confirmation', true)
            ->call('updateSettings');

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'payroll_period_type' => 'weekly',
            'default_timezone' => 'America/Mazatlan',
            'default_closure_day' => 5,
            'late_arrival_tolerance_minutes' => 7,
            'early_departure_tolerance_minutes' => 9,
            'allow_worker_corrections' => true,
            'require_pin_for_kiosk' => false,
            'kiosk_key_hash' => KioskKey::hash('KIOSK-DEMO1!'),
            'require_pin_for_confirmation' => true,
        ]);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'timezone' => 'America/Mazatlan',
        ]);
    }

    public function test_company_settings_separates_operation_legal_configuration_and_users_into_tabs(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('company-settings.index')
            ->assertSee('Operación')
            ->assertSee('Configuración legal')
            ->assertSee('Usuarios')
            ->assertSee('Periodo de cierre')
            ->assertDontSee('Reglas base del pais')
            ->set('activeTab', 'legal')
            ->assertSee('Reglas base del pais')
            ->assertDontSee('Periodo de cierre')
            ->set('activeTab', 'users')
            ->assertSee('Administrar usuarios')
            ->assertDontSee('Reglas base del pais');
    }

    public function test_settings_validation_rejects_invalid_closure_day(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('company-settings.index')
            ->set('settingsForm.default_closure_day', 40)
            ->call('updateSettings')
            ->assertHasErrors(['settingsForm.default_closure_day']);
    }

    public function test_kiosk_key_requires_uppercase_number_and_symbol(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('company-settings.index')
            ->set('settingsForm.kiosk_key', 'kioskodemo')
            ->call('updateSettings')
            ->assertHasErrors(['settingsForm.kiosk_key']);
    }

    public function test_kiosk_key_must_be_unique_between_companies(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create();

        $otherCompany->setting()->create(array_replace(Company::defaultSettings(), [
            'kiosk_key_hash' => KioskKey::hash('KIOSK-REPETIDO1!'),
        ]));

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('company-settings.index')
            ->set('settingsForm.kiosk_key', 'KIOSK-REPETIDO1!')
            ->call('updateSettings')
            ->assertHasErrors(['settingsForm.kiosk_key']);
    }
}
