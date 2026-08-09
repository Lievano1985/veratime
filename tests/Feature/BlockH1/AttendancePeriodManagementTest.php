<?php

namespace Tests\Feature\BlockH1;

use App\Domains\Attendance\Actions\CreateAttendancePeriodAction;
use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AttendancePeriodManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_managers_can_create_attendance_period_for_full_center(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::RH);
        $center = Center::factory()->for($company)->create(['name' => 'Operativos']);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('attendance-periods.index')
            ->call('openCreatePanel')
            ->set('form.center_id', (string) $center->id)
            ->set('form.scope_mode', 'center')
            ->set('form.period_start', '2026-08-01')
            ->set('form.period_end', '2026-08-15')
            ->set('form.name', 'Primera quincena operativos')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSee('Periodo de asistencia generado.');

        $period = AttendancePeriod::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame($center->id, $period->center_id);
        $this->assertSame(AttendancePeriod::SCOPE_CENTER, $period->scope_type);
        $this->assertSame('2026-08-01', $period->period_start->toDateString());
        $this->assertSame('2026-08-15', $period->period_end->toDateString());
        $this->assertSame(AttendancePeriod::STATUS_OPEN, $period->status);
    }

    public function test_managers_can_create_attendance_period_for_multiple_units(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::ADMIN);
        $center = Center::factory()->for($company)->create();
        $unitA = OrganizationalUnit::factory()->forCenter($center)->create(['name' => 'Albaniles']);
        $unitB = OrganizationalUnit::factory()->forCenter($center)->create(['name' => 'Vigilancia']);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('attendance-periods.index')
            ->call('openCreatePanel')
            ->set('form.center_id', (string) $center->id)
            ->set('form.scope_mode', 'units')
            ->set('form.organizational_unit_ids', [$unitA->id, $unitB->id])
            ->set('form.period_start', '2026-08-01')
            ->set('form.period_end', '2026-08-07')
            ->call('create')
            ->assertHasNoErrors();

        $period = AttendancePeriod::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame(AttendancePeriod::SCOPE_ORGANIZATIONAL_UNITS, $period->scope_type);
        $this->assertDatabaseHas('attendance_period_scopes', [
            'attendance_period_id' => $period->id,
            'organizational_unit_id' => $unitA->id,
        ]);
        $this->assertDatabaseHas('attendance_period_scopes', [
            'attendance_period_id' => $period->id,
            'organizational_unit_id' => $unitB->id,
        ]);
    }

    public function test_supervisor_and_foreign_company_are_blocked(): void
    {
        [$company, $supervisor] = $this->companyUser(RoleKey::SUPERVISOR);
        [$otherCompany] = $this->companyUser(RoleKey::RH);
        $center = Center::factory()->for($company)->create();

        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id]);

        $this->get(route('attendance-periods.index'))->assertForbidden();
        $this->assertFalse($supervisor->can('create', [AttendancePeriod::class, $company]));
        $this->assertFalse($supervisor->can('viewAny', [AttendancePeriod::class, $otherCompany]));
    }

    public function test_it_blocks_units_from_another_center_or_company(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::OWNER);
        $center = Center::factory()->for($company)->create();
        $otherCenter = Center::factory()->for($company)->create();
        $foreignUnit = OrganizationalUnit::factory()->forCenter($otherCenter)->create();

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('attendance-periods.index')
            ->call('openCreatePanel')
            ->set('form.center_id', (string) $center->id)
            ->set('form.scope_mode', 'units')
            ->set('form.organizational_unit_ids', [$foreignUnit->id])
            ->set('form.period_start', '2026-08-01')
            ->set('form.period_end', '2026-08-07')
            ->call('create')
            ->assertHasErrors(['form.period_start']);
    }

    public function test_it_blocks_overlapping_periods_for_same_scope(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::RH);
        $center = Center::factory()->for($company)->create();

        app(CreateAttendancePeriodAction::class)->handle($company, $center, [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
        ], [], $user);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('attendance-periods.index')
            ->call('openCreatePanel')
            ->set('form.center_id', (string) $center->id)
            ->set('form.scope_mode', 'center')
            ->set('form.period_start', '2026-08-10')
            ->set('form.period_end', '2026-08-20')
            ->call('create')
            ->assertHasErrors(['form.period_start']);
    }

    public function test_open_period_can_be_cancelled_with_reason(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::ADMIN);
        $center = Center::factory()->for($company)->create();
        $period = app(CreateAttendancePeriodAction::class)->handle($company, $center, [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
        ], [], $user);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('attendance-periods.index')
            ->call('openCancelPanel', $period->id)
            ->set('cancelForm.reason', 'Periodo creado por error')
            ->call('cancel')
            ->assertHasNoErrors()
            ->assertSee('Periodo cancelado.');

        $this->assertDatabaseHas('attendance_periods', [
            'id' => $period->id,
            'status' => AttendancePeriod::STATUS_CANCELLED,
            'cancellation_reason' => 'Periodo creado por error',
        ]);
    }

    public function test_h1_does_not_create_payroll_or_export_tables(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('payroll_periods'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('payroll_exports'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('period_closure_reports'));
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $roleKey): array
    {
        $role = Role::factory()->create(['key' => $roleKey]);
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return [$company, $user];
    }
}
