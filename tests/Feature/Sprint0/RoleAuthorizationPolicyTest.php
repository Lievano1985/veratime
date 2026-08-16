<?php

namespace Tests\Feature\Sprint0;

use App\Models\Center;
use App\Models\Company;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_admin_keeps_company_wide_operational_permissions(): void
    {
        [$user, $company] = $this->userWithCompanyRole(RoleKey::RH_ADMIN);

        $this->assertTrue($user->can('update', $company));
        $this->assertTrue($user->can('create', [Center::class, $company]));
        $this->assertTrue($user->can('create', [Worker::class, $company]));
        $this->assertTrue($user->can('create', [Schedule::class, $company]));
        $this->assertTrue($user->can('create', [ScheduleAssignment::class, $company]));
        $this->assertTrue($user->can('create', [TimeEvent::class, $company]));
    }

    public function test_legacy_roles_are_not_authorized_aliases(): void
    {
        foreach (['owner', 'admin', 'rh', 'hr'] as $legacyRole) {
            [$user, $company] = $this->userWithCompanyRole($legacyRole);

            $this->assertFalse($user->can('update', $company));
            $this->assertFalse($user->can('create', [Center::class, $company]));
            $this->assertFalse($user->can('create', [Worker::class, $company]));
            $this->assertFalse($user->can('create', [Schedule::class, $company]));
            $this->assertFalse($user->can('create', [ScheduleAssignment::class, $company]));
            $this->assertFalse($user->can('create', [TimeEvent::class, $company]));
        }
    }

    public function test_admin_empresa_keeps_company_wide_permissions(): void
    {
        [$user, $company] = $this->userWithCompanyRole(RoleKey::ADMIN_EMPRESA);

        $this->assertTrue($user->can('update', $company));
        $this->assertTrue($user->can('create', [Center::class, $company]));
        $this->assertTrue($user->can('create', [Worker::class, $company]));
        $this->assertTrue($user->can('create', [Schedule::class, $company]));
        $this->assertTrue($user->can('create', [ScheduleAssignment::class, $company]));
        $this->assertTrue($user->can('create', [TimeEvent::class, $company]));
    }

    public function test_super_admin_can_manage_tenant_companies(): void
    {
        [$user, $company] = $this->userWithCompanyRole(RoleKey::SUPER_ADMIN);

        $this->assertTrue($user->can('create', Company::class));
        $this->assertTrue($user->can('update', $company));
    }

    public function test_rh_operativo_and_supervisor_do_not_get_global_company_permissions(): void
    {
        foreach ([RoleKey::RH_OPERATIVO, RoleKey::SUPERVISOR] as $roleKey) {
            [$user, $company] = $this->userWithCompanyRole($roleKey);

            $this->assertFalse($user->can('update', $company));
            $this->assertFalse($user->can('create', [Center::class, $company]));
            $this->assertFalse($user->can('create', [Worker::class, $company]));
            $this->assertFalse($user->can('create', [Schedule::class, $company]));
            $this->assertFalse($user->can('create', [ScheduleAssignment::class, $company]));
            $this->assertFalse($user->can('create', [TimeEvent::class, $company]));
        }
    }

    public function test_other_company_inactive_company_and_inactive_membership_stay_blocked(): void
    {
        [$user, $company] = $this->userWithCompanyRole(RoleKey::RH_ADMIN);
        $otherCompany = Company::factory()->create();

        $this->assertTrue($user->can('create', [Worker::class, $company]));
        $this->assertFalse($user->can('create', [Worker::class, $otherCompany]));

        [$inactiveCompanyUser, $inactiveCompany] = $this->userWithCompanyRole(RoleKey::RH_ADMIN, companyStatus: 'inactive');
        $this->assertFalse($inactiveCompanyUser->can('create', [Worker::class, $inactiveCompany]));

        [$inactiveMembershipUser, $inactiveMembershipCompany] = $this->userWithCompanyRole(RoleKey::RH_ADMIN, membershipStatus: 'inactive');
        $this->assertFalse($inactiveMembershipUser->can('create', [Worker::class, $inactiveMembershipCompany]));
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function userWithCompanyRole(string $roleKey, string $companyStatus = 'active', string $membershipStatus = 'active'): array
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => strtoupper($roleKey), 'description' => 'Rol de prueba', 'is_system' => false],
        );
        $company = Company::factory()->create(['status' => $companyStatus]);
        $user = User::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => $membershipStatus,
            'is_default' => true,
        ]);

        return [$user->refresh(), $company->refresh()];
    }
}
