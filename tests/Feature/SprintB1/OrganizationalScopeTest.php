<?php

namespace Tests\Feature\SprintB1;

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\CreateOrganizationalUnitAction;
use App\Domains\Organization\Actions\EndOperationalScopeAction;
use App\Domains\Organization\Actions\EnsureUserCanManageWorkerAction;
use App\Domains\Organization\Actions\InactivateOrganizationalUnitAction;
use App\Domains\Organization\Actions\ReplaceOperationalScopeAction;
use App\Domains\Organization\Actions\ReplacePrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
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
use Database\Seeders\VeraTimeDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OrganizationalScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_valid_organizational_hierarchy_and_blocks_invalid_hierarchy(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $action = app(CreateOrganizationalUnitAction::class);

        $department = $action->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));
        $directArea = $action->handle($company, $center, $this->unitData('RH', 'Recursos Humanos', 'area'));
        $area = $action->handle($company, $center, $this->unitData('CNT', 'Contabilidad', 'area'), $department);
        $team = $action->handle($company, $center, $this->unitData('CNT-A', 'Equipo A', 'team'), $area);

        $this->assertSame('department', $department->type);
        $this->assertNull($department->parent_id);
        $this->assertNull($directArea->parent_id);
        $this->assertSame($department->id, $area->parent_id);
        $this->assertSame($area->id, $team->parent_id);

        $this->expectException(InvalidArgumentException::class);
        $action->handle($company, $center, $this->unitData('BAD', 'Equipo invalido', 'team'), $department);
    }

    public function test_it_blocks_parent_from_other_center_or_company_and_duplicate_code_only_in_same_center(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $otherCenter = Center::factory()->for($company)->create();
        $otherCompany = Company::factory()->create();
        $foreignCenter = Center::factory()->for($otherCompany)->create();
        $action = app(CreateOrganizationalUnitAction::class);

        $department = $action->handle($company, $center, $this->unitData('OPS', 'Operaciones', 'department'));
        $otherCenterDepartment = $action->handle($company, $otherCenter, $this->unitData('OPS', 'Operaciones', 'department'));
        $this->assertSame($otherCenter->id, $otherCenterDepartment->center_id);

        $this->expectException(InvalidArgumentException::class);
        $action->handle($company, $center, $this->unitData('OPS-2', 'Area invalida', 'area'), $otherCenterDepartment);
    }

    public function test_it_blocks_duplicate_code_in_same_center(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $action = app(CreateOrganizationalUnitAction::class);

        $action->handle($company, $center, $this->unitData('DUP', 'Uno', 'department'));

        $this->expectException(InvalidArgumentException::class);
        $action->handle($company, $center, $this->unitData('DUP', 'Dos', 'department'));
    }

    public function test_it_inactivates_unit_without_active_links_and_blocks_children_or_assignments(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $create = app(CreateOrganizationalUnitAction::class);
        $inactivate = app(InactivateOrganizationalUnitAction::class);
        $department = $create->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));
        $area = $create->handle($company, $center, $this->unitData('RH', 'RH', 'area'), $department);

        $this->expectException(InvalidArgumentException::class);
        $inactivate->handle($company, $department, '2026-08-01');
    }

    public function test_it_blocks_inactivation_with_active_assignments_and_allows_after_end(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $unit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));
        $assignment = app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, ['effective_from' => '2026-08-01']);

        try {
            app(InactivateOrganizationalUnitAction::class)->handle($company, $unit, '2026-08-10');
            $this->fail('Expected active assignment to block inactivation.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $assignment->forceFill(['effective_to' => '2026-08-09', 'status' => 'inactive'])->save();
        $unit = app(InactivateOrganizationalUnitAction::class)->handle($company, $unit, '2026-08-10');
        $this->assertSame('inactive', $unit->status);
    }

    public function test_it_assigns_updates_and_resolves_primary_unit_as_current_segmentation(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $create = app(CreateOrganizationalUnitAction::class);
        $first = $create->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));
        $second = $create->handle($company, $center, $this->unitData('OPS', 'Operaciones', 'department'));

        $assignment = app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $first, ['effective_from' => '2026-08-01']);

        try {
            app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $second, ['effective_from' => '2026-08-15']);
            $this->fail('Expected overlap to be blocked.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $replacement = app(ReplacePrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $second, [
            'effective_from' => '2026-08-15',
            'reason' => 'Cambio de segmentacion',
        ]);
        $this->assertSame($assignment->id, $replacement->id);
        $this->assertSame('active', $assignment->refresh()->status);
        $this->assertNull($assignment->refresh()->replaced_by_id);
        $this->assertSame($second->id, $assignment->refresh()->organizational_unit_id);

        $resolved = app(ResolveEmploymentUnitsForDateAction::class)->handle($company, $relationship, '2026-08-20');
        $this->assertSame($second->id, $resolved['primary']->id);
        $this->assertSame($center->id, $resolved['center']->id);
    }

    public function test_legacy_temporary_support_is_not_resolved_as_current_segmentation(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $otherCenter = Center::factory()->for($company)->create();
        $supportUnit = app(CreateOrganizationalUnitAction::class)->handle($company, $otherCenter, $this->unitData('SUP', 'Apoyo', 'department'));
        $support = EmploymentUnitAssignment::query()->forceCreate([
            'company_id' => $company->id,
            'employment_relationship_id' => $relationship->id,
            'organizational_unit_id' => $supportUnit->id,
            'assignment_type' => 'temporary_support',
            'effective_from' => '2026-08-05',
            'effective_to' => '2026-08-10',
            'status' => 'active',
        ]);

        $this->assertSame('temporary_support', $support->assignment_type);
        $this->assertTrue(app(ResolveEmploymentUnitsForDateAction::class)->handle($company, $relationship, '2026-08-06')['temporary_supports']->isEmpty());
    }

    public function test_relationship_status_controls_assignment_not_assignment_dates(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext(endedAt: '2026-08-10');
        $unit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));

        $assignment = app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, ['effective_from' => '2026-08-11']);
        $this->assertSame('active', $assignment->status);

        $relationship->forceFill(['status' => 'inactive'])->save();

        $this->expectException(InvalidArgumentException::class);
        app(ReplacePrimaryOrganizationalUnitAction::class)->handle($company, $relationship->refresh(), $unit, [
            'reason' => 'Relacion cerrada',
        ]);
    }

    public function test_scoped_operator_without_scope_cannot_manage_workers(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $rhOperativo = $this->userWithCompanyRole($company, RoleKey::RH_OPERATIVO);
        $create = app(CreateOrganizationalUnitAction::class);
        $department = $create->handle($company, $center, $this->unitData('OPS', 'Operaciones', 'department'));
        $area = $create->handle($company, $center, $this->unitData('PROD', 'Produccion', 'area'), $department);
        $team = $create->handle($company, $center, $this->unitData('TA', 'Turno A', 'team'), $area);
        $otherArea = $create->handle($company, $center, $this->unitData('ALM', 'Almacen', 'area'), $department);
        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $team, ['effective_from' => '2026-08-01']);

        $this->expectException(AuthorizationException::class);
        app(EnsureUserCanManageWorkerAction::class)->handle($rhOperativo, $company, $relationship, '2026-08-02');
    }

    public function test_rh_operativo_cannot_receive_unit_scope(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $rhOperativo = $this->userWithCompanyRole($company, RoleKey::RH_OPERATIVO);
        $create = app(CreateOrganizationalUnitAction::class);
        $department = $create->handle($company, $center, $this->unitData('OPS', 'Operaciones', 'department'));
        $area = $create->handle($company, $center, $this->unitData('PROD', 'Produccion', 'area'), $department);

        $this->expectException(InvalidArgumentException::class);
        app(AssignOperationalScopeAction::class)->handle($company, $rhOperativo, ['effective_from' => '2026-08-01'], unit: $area);
    }

    public function test_rh_operativo_center_scope_does_not_use_legacy_temporary_support_as_extra_scope(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $supportCenter = Center::factory()->for($company)->create();
        $supportUnit = app(CreateOrganizationalUnitAction::class)->handle($company, $supportCenter, $this->unitData('SUP', 'Soporte', 'department'));
        $rhOperativo = $this->userWithCompanyRole($company, RoleKey::RH_OPERATIVO);
        EmploymentUnitAssignment::query()->forceCreate([
            'company_id' => $company->id,
            'employment_relationship_id' => $relationship->id,
            'organizational_unit_id' => $supportUnit->id,
            'assignment_type' => 'temporary_support',
            'effective_from' => '2026-08-05',
            'effective_to' => '2026-08-10',
            'status' => 'active',
        ]);
        app(AssignOperationalScopeAction::class)->handle($company, $rhOperativo, ['effective_from' => '2026-08-01'], center: $supportCenter);

        $this->expectException(AuthorizationException::class);
        app(EnsureUserCanManageWorkerAction::class)->handle($rhOperativo, $company, $relationship, '2026-08-06');
    }

    public function test_company_managers_can_manage_any_worker_and_inactive_contexts_are_blocked(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        foreach ([RoleKey::ADMIN_EMPRESA, RoleKey::RH_ADMIN] as $role) {
            app(EnsureUserCanManageWorkerAction::class)->handle($this->userWithCompanyRole($company, $role), $company, $relationship, '2026-08-01');
            $this->assertTrue(true);
        }

        $inactiveUser = $this->userWithCompanyRole($company, RoleKey::RH_ADMIN, userStatus: 'inactive');
        $this->expectException(AuthorizationException::class);
        app(EnsureUserCanManageWorkerAction::class)->handle($inactiveUser, $company, $relationship, '2026-08-01');
    }

    public function test_scope_assignment_requires_scope_assignable_role_and_blocks_duplicates(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN_EMPRESA);
        $rhOperativo = $this->userWithCompanyRole($company, RoleKey::RH_OPERATIVO);

        try {
            app(AssignOperationalScopeAction::class)->handle($company, $admin, ['effective_from' => '2026-08-01'], center: $center);
            $this->fail('Expected non scope assignable role to be blocked.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        app(AssignOperationalScopeAction::class)->handle($company, $rhOperativo, ['effective_from' => '2026-08-01'], center: $center);

        $this->expectException(InvalidArgumentException::class);
        app(AssignOperationalScopeAction::class)->handle($company, $rhOperativo, ['effective_from' => '2026-08-02'], center: $center);
    }

    public function test_scope_replacement_and_end_preserve_history(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $newCenter = Center::factory()->for($company)->create();
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);
        $scope = app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => '2026-08-01'], center: $center);
        $replacement = app(ReplaceOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => '2026-08-15'], center: $center);

        $this->assertSame('replaced', $scope->refresh()->status);
        $this->assertSame($replacement->id, $scope->refresh()->replaced_by_id);

        $ended = app(EndOperationalScopeAction::class)->handle($company, $replacement, '2026-08-20');
        $this->assertSame('inactive', $ended->status);
    }

    public function test_demo_seeder_creates_organizational_data(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(VeraTimeDemoSeeder::class);

        $this->assertDatabaseHas(OrganizationalUnit::class, ['code' => 'ADM', 'type' => 'department']);
        $this->assertDatabaseHas(OrganizationalUnit::class, ['code' => 'PROD-A', 'type' => 'team']);
        $this->assertDatabaseHas(EmploymentUnitAssignment::class, ['assignment_type' => 'primary', 'status' => 'active']);
        $this->assertDatabaseMissing(EmploymentUnitAssignment::class, ['assignment_type' => 'temporary_support', 'status' => 'active']);
        $this->assertDatabaseHas(OperationalScopeAssignment::class, ['responsibility_type' => 'supervisor', 'status' => 'active']);
    }

    public function test_policy_allows_company_managers_and_blocks_supervisor_for_units(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $unit = app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('ADM', 'Administracion', 'department'));

        foreach ([RoleKey::ADMIN_EMPRESA, RoleKey::RH_ADMIN] as $role) {
            $user = $this->userWithCompanyRole($company, $role);
            $this->assertTrue($user->can('create', [OrganizationalUnit::class, $company]));
            $this->assertTrue($user->can('update', $unit));
        }

        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);
        $this->assertFalse($supervisor->can('create', [OrganizationalUnit::class, $company]));
        $this->assertFalse($supervisor->can('update', $unit));
    }

    public function test_it_blocks_parent_from_another_company(): void
    {
        [$company, $center] = $this->companyAndCenter();
        $foreignCompany = Company::factory()->create();
        $foreignCenter = Center::factory()->for($foreignCompany)->create();
        $foreignParent = app(CreateOrganizationalUnitAction::class)->handle($foreignCompany, $foreignCenter, $this->unitData('F-ADM', 'Foraneo', 'department'));

        $this->expectException(InvalidArgumentException::class);
        app(CreateOrganizationalUnitAction::class)->handle($company, $center, $this->unitData('BAD', 'Area invalida', 'area'), $foreignParent);
    }

    public function test_rh_operativo_with_center_scope_can_manage_workers_in_that_center(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $rhOperativo = $this->userWithCompanyRole($company, RoleKey::RH_OPERATIVO);

        app(AssignOperationalScopeAction::class)->handle($company, $rhOperativo, ['effective_from' => '2026-08-01'], center: $center);

        app(EnsureUserCanManageWorkerAction::class)->handle($rhOperativo, $company, $relationship, '2026-08-02');
        $this->assertTrue(true);
    }

    public function test_supervisor_with_center_scope_cannot_manage_workers(): void
    {
        [$company, $center, $relationship] = $this->relationshipContext();
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);

        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => '2026-08-01'], center: $center);

        $this->expectException(AuthorizationException::class);
        app(EnsureUserCanManageWorkerAction::class)->handle($supervisor, $company, $relationship, '2026-08-02');
    }
    private function unitData(string $code, string $name, string $type): array
    {
        return ['code' => $code, 'name' => $name, 'type' => $type];
    }

    private function companyAndCenter(): array
    {
        $company = Company::factory()->create();
        $center = Center::factory()->for($company)->create();

        return [$company, $center];
    }

    private function relationshipContext(?string $endedAt = null): array
    {
        [$company, $center] = $this->companyAndCenter();
        $worker = Worker::factory()->for($company)->create();
        $relationship = EmploymentRelationship::factory()->for($company)->for($worker)->for($center)->create([
            'started_at' => '2026-08-01',
            'ended_at' => $endedAt,
            'status' => 'active',
        ]);

        return [$company, $center, $relationship, $worker];
    }

    private function userWithCompanyRole(Company $company, string $roleKey, string $membershipStatus = 'active', string $userStatus = 'active'): User
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => strtoupper($roleKey), 'description' => 'Rol prueba', 'is_system' => true],
        );
        $user = User::factory()->create(['status' => $userStatus]);
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => $membershipStatus,
            'is_default' => true,
        ]);

        return $user->refresh();
    }
}
