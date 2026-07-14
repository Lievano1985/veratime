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

    public function test_rh_keeps_company_wide_operational_permissions(): void
    {
        [$user, $company] = $this->userWithCompanyRole(RoleKey::RH);

        $this->assertTrue($user->can('update', $company));
        $this->assertTrue($user->can('create', [Center::class, $company]));
        $this->assertTrue($user->can('create', [Worker::class, $company]));
        $this->assertTrue($user->can('create', [Schedule::class, $company]));
        $this->assertTrue($user->can('create', [ScheduleAssignment::class, $company]));
        $this->assertTrue($user->can('create', [TimeEvent::class, $company]));
    }

    public function test_hr_is_not_an_authorized_role_alias(): void
    {
        [$user, $company] = $this->userWithCompanyRole('hr');

        $this->assertFalse($user->can('update', $company));
        $this->assertFalse($user->can('create', [Center::class, $company]));
        $this->assertFalse($user->can('create', [Worker::class, $company]));
        $this->assertFalse($user->can('create', [Schedule::class, $company]));
        $this->assertFalse($user->can('create', [ScheduleAssignment::class, $company]));
        $this->assertFalse($user->can('create', [TimeEvent::class, $company]));
    }

    public function test_owner_and_admin_keep_company_wide_permissions(): void
    {
        foreach ([RoleKey::OWNER, RoleKey::ADMIN] as $roleKey) {
            [$user, $company] = $this->userWithCompanyRole($roleKey);

            $this->assertTrue($user->can('update', $company));
            $this->assertTrue($user->can('create', [Center::class, $company]));
            $this->assertTrue($user->can('create', [Worker::class, $company]));
            $this->assertTrue($user->can('create', [Schedule::class, $company]));
            $this->assertTrue($user->can('create', [ScheduleAssignment::class, $company]));
            $this->assertTrue($user->can('create', [TimeEvent::class, $company]));
        }
    }

    public function test_supervisor_does_not_get_global_company_permissions(): void
    {
        [$user, $company] = $this->userWithCompanyRole(RoleKey::SUPERVISOR);

        $this->assertFalse($user->can('update', $company));
        $this->assertFalse($user->can('create', [Center::class, $company]));
        $this->assertFalse($user->can('create', [Worker::class, $company]));
        $this->assertFalse($user->can('create', [Schedule::class, $company]));
        $this->assertFalse($user->can('create', [ScheduleAssignment::class, $company]));
        $this->assertFalse($user->can('create', [TimeEvent::class, $company]));
    }

    public function test_other_company_inactive_company_and_inactive_membership_stay_blocked(): void
    {
        [$user, $company] = $this->userWithCompanyRole(RoleKey::RH);
        $otherCompany = Company::factory()->create();

        $this->assertTrue($user->can('create', [Worker::class, $company]));
        $this->assertFalse($user->can('create', [Worker::class, $otherCompany]));

        [$inactiveCompanyUser, $inactiveCompany] = $this->userWithCompanyRole(RoleKey::RH, companyStatus: 'inactive');
        $this->assertFalse($inactiveCompanyUser->can('create', [Worker::class, $inactiveCompany]));

        [$inactiveMembershipUser, $inactiveMembershipCompany] = $this->userWithCompanyRole(RoleKey::RH, membershipStatus: 'inactive');
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