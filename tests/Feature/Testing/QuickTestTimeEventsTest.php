<?php

namespace Tests\Feature\Testing;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class QuickTestTimeEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_manager_can_create_quick_test_events_and_refresh_work_day(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::RH_ADMIN);
        $center = Center::factory()->for($company)->create(['timezone' => 'America/Mexico_City']);
        $worker = Worker::factory()->for($company)->create(['status' => 'active']);
        EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'status' => 'active',
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('testing.quick-events')
            ->set('workDate', '2026-08-06')
            ->set("rows.{$worker->id}.source_mode", 'assisted')
            ->set("rows.{$worker->id}.clock_in", '08:00')
            ->set("rows.{$worker->id}.break1_start", '12:00')
            ->set("rows.{$worker->id}.break1_end", '12:30')
            ->set("rows.{$worker->id}.clock_out", '17:00')
            ->call('createEvents', $worker->id)
            ->assertSee('Eventos cargados');

        $this->assertSame(4, TimeEvent::query()->where('worker_id', $worker->id)->where('source', 'web')->count());
        $workDay = WorkDay::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->where('center_id', $center->id)
            ->whereDate('work_date', '2026-08-06')
            ->firstOrFail();

        $this->assertNotNull($workDay->active_calculation_id);
        $this->assertContains($workDay->status, [WorkDay::STATUS_CALCULATED, WorkDay::STATUS_WITH_ALERTS]);
    }

    public function test_supervisor_cannot_access_quick_test_events_tool(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::SUPERVISOR);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->get(route('testing.quick-events'))->assertForbidden();
    }

    public function test_company_manager_can_delete_provisional_operational_test_data(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::ADMIN_EMPRESA);
        $center = Center::factory()->for($company)->create();
        $worker = Worker::factory()->for($company)->create(['status' => 'active']);
        $relationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'status' => 'active',
        ]);
        $published = ScheduleBatch::factory()->create([
            'company_id' => $company->id,
            'center_id' => $center->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'status' => 'published',
            'version' => 1,
        ]);
        $draft = ScheduleBatch::factory()->create([
            'company_id' => $company->id,
            'center_id' => $center->id,
            'period_start' => '2026-08-08',
            'period_end' => '2026-08-14',
            'status' => 'draft',
            'version' => null,
        ]);
        \App\Models\DailyScheduleAssignment::factory()->create([
            'company_id' => $company->id,
            'schedule_batch_id' => $published->id,
            'employment_relationship_id' => $relationship->id,
            'work_date' => '2026-08-01',
            'day_type' => 'rest',
        ]);
        TimeEvent::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
        ]);
        WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'work_date' => '2026-08-01',
        ]);
        \App\Models\AttendancePeriod::factory()->create([
            'company_id' => $company->id,
            'center_id' => $center->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('testing.quick-events')
            ->call('deletePublishedSchedules')
            ->assertSee('Horarios publicados eliminados')
            ->call('deleteTimeEvents')
            ->assertSee('Eventos eliminados')
            ->call('deleteWorkDays')
            ->assertSee('Jornadas eliminadas')
            ->call('deleteAttendancePeriods')
            ->assertSee('Periodos eliminados');

        $this->assertDatabaseMissing('schedule_batches', ['id' => $published->id]);
        $this->assertDatabaseHas('schedule_batches', ['id' => $draft->id]);
        $this->assertSame(0, TimeEvent::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, WorkDay::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, \App\Models\AttendancePeriod::query()->where('company_id', $company->id)->count());
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $roleKey): array
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => ucfirst($roleKey), 'description' => null, 'is_system' => true],
        );
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $user = User::factory()->create(['status' => 'active']);

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return [$company, $user];
    }
}
