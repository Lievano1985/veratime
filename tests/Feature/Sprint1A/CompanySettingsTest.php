<?php

namespace Tests\Feature\Sprint1A;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Identidad')
            ->assertSee('Usuarios')
            ->assertSee('Periodo de cierre')
            ->assertDontSee('Clave de kiosco')
            ->assertDontSee('Reglas base del pais')
            ->set('activeTab', 'legal')
            ->assertSee('Reglas base del pais')
            ->assertDontSee('Periodo de cierre')
            ->set('activeTab', 'users')
            ->assertSee('Administrar usuarios')
            ->assertDontSee('Reglas base del pais');
    }

    public function test_company_admin_can_upload_a_brand_image_for_the_kiosk(): void
    {
        Storage::fake('public');

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
            ->set('activeTab', 'identity')
            ->set('companyBrandImage', UploadedFile::fake()->image('empresa.png', 600, 300))
            ->call('updateCompanyBrandingImage')
            ->assertHasNoErrors()
            ->assertSee('Imagen actual');

        $path = (string) $company->setting()->value('branding_image_path');

        $this->assertStringStartsWith("companies/{$company->id}/branding/", $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_company_admin_can_save_a_mobile_marking_policy_draft_from_settings(): void
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
            ->set('activeTab', 'mobile-marking')
            ->set('mobilePolicyForm.mode', 'circle')
            ->set('mobilePolicyForm.requires_device_binding', true)
            ->set('mobilePolicyForm.center_latitude', '17.9971944')
            ->set('mobilePolicyForm.center_longitude', '-92.9328333')
            ->set('mobilePolicyForm.radius_meters', 150)
            ->set('mobilePolicyForm.offline_authorization_duration_minutes', 720)
            ->call('saveMobileMarkingPolicyDraft')
            ->assertHasNoErrors()
            ->assertSee('Borrador de política de marcaje móvil guardado.');

        $this->assertDatabaseHas('mobile_marking_policies', [
            'company_id' => $company->id,
            'mode' => 'circle',
            'center_latitude' => '17.9971944',
            'center_longitude' => '-92.9328333',
            'radius_meters' => 150,
            'offline_authorization_duration_minutes' => 720,
        ]);
    }

    public function test_mobile_policy_shows_the_reason_when_offline_marking_has_no_authorized_device(): void
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
            ->set('activeTab', 'mobile-marking')
            ->set('mobilePolicyForm.offline_authorization_duration_minutes', 720)
            ->call('saveMobileMarkingPolicyDraft')
            ->assertHasErrors(['mobilePolicyForm.offline_authorization_duration_minutes']);

        $this->assertDatabaseMissing('mobile_marking_policies', [
            'company_id' => $company->id,
        ]);
    }

    public function test_mobile_circle_policy_offers_a_map_picker_for_coordinates(): void
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
            ->set('activeTab', 'mobile-marking')
            ->set('mobilePolicyForm.mode', 'circle')
            ->assertSee('Elegir en mapa')
            ->assertSee('Usar mi ubicación actual');
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
}
