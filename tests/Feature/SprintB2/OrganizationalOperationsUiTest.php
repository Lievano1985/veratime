<?php

namespace Tests\Feature\SprintB2;

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\CreateOrganizationalUnitAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OperationalScopeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OrganizationalOperationsUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-10 09:00:00');
        CarbonImmutable::setTestNow('2026-08-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_company_manager_can_create_department_area_and_team_from_ui(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.units')
            ->set('form.center_id', (string) $center->id)
            ->set('form.type', 'department')
            ->set('form.code', 'OPS')
            ->set('form.name', 'Operaciones')
            ->call('save');

        $department = OrganizationalUnit::query()->where('code', 'OPS')->firstOrFail();

        Volt::test('organization.units')
            ->set('form.center_id', (string) $center->id)
            ->set('form.type', 'area')
            ->set('form.parent_id', (string) $department->id)
            ->set('form.code', 'PROD')
            ->set('form.name', 'Produccion')
            ->call('save');

        $area = OrganizationalUnit::query()->where('code', 'PROD')->firstOrFail();

        Volt::test('organization.units')
            ->set('form.center_id', (string) $center->id)
            ->set('form.type', 'team')
            ->set('form.parent_id', (string) $area->id)
            ->set('form.code', 'PROD-A')
            ->set('form.name', 'Turno A')
            ->call('save');

        $this->assertDatabaseHas('organizational_units', [
            'company_id' => $company->id,
            'center_id' => $center->id,
            'parent_id' => $area->id,
            'code' => 'PROD-A',
            'type' => 'team',
        ]);
    }

    public function test_supervisor_units_view_is_limited_to_active_operational_scope(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $otherCenter = Center::factory()->for($company)->create(['status' => 'active']);
        $department = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('OPS', 'Operaciones', 'department'));
        $area = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('PROD', 'Produccion', 'area'), $department);
        app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('PROD-A', 'Turno A', 'team'), $area);
        app(CreateOrganizationalUnitAction::class)->handle($company, $otherCenter, $this->unitData('FIN', 'Finanzas', 'department'));
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);

        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id]);

        $this->get(route('organization.units'))
            ->assertOk()
            ->assertDontSee('Operaciones')
            ->assertDontSee('Produccion')
            ->assertDontSee('Turno A')
            ->assertDontSee('Finanzas')
            ->assertDontSee('Nueva unidad');

        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => '2026-08-01'], unit: $area);

        $this->get(route('organization.units'))
            ->assertOk()
            ->assertDontSee('Operaciones')
            ->assertSee('Produccion')
            ->assertSee('Turno A')
            ->assertDontSee('Finanzas')
            ->assertDontSee('Nueva unidad');
    }

    public function test_unit_form_blocks_parent_from_other_company(): void
    {
        [$company, $center] = $this->companyAndCenter();
        [$foreignCompany, $foreignCenter] = $this->companyAndCenter();
        $foreignParent = app(CreateOrganizationalUnitAction::class)->handle($foreignCompany, $foreignCenter, $this->unitData('EXT', 'Externa', 'department'));
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.units')
            ->set('form.center_id', (string) $center->id)
            ->set('form.type', 'area')
            ->set('form.parent_id', (string) $foreignParent->id)
            ->set('form.code', 'BAD')
            ->set('form.name', 'Invalida')
            ->call('save')
            ->assertHasErrors(['form.parent_id']);
    }

    public function test_inactivation_error_from_domain_is_visible(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $department = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('OPS', 'Operaciones', 'department'));
        app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('PROD', 'Produccion', 'area'), $department);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.units')
            ->call('inactivate', $department->id)
            ->assertHasErrors(['unit']);

        $this->assertSame('active', $department->refresh()->status);
    }

    public function test_assignments_ui_assigns_and_replaces_primary_unit_preserving_history(): void
    {
        [$company, $center, $relationship, $worker] = $this->relationshipContext();
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $first = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));
        $second = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('OPS', 'Operaciones', 'department'));

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.assignments')
            ->set('primaryForm.worker_ids', [$worker->id])
            ->set('primaryForm.organizational_unit_id', (string) $first->id)
            ->set('primaryForm.operation', 'assign')
            ->set('primaryForm.effective_from', '2026-08-01')
            ->call('savePrimary');

        Volt::test('organization.assignments')
            ->set('primaryForm.worker_ids', [$worker->id])
            ->set('primaryForm.organizational_unit_id', (string) $second->id)
            ->set('primaryForm.operation', 'replace')
            ->set('primaryForm.effective_from', '2026-08-15')
            ->set('primaryForm.reason', 'Cambio de area')
            ->call('savePrimary');

        $this->assertDatabaseHas('employment_unit_assignments', [
            'company_id' => $company->id,
            'employment_relationship_id' => $relationship->id,
            'organizational_unit_id' => $first->id,
            'status' => 'replaced',
        ]);
        $this->assertDatabaseHas('employment_unit_assignments', [
            'company_id' => $company->id,
            'employment_relationship_id' => $relationship->id,
            'organizational_unit_id' => $second->id,
            'status' => 'active',
        ]);
    }

    public function test_assignments_ui_assigns_primary_unit_to_multiple_workers(): void
    {
        [$company, $center, $relationship, $worker] = $this->relationshipContext();
        $secondWorker = Worker::factory()->for($company)->create(['status' => 'active']);
        $secondRelationship = EmploymentRelationship::factory()->for($company)->for($secondWorker)->for($center)->create([
            'started_at' => '2026-01-01',
            'status' => 'active',
        ]);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $unit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.assignments')
            ->set('primaryForm.worker_ids', [$worker->id, $secondWorker->id])
            ->assertSee('ADM - Administracion')
            ->set('primaryForm.organizational_unit_id', (string) $unit->id)
            ->set('primaryForm.operation', 'assign')
            ->set('primaryForm.effective_from', '2026-08-01')
            ->call('savePrimary')
            ->assertHasNoErrors()
            ->assertSee('Unidad principal guardada para 2 trabajadores.');

        foreach ([$relationship, $secondRelationship] as $activeRelationship) {
            $this->assertDatabaseHas('employment_unit_assignments', [
                'company_id' => $company->id,
                'employment_relationship_id' => $activeRelationship->id,
                'organizational_unit_id' => $unit->id,
                'assignment_type' => 'primary',
                'status' => 'active',
            ]);
        }
    }

    public function test_assignments_ui_default_operation_replaces_existing_primary_for_multiple_workers(): void
    {
        [$company, $center, $relationship, $worker] = $this->relationshipContext();
        $secondWorker = Worker::factory()->for($company)->create(['status' => 'active']);
        $secondRelationship = EmploymentRelationship::factory()->for($company)->for($secondWorker)->for($center)->create([
            'started_at' => '2026-01-01',
            'status' => 'active',
        ]);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $first = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));
        $second = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('FLEX', 'Flexibles', 'department'));

        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $first, ['effective_from' => '2026-08-01']);
        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $secondRelationship, $first, ['effective_from' => '2026-08-01']);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.assignments')
            ->assertSet('primaryForm.operation', 'replace')
            ->set('primaryForm.worker_ids', [$worker->id, $secondWorker->id])
            ->set('primaryForm.organizational_unit_id', (string) $second->id)
            ->set('primaryForm.effective_from', '2026-08-15')
            ->set('primaryForm.reason', 'Cambio de unidad principal')
            ->call('savePrimary')
            ->assertHasNoErrors()
            ->assertSee('Unidad principal guardada para 2 trabajadores.');

        foreach ([$relationship, $secondRelationship] as $activeRelationship) {
            $this->assertDatabaseHas('employment_unit_assignments', [
                'company_id' => $company->id,
                'employment_relationship_id' => $activeRelationship->id,
                'organizational_unit_id' => $first->id,
                'status' => 'replaced',
            ]);
            $this->assertDatabaseHas('employment_unit_assignments', [
                'company_id' => $company->id,
                'employment_relationship_id' => $activeRelationship->id,
                'organizational_unit_id' => $second->id,
                'status' => 'active',
            ]);
        }
    }

    public function test_assignments_ui_creates_and_ends_temporary_support(): void
    {
        [$company, $center, $relationship, $worker] = $this->relationshipContext();
        $supportCenter = Center::factory()->for($company)->create();
        $supportUnit = app(CreateOrganizationalUnitAction::class)->handle($company, $supportCenter, $this->unitData('SUP', 'Soporte', 'department'));
        $admin = $this->userWithCompanyRole($company, RoleKey::RH);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.assignments')
            ->set('supportForm.worker_ids', [$worker->id])
            ->set('supportForm.support_center_id', (string) $supportCenter->id)
            ->set('supportForm.organizational_unit_id', (string) $supportUnit->id)
            ->set('supportForm.effective_from', '2026-08-11')
            ->set('supportForm.effective_to', '2026-08-20')
            ->set('supportForm.reason', 'Apoyo temporal')
            ->call('saveSupport');

        $support = EmploymentUnitAssignment::query()->where('assignment_type', 'temporary_support')->firstOrFail();

        Volt::test('organization.assignments')
            ->call('openEndSupportPanel', $support->id)
            ->set('endForm.effective_to', '2026-08-18')
            ->set('endForm.reason', 'Fin de apoyo')
            ->call('endSupport');

        $this->assertDatabaseHas('employment_unit_assignments', [
            'id' => $support->id,
            'status' => 'inactive',
            'effective_to' => '2026-08-18 00:00:00',
            'reason' => 'Fin de apoyo',
        ]);
    }

    public function test_assignments_ui_creates_temporary_support_for_multiple_workers(): void
    {
        [$company, $center, $relationship, $worker] = $this->relationshipContext();
        $secondWorker = Worker::factory()->for($company)->create(['status' => 'active']);
        $secondRelationship = EmploymentRelationship::factory()->for($company)->for($secondWorker)->for($center)->create([
            'started_at' => '2026-01-01',
            'status' => 'active',
        ]);
        $supportCenter = Center::factory()->for($company)->create();
        $supportUnit = app(CreateOrganizationalUnitAction::class)->handle($company, $supportCenter, $this->unitData('SUP', 'Soporte', 'department'));
        $admin = $this->userWithCompanyRole($company, RoleKey::RH);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.assignments')
            ->set('supportForm.worker_ids', [$worker->id, $secondWorker->id])
            ->set('supportForm.support_center_id', (string) $supportCenter->id)
            ->set('supportForm.organizational_unit_id', (string) $supportUnit->id)
            ->set('supportForm.effective_from', '2026-08-11')
            ->set('supportForm.effective_to', '2026-08-20')
            ->set('supportForm.reason', 'Apoyo temporal multiple')
            ->call('saveSupport')
            ->assertHasNoErrors()
            ->assertSee('Apoyo temporal guardado para 2 trabajadores.');

        foreach ([$relationship, $secondRelationship] as $activeRelationship) {
            $this->assertDatabaseHas('employment_unit_assignments', [
                'company_id' => $company->id,
                'employment_relationship_id' => $activeRelationship->id,
                'organizational_unit_id' => $supportUnit->id,
                'assignment_type' => 'temporary_support',
                'status' => 'active',
            ]);
        }
    }

    public function test_assignment_worker_selector_can_show_all_active_workers_for_the_company(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);

        foreach (range(1, 15) as $index) {
            $worker = Worker::factory()->for($company)->create([
                'employee_code' => sprintf('ORG-%03d', $index),
                'full_name' => sprintf('Trabajador Demo %02d', $index),
                'status' => 'active',
            ]);
            EmploymentRelationship::factory()->for($company)->for($worker)->for($center)->create([
                'started_at' => '2026-01-01',
                'status' => 'active',
            ]);
        }

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('workers.multi-select', ['mode' => 'single', 'resultLimit' => 150])
            ->set('open', true)
            ->assertSee('ORG-001')
            ->assertSee('ORG-008')
            ->assertSee('ORG-015');
    }

    public function test_assignment_worker_selector_shows_primary_assignment_status(): void
    {
        [$company, $center, $relationship, $worker] = $this->relationshipContext();
        $unassignedWorker = Worker::factory()->for($company)->create([
            'employee_code' => 'ORG-999',
            'full_name' => 'Trabajador Sin Unidad',
            'status' => 'active',
        ]);
        EmploymentRelationship::factory()->for($company)->for($unassignedWorker)->for($center)->create([
            'started_at' => '2026-01-01',
            'status' => 'active',
        ]);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $unit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));

        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, ['effective_from' => '2026-08-01']);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('workers.multi-select', [
            'resultLimit' => 150,
            'showPrimaryAssignmentStatus' => true,
            'assignmentDate' => '2026-08-10',
        ])
            ->set('open', true)
            ->assertSee($worker->employee_code)
            ->assertSee($unassignedWorker->employee_code)
            ->assertSee('Ya asignado')
            ->assertSee('Sin unidad principal');
    }
    public function test_assignments_table_can_filter_by_organizational_unit(): void
    {
        [$company, $center, $relationship, $worker] = $this->relationshipContext();
        $worker->update([
            'employee_code' => 'UNIT-001',
            'full_name' => 'Trabajador Unidad Uno',
        ]);
        $secondWorker = Worker::factory()->for($company)->create([
            'employee_code' => 'UNIT-002',
            'full_name' => 'Trabajador Unidad Dos',
            'status' => 'active',
        ]);
        $secondRelationship = EmploymentRelationship::factory()->for($company)->for($secondWorker)->for($center)->create([
            'started_at' => '2026-01-01',
            'status' => 'active',
        ]);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $firstUnit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('U-UNO', 'Unidad Uno', 'department'));
        $secondUnit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('U-DOS', 'Unidad Dos', 'department'));

        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $firstUnit, ['effective_from' => '2026-08-01']);
        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $secondRelationship, $secondUnit, ['effective_from' => '2026-08-01']);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.assignments')
            ->assertSee('Trabajador Unidad Uno')
            ->assertSee('Trabajador Unidad Dos')
            ->set('filters.organizational_unit_id', (string) $firstUnit->id)
            ->assertSee('Trabajador Unidad Uno')
            ->assertSee('Unidad Uno')
            ->assertDontSee('Trabajador Unidad Dos');
    }
    public function test_assignments_ui_blocks_unit_from_other_company(): void
    {
        [$company, $center, $relationship, $worker] = $this->relationshipContext();
        [$foreignCompany, $foreignCenter] = $this->companyAndCenter();
        $foreignUnit = app(CreateOrganizationalUnitAction::class)->handle($foreignCompany, $foreignCenter, $this->unitData('EXT', 'Externa', 'department'));
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.assignments')
            ->set('primaryForm.worker_ids', [$worker->id])
            ->set('primaryForm.organizational_unit_id', (string) $foreignUnit->id)
            ->set('primaryForm.operation', 'assign')
            ->set('primaryForm.effective_from', '2026-08-01')
            ->call('savePrimary')
            ->assertHasErrors(['primaryForm.organizational_unit_id']);
    }

    public function test_scopes_ui_assigns_unit_scope_and_blocks_non_supervisor(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $unit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('OPS', 'Operaciones', 'department'));
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);
        $rh = $this->userWithCompanyRole($company, RoleKey::RH);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.scopes')
            ->set('form.user_id', (string) $supervisor->id)
            ->set('form.scope_kind', 'unit')
            ->set('form.organizational_unit_id', (string) $unit->id)
            ->set('form.responsibility_type', 'responsible')
            ->set('form.effective_from', '2026-08-01')
            ->set('form.reason', 'Responsable del area')
            ->call('save');

        Volt::test('organization.scopes')
            ->set('form.user_id', (string) $rh->id)
            ->set('form.scope_kind', 'center')
            ->set('form.center_id', (string) $center->id)
            ->set('form.effective_from', '2026-08-01')
            ->set('form.reason', 'No debe requerir scope')
            ->call('save')
            ->assertHasErrors(['form.user_id']);

        $this->assertDatabaseHas('operational_scope_assignments', [
            'company_id' => $company->id,
            'user_id' => $supervisor->id,
            'organizational_unit_id' => $unit->id,
            'responsibility_type' => 'responsible',
            'status' => 'active',
        ]);
    }

    public function test_scopes_ui_replaces_and_ends_scope_preserving_history(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('organization.scopes')
            ->set('form.user_id', (string) $supervisor->id)
            ->set('form.scope_kind', 'center')
            ->set('form.center_id', (string) $center->id)
            ->set('form.operation', 'assign')
            ->set('form.effective_from', '2026-08-01')
            ->set('form.reason', 'Alta')
            ->call('save');

        Volt::test('organization.scopes')
            ->set('form.user_id', (string) $supervisor->id)
            ->set('form.scope_kind', 'center')
            ->set('form.center_id', (string) $center->id)
            ->set('form.operation', 'replace')
            ->set('form.effective_from', '2026-08-15')
            ->set('form.reason', 'Cambio de vigencia')
            ->call('save');

        $active = OperationalScopeAssignment::query()->where('status', 'active')->firstOrFail();

        Volt::test('organization.scopes')
            ->call('openEndPanel', $active->id)
            ->set('endForm.effective_to', '2026-08-20')
            ->set('endForm.reason', 'Fin de responsabilidad')
            ->call('endScope');

        $this->assertDatabaseHas('operational_scope_assignments', ['status' => 'replaced']);
        $this->assertDatabaseHas('operational_scope_assignments', [
            'id' => $active->id,
            'status' => 'inactive',
            'reason' => 'Fin de responsabilidad',
        ]);
    }

    public function test_my_scope_shows_only_authorized_workers_and_empty_state(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $otherCenter = Center::factory()->for($company)->create();
        $otherWorker = Worker::factory()->for($company)->create(['employee_code' => 'OUT']);
        EmploymentRelationship::factory()->for($company)->for($otherWorker)->for($otherCenter)->create([
            'started_at' => '2026-01-01',
            'status' => 'active',
        ]);
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);

        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id]);
        $this->get(route('organization.my-scope'))
            ->assertOk()
            ->assertSee('Sin alcance operativo');

        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => '2026-08-01'], center: $center);

        $this->get(route('organization.my-scope'))
            ->assertOk()
            ->assertSee($relationship->worker->full_name)
            ->assertDontSee($otherWorker->full_name);
    }

    public function test_guest_is_blocked_from_organization_routes(): void
    {
        $this->get(route('organization.units'))->assertRedirect(route('login'));
        $this->get(route('organization.assignments'))->assertRedirect(route('login'));
        $this->get(route('organization.scopes'))->assertRedirect(route('login'));
        $this->get(route('organization.my-scope'))->assertRedirect(route('login'));
    }

    public function test_supervisor_cannot_access_administrative_organization_routes_directly(): void
    {
        [$company] = $this->companyAndCenter();
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);

        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id]);

        $this->get(route('organization.assignments'))->assertForbidden();
        $this->get(route('organization.scopes'))->assertForbidden();
    }

    private function companyAndCenter(): array
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['status' => 'active']);

        return [$company, $center];
    }

    private function relationshipContext(): array
    {
        [$company, $center] = $this->companyAndCenter();
        $worker = Worker::factory()->for($company)->create(['status' => 'active']);
        $relationship = EmploymentRelationship::factory()->for($company)->for($worker)->for($center)->create([
            'started_at' => '2026-01-01',
            'status' => 'active',
        ]);

        return [$company, $center, $relationship, $worker];
    }

    private function unitData(string $code, string $name, string $type): array
    {
        return ['code' => $code, 'name' => $name, 'type' => $type];
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
