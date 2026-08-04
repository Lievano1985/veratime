<?php

namespace Tests\Feature\WorkDays;

use App\Models\Center;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkDayOperationalListTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_work_days_list(): void
    {
        [$company, $user, $workDay] = $this->companyUserAndWorkDay(RoleKey::ADMIN);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index'))
            ->assertOk()
            ->assertSee('Jornadas')
            ->assertSee($workDay->worker->employee_code)
            ->assertSee('Programada');
    }

    public function test_work_days_list_filters_by_company_and_search(): void
    {
        [$company, $user, $workDay] = $this->companyUserAndWorkDay(RoleKey::RH, 'ANA', 'Ana Demo Lopez');
        [$otherCompany] = $this->companyUserAndWorkDay(RoleKey::RH, 'BRU', 'Bruno Demo Perez');

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index', ['search' => 'ANA']))
            ->assertOk()
            ->assertSee($workDay->worker->full_name)
            ->assertDontSee('Bruno Demo Perez');

        $this->assertSame(1, WorkDay::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, WorkDay::query()->where('company_id', $otherCompany->id)->count());
    }

    public function test_supervisor_without_manager_permission_cannot_view_work_days_list(): void
    {
        [$company, $user] = $this->companyUserAndWorkDay(RoleKey::SUPERVISOR);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('work-days.index'))
            ->assertForbidden();
    }

    private function companyUserAndWorkDay(string $roleKey, string $code = 'VT-001', string $name = 'Ana Demo Lopez'): array
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => ucfirst($roleKey), 'description' => null, 'is_system' => true],
        );
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $user = User::factory()->create();
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);
        $center = Center::factory()->create(['company_id' => $company->id]);
        $worker = Worker::factory()->create([
            'company_id' => $company->id,
            'employee_code' => $code,
            'full_name' => $name,
        ]);
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'work_date' => '2026-08-03',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
        ]);

        return [$company, $user, $workDay->refresh()];
    }
}
