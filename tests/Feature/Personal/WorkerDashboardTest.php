<?php

use App\Domains\Workers\Actions\BuildPersonalDashboardAction;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Database\Seeders\AlertTypeSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 10:00:00', 'America/Mexico_City'));
});
afterEach(function () {
    CarbonImmutable::setTestNow();
});

function dashboardContext(): array
{
    $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
    $role = Role::firstOrCreate(['key' => RoleKey::TRABAJADOR], ['name' => 'Trabajador']);
    $user = User::factory()->create(['status' => 'active']);
    $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
    $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id, 'center_id' => $center->id, 'started_at' => '2026-10-01', 'status' => 'active']);
    UserWorkerLink::create(['company_id' => $company->id, 'worker_id' => $worker->id, 'user_id' => $user->id, 'status' => 'active']);

    return [$company, $user, $worker, $center, $relationship];
}

it('shows a personal dashboard with safe empty states and blocks unlinked accounts', function () {
    [$company,$user] = dashboardContext();
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    $this->get(route('personal.dashboard'))->assertOk()->assertSee('Mi día de hoy')
        ->assertSee('Sin horario publicado para hoy')->assertSee('Horas calculadas')->assertSee('Provisionales')
        ->assertSee('Mis marcajes recientes')->assertSee('Mis avisos pendientes');
    UserWorkerLink::query()->where('user_id', $user->id)->update(['status' => 'revoked']);
    $this->get(route('personal.dashboard'))->assertForbidden();
});

it('counts only published personal schedules and active calculations through today', function () {
    [$company,$user,$worker,$center,$relationship] = dashboardContext();
    foreach (['published' => 480, 'draft' => 999] as $status => $minutes) {
        $batch = ScheduleBatch::factory()->create(['company_id' => $company->id, 'center_id' => $center->id, 'status' => $status]);
        DailyScheduleAssignment::factory()->create(['company_id' => $company->id, 'schedule_batch_id' => $batch->id, 'employment_relationship_id' => $relationship->id, 'work_date' => '2026-11-02', 'day_type' => 'shift', 'required_minutes' => $minutes]);
    }
    foreach (['2026-11-02' => 360, '2026-11-03' => 900, '2026-10-26' => 800] as $date => $minutes) {
        $day = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id, 'center_id' => $center->id, 'employment_relationship_id' => $relationship->id, 'work_date' => $date, 'status' => 'calculated']);
        $calculation = WorkDayCalculation::factory()->create(['company_id' => $company->id, 'work_day_id' => $day->id, 'total_work_minutes' => $minutes]);
        $day->update(['active_calculation_id' => $calculation->id]);
    }
    $summary = app(BuildPersonalDashboardAction::class)->handle($user, $company);
    expect($summary['plannedMinutes'])->toBe(480)->and($summary['calculatedMinutes'])->toBe(360)
        ->and($summary['pendingDays'])->toBe(0);
});

it('does not expose another worker events or alerts and hides voided events', function () {
    [$company,$user,$worker] = dashboardContext();
    $other = Worker::factory()->create(['company_id' => $company->id]);
    $this->seed(AlertTypeSeeder::class);
    $type = AlertType::query()->firstOrFail();
    foreach ([$worker->id, $other->id] as $id) {
        TimeEvent::factory()->create(['company_id' => $company->id, 'worker_id' => $id, 'status' => 'valid', 'event_type' => 'clock_in', 'occurred_local_date' => '2026-11-02', 'occurred_at_utc' => '2026-11-02 14:00:00', 'timezone' => 'America/Mexico_City']);
        Alert::create(['company_id' => $company->id, 'worker_id' => $id, 'alert_type_id' => $type->id,
            'rule_code' => $type->code, 'title' => 'Aviso '.$id, 'description' => 'Revisión personal',
            'severity' => 'warning', 'status' => 'new', 'detected_at' => now(), 'fingerprint' => 'worker-'.$id]);
    }
    TimeEvent::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id, 'status' => 'voided', 'occurred_local_date' => '2026-11-02']);
    $summary = app(BuildPersonalDashboardAction::class)->handle($user, $company);
    expect($summary['events'])->toHaveCount(1)->and($summary['events']->first()->worker_id)->toBe($worker->id)
        ->and($summary['todayEvent']->worker_id)->toBe($worker->id)
        ->and($summary['alerts'])->toHaveCount(1)->and($summary['alerts']->first()->worker_id)->toBe($worker->id);
});
