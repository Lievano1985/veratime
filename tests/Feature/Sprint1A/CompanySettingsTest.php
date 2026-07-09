<?php

namespace Tests\Feature\Sprint1A;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_or_update_company_settings(): void
    {
        $role = Role::factory()->create(['key' => 'admin']);
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.index')
            ->set('settingsForm.payroll_period_type', 'weekly')
            ->set('settingsForm.default_timezone', 'America/Mazatlan')
            ->set('settingsForm.default_closure_day', 5)
            ->set('settingsForm.allow_worker_corrections', true)
            ->set('settingsForm.require_pin_for_kiosk', false)
            ->set('settingsForm.require_pin_for_confirmation', true)
            ->call('updateSettings');

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'payroll_period_type' => 'weekly',
            'default_timezone' => 'America/Mazatlan',
            'default_closure_day' => 5,
            'allow_worker_corrections' => true,
            'require_pin_for_kiosk' => false,
            'require_pin_for_confirmation' => true,
        ]);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'timezone' => 'America/Mazatlan',
        ]);
    }

    public function test_settings_validation_rejects_invalid_closure_day(): void
    {
        $role = Role::factory()->create(['key' => 'admin']);
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.index')
            ->set('settingsForm.default_closure_day', 40)
            ->call('updateSettings')
            ->assertHasErrors(['settingsForm.default_closure_day']);
    }
}
