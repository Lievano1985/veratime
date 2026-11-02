<?php

namespace Tests\Feature;

use App\Domains\Companies\Actions\UpdateCompanySettingsAction;
use App\Domains\Scheduling\Actions\UpdateSchedulingInterfaceAction;
use App\Domains\Scheduling\Support\SchedulingInterface;
use App\Models\Center;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SchedulingInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(string $role = RoleKey::RH_ADMIN): array
    {
        $company = Company::factory()->create(['status' => 'active']);
        Center::factory()->for($company)->create(['status' => 'active']);
        $user = User::factory()->create(['status' => 'active']);
        $companyRole = Role::firstOrCreate(['key' => $role], ['name' => $role, 'is_system' => true]);
        $user->companies()->attach($company, ['role_id' => $companyRole->id, 'status' => 'active', 'is_default' => true]);
        $super = User::factory()->create(['status' => 'active', 'global_role' => RoleKey::SUPER_ADMIN]);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        return [$company, $user, $super];
    }

    public function test_new_company_uses_planner_and_only_active_model_is_shown_and_accessible(): void
    {
        [$company] = $this->scenario();
        $this->assertSame('planner', SchedulingInterface::forCompany($company));
        $this->get(route('scheduling.planner'))->assertOk()->assertSee('Planificador de horarios')
            ->assertSee('Catálogo de turnos')->assertDontSee('Aplicacion de modelos')->assertDontSee('Programacion semanal');
        foreach (['scheduling.daily', 'scheduling.profiles', 'scheduling.profile-assignments', 'schedules.index', 'schedule-assignments.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('scheduling.shifts'))->assertOk();
    }

    public function test_only_super_admin_can_see_and_switch_model_for_current_company(): void
    {
        [$company, , $super] = $this->scenario();
        $other = Company::factory()->create();
        $this->actingAs($super)->withSession(['current_company_id' => $company->id]);
        Volt::test('company-settings.index')->assertSee('Forma de crear horarios')->assertSet('schedulingInterface', 'planner')
            ->set('schedulingInterface', 'classic')->call('updateSchedulingInterface')->assertHasNoErrors()
            ->assertRedirect(route('company-settings.index'));
        $this->assertSame('classic', SchedulingInterface::forCompany($company));
        $this->assertSame('planner', SchedulingInterface::forCompany($other));
        $this->assertSame($super->id, $company->setting()->firstOrFail()->metadata['scheduling_interface_changed_by']);
        $this->get(route('scheduling.daily'))->assertOk()->assertSee('Aplicacion de modelos')->assertDontSee('Planificador de horarios');
        $this->get(route('scheduling.profiles'))->assertOk();
        $this->get(route('scheduling.planner'))->assertForbidden();
        $this->get(route('scheduling.shifts'))->assertOk();
        Volt::test('company-settings.index')->set('schedulingInterface', 'planner')->call('updateSchedulingInterface')->assertHasNoErrors();
        $this->get(route('scheduling.planner'))->assertOk();
    }

    public function test_company_admin_cannot_see_or_call_the_activation_control(): void
    {
        [$company] = $this->scenario(RoleKey::ADMIN_EMPRESA);
        Volt::test('company-settings.index')->assertDontSee('Forma de crear horarios')
            ->assertDontSee('Solo superadministrador')->assertSet('schedulingInterface', '')
            ->set('schedulingInterface', 'classic')->call('updateSchedulingInterface')->assertForbidden();
        $this->assertSame('planner', SchedulingInterface::forCompany($company));
    }

    public function test_general_settings_save_preserves_super_admin_selection_and_other_metadata(): void
    {
        [$company, , $super] = $this->scenario();
        $company->setting()->updateOrCreate(['company_id' => $company->id], ['metadata' => ['example' => 'preserved']]);
        app(UpdateSchedulingInterfaceAction::class)->handle($super, $company, 'classic');
        app(UpdateCompanySettingsAction::class)->handle($company, array_replace(Company::defaultSettings(), [
            'default_timezone' => 'America/Mexico_City', 'metadata' => ['scheduling_interface' => 'planner'],
        ]));
        $this->assertSame('classic', SchedulingInterface::forCompany($company));
        $this->assertSame('preserved', $company->setting()->firstOrFail()->metadata['example']);
    }

    public function test_invalid_selection_is_rejected_without_changing_company(): void
    {
        [$company, , $super] = $this->scenario();
        $this->actingAs($super)->withSession(['current_company_id' => $company->id]);
        Volt::test('company-settings.index')->set('schedulingInterface', 'both')->call('updateSchedulingInterface')->assertHasErrors('schedulingInterface');
        $this->assertSame('planner', SchedulingInterface::forCompany($company));
    }

    public function test_open_planner_livewire_session_is_blocked_after_switch_to_classic(): void
    {
        [$company, , $super] = $this->scenario();
        $response = $this->get(route('scheduling.planner'))->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $candidate = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $data = json_decode($candidate, true);
            if (($data['memo']['name'] ?? '') === 'scheduling.planner') {
                $snapshot = $candidate;
                break;
            }
        }
        $this->assertNotNull($snapshot);
        app(UpdateSchedulingInterfaceAction::class)->handle($super, $company, 'classic');
        $this->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'openWeek', 'params' => [false]]],
        ]]])->assertForbidden();
        $this->assertSame(0, $company->scheduleBatches()->count());
    }
}
