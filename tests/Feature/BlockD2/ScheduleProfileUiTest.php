<?php

namespace Tests\Feature\BlockD2;

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ScheduleProfileUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_sidebar_and_legacy_navigation_state(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);

        $this->assertTrue(Route::has('scheduling.profiles'));
        $this->assertTrue(Route::has('scheduling.profile-assignments'));
        $this->assertTrue(Route::has('schedules.index'));
        $this->assertTrue(Route::has('schedule-assignments.index'));

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Catálogo de turnos')
            ->assertSee('Perfiles de horario')
            ->assertSee('Asignaciones de perfiles')
            ->assertSee('Descansos obligatorios')
            ->assertDontSee('Horarios legacy')
            ->assertDontSee('Asignación de Horarios legacy');
    }

    public function test_profiles_ui_creates_pattern_and_calendar_profiles_and_manages_status(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $template = $this->shiftTemplate($company, 'APER', 'Apertura');
        $this->shiftTemplate($company, 'INA', 'Inactiva', 'inactive');
        $this->shiftTemplate(Company::factory()->create(['status' => 'active']), 'EXT', 'Externa');

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profiles')
            ->call('openCreatePanel')
            ->assertSee('Lunes')
            ->assertSee('Domingo')
            ->assertSee('Apertura')
            ->assertDontSee('Inactiva')
            ->assertDontSee('Externa')
            ->assertSee('Patron semanal')
            ->assertSee('se repite cada semana')
            ->set('form.code', 'OPAT')
            ->set('form.name', 'Oficina por patron')
            ->set('weeklyRules.0.shift_template_id', (string) $template->id)
            ->set('weeklyRules.1.shift_template_id', (string) $template->id)
            ->set('weeklyRules.2.shift_template_id', (string) $template->id)
            ->set('weeklyRules.3.shift_template_id', (string) $template->id)
            ->set('weeklyRules.4.shift_template_id', (string) $template->id)
            ->call('save')
            ->assertHasNoErrors();

        $pattern = ScheduleProfile::query()->where('code', 'OPAT')->firstOrFail();
        $this->assertSame('pattern', $pattern->profile_type);
        $this->assertSame('weekly', $pattern->pattern_mode);
        $this->assertSame(7, $pattern->weeklyRules()->count());

        Volt::test('scheduling.profiles')
            ->call('openCreatePanel')
            ->set('form.code', 'OCAL')
            ->set('form.name', 'Operacion por calendario')
            ->set('form.profile_type', 'calendar')
            ->assertSee('No se repite automaticamente')
            ->call('save')
            ->assertHasNoErrors();

        $calendar = ScheduleProfile::query()->where('code', 'OCAL')->firstOrFail();
        $this->assertSame('calendar', $calendar->profile_type);
        $this->assertNull($calendar->pattern_mode);
        $this->assertSame(0, $calendar->weeklyRules()->count());

        Volt::test('scheduling.profiles')
            ->call('loadEditForm', $pattern->id)
            ->set('form.name', 'Oficina por patron editada')
            ->call('save')
            ->assertHasNoErrors()
            ->call('inactivate', $pattern->id)
            ->assertHasNoErrors()
            ->call('reactivate', $pattern->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('schedule_profiles', [
            'company_id' => $company->id,
            'code' => 'OPAT',
            'name' => 'Oficina por patron editada',
            'profile_type' => 'pattern',
            'pattern_mode' => 'weekly',
            'status' => 'active',
        ]);
    }

    public function test_profile_assignments_ui_creates_replaces_ends_and_resolves_inheritance(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $center = Center::factory()->for($company)->create(['status' => 'active', 'name' => 'Planta']);
        $unit = OrganizationalUnit::factory()->for($company)->for($center)->create(['status' => 'active', 'name' => 'Administracion']);
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create(['center_id' => $center->id, 'started_at' => '2026-08-01']);
        $worker = $relationship->worker;
        $base = $this->patternProfile($company, 'BASE', 'Base');
        $calendar = app(CreateScheduleProfileAction::class)->handle($company, ['code' => 'CAL', 'name' => 'Calendario', 'profile_type' => 'calendar']);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profile-assignments')
            ->call('openAssignmentPanel')
            ->set('assignmentForm.schedule_profile_id', (string) $base->id)
            ->set('assignmentForm.effective_from', '2026-08-01')
            ->call('saveAssignment')
            ->assertHasNoErrors();

        Volt::test('scheduling.profile-assignments')
            ->call('openAssignmentPanel')
            ->set('assignmentForm.assignment_scope', 'center')
            ->set('assignmentForm.schedule_profile_id', (string) $calendar->id)
            ->set('assignmentForm.center_id', (string) $center->id)
            ->set('assignmentForm.effective_from', '2026-08-01')
            ->call('saveAssignment')
            ->assertHasNoErrors();

        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, ['effective_from' => '2026-08-01']);

        Volt::test('scheduling.profile-assignments')
            ->call('openAssignmentPanel')
            ->set('assignmentForm.assignment_scope', 'organizational_unit')
            ->set('assignmentForm.schedule_profile_id', (string) $base->id)
            ->set('assignmentForm.center_id', (string) $center->id)
            ->set('assignmentForm.organizational_unit_id', (string) $unit->id)
            ->set('assignmentForm.effective_from', '2026-08-01')
            ->call('saveAssignment')
            ->assertHasNoErrors();

        Volt::test('scheduling.profile-assignments')
            ->call('openAssignmentPanel')
            ->set('assignmentForm.assignment_scope', 'employment_relationship')
            ->set('assignmentForm.schedule_profile_id', (string) $calendar->id)
            ->set('assignmentForm.effective_from', '2026-09-01')
            ->call('selectWorker', $worker->id)
            ->call('saveAssignment')
            ->assertHasNoErrors()
            ->set('resolveForm.date', '2026-09-15')
            ->set('resolveWorkerSearch', $worker->full_name)
            ->call('selectResolveWorker', $worker->id)
            ->assertSee('Perfil efectivo')
            ->assertSee('Calendario')
            ->assertSee('Relacion laboral');

        $direct = ScheduleProfileAssignment::query()
            ->where('assignment_scope', 'employment_relationship')
            ->where('employment_relationship_id', $relationship->id)
            ->firstOrFail();

        Volt::test('scheduling.profile-assignments')
            ->call('openReplacePanel', $direct->id)
            ->set('replaceForm.schedule_profile_id', (string) $base->id)
            ->set('replaceForm.effective_from', '2026-10-01')
            ->set('replaceForm.reason', 'Cambio de operacion')
            ->call('replaceAssignment')
            ->assertHasNoErrors();

        $replacement = ScheduleProfileAssignment::query()
            ->where('assignment_scope', 'employment_relationship')
            ->where('employment_relationship_id', $relationship->id)
            ->where('status', 'active')
            ->firstOrFail();

        Volt::test('scheduling.profile-assignments')
            ->call('openEndPanel', $replacement->id)
            ->set('endForm.effective_to', '2026-10-31')
            ->set('endForm.reason', 'Retirar excepcion')
            ->call('endAssignment')
            ->assertHasNoErrors()
            ->assertSee('configuracion heredada');

        $this->assertDatabaseHas('schedule_profile_assignments', ['status' => 'replaced', 'id' => $direct->id]);
        $this->assertDatabaseHas('schedule_profile_assignments', ['status' => 'inactive', 'id' => $replacement->id]);
    }

    public function test_supervisor_can_only_assign_direct_relationship_inside_scope(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['status' => 'active']);
        $otherCenter = Center::factory()->for($company)->create(['status' => 'active']);
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create(['center_id' => $center->id]);
        $foreignRelationship = EmploymentRelationship::factory()->forCompany($company)->create(['center_id' => $otherCenter->id]);
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);
        $profile = $this->patternProfile($company, 'BASE', 'Base');

        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => now()->toDateString()], center: $center);

        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profile-assignments')
            ->call('openAssignmentPanel')
            ->assertSet('assignmentForm.assignment_scope', 'employment_relationship')
            ->set('assignmentForm.schedule_profile_id', (string) $profile->id)
            ->set('assignmentForm.effective_from', now()->toDateString())
            ->call('selectWorker', $relationship->worker_id)
            ->call('saveAssignment')
            ->assertHasNoErrors();

        Volt::test('scheduling.profile-assignments')
            ->call('openAssignmentPanel')
            ->set('assignmentForm.schedule_profile_id', (string) $profile->id)
            ->set('assignmentForm.effective_from', now()->toDateString())
            ->call('selectWorker', $foreignRelationship->worker_id)
            ->assertForbidden();
    }

    private function patternProfile(Company $company, string $code, string $name): ScheduleProfile
    {
        $template = $this->shiftTemplate($company, 'T'.$code, 'Turno '.$name);

        return app(CreateScheduleProfileAction::class)->handle($company, [
            'code' => $code,
            'name' => $name,
            'profile_type' => 'pattern',
            'pattern_mode' => 'weekly',
        ], $this->weeklyRules($template));
    }

    private function shiftTemplate(Company $company, string $code, string $name, string $status = 'active'): ShiftTemplate
    {
        return app(CreateShiftTemplateAction::class)->handle($company, [
            'code' => $code,
            'name' => $name,
            'status' => $status,
        ], [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'sort_order' => 1],
        ]);
    }

    private function weeklyRules(ShiftTemplate $template): array
    {
        return [
            ['day_of_week' => 1, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 2, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 3, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 4, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 5, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['day_of_week' => 6, 'day_type' => 'rest'],
            ['day_of_week' => 7, 'day_type' => 'rest'],
        ];
    }

    private function userWithCompanyRole(Company $company, string $roleKey): User
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => strtoupper($roleKey), 'description' => 'Rol prueba', 'is_system' => true],
        );
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return $user->refresh();
    }
}
