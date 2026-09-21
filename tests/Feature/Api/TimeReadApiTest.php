<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
    $this->seed(RoleSeeder::class);
});

function timeReadApiToken(User $user, Company $company): string
{
    return app(IssueCompanyApiTokenAction::class)
        ->handle($user, $company, 'time-read-api-test', ['time-events:read', 'work-days:read'])
        ->plainTextToken;
}

function timeReadFixtures(Company $company): array
{
    $center = Center::factory()->create(['company_id' => $company->id]);
    $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);

    return [$worker, $center, $relationship];
}

it('lists only time events from the token company with supported filters', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    [$worker, $center, $relationship] = timeReadFixtures($company);
    [$otherWorker, $otherCenter, $otherRelationship] = timeReadFixtures(Company::factory()->create());
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $visible = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $center->id,
        'event_type' => 'clock_in',
        'source' => 'api',
        'status' => 'valid',
        'occurred_local_date' => '2026-09-17',
    ]);
    $hidden = TimeEvent::factory()->create([
        'company_id' => $otherWorker->company_id,
        'worker_id' => $otherWorker->id,
        'employment_relationship_id' => $otherRelationship->id,
        'center_id' => $otherCenter->id,
    ]);

    $this->withToken(timeReadApiToken($user, $company))
        ->getJson('/api/v1/time/time-events?employee_code='.$worker->employee_code.'&date_from=2026-09-17&date_to=2026-09-17')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $visible->id)
        ->assertJsonMissing(['id' => (string) $hidden->id])
        ->assertJsonStructure(['meta' => ['current_page', 'per_page', 'total', 'trace_id'], 'links']);
});

it('lists calculated work days only from the token company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    [$worker, $center, $relationship] = timeReadFixtures($company);
    $workDay = WorkDay::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $center->id,
        'work_date' => '2026-09-16',
        'status' => WorkDay::STATUS_CALCULATED,
    ]);
    $calculation = WorkDayCalculation::factory()->create([
        'company_id' => $company->id,
        'work_day_id' => $workDay->id,
        'total_work_minutes' => 480,
    ]);
    $workDay->update(['active_calculation_id' => $calculation->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);

    $this->withToken(timeReadApiToken($user, $company))
        ->getJson('/api/v1/time/work-days?worker_id='.$worker->id.'&date_from=2026-09-16&date_to=2026-09-16')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $workDay->id)
        ->assertJsonPath('data.0.active_calculation.total_work_minutes', 480)
        ->assertJsonPath('data.0.worker.employee_code', $worker->employee_code)
        ->assertJsonStructure(['meta' => ['trace_id'], 'links']);
});

it('does not expose operational API data to a worker role token', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['status' => 'active']);
    $role = Role::query()->where('key', RoleKey::TRABAJADOR)->firstOrFail();
    $company->users()->attach($user, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
    $token = app(IssueCompanyApiTokenAction::class)
        ->handle($user, $company, 'worker-personal', ['time-events:read'])
        ->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/time/time-events')
        ->assertForbidden();
});

it('returns administrative details only from the token company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    [$worker, $center, $relationship] = timeReadFixtures($company);
    $event = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $center->id,
    ]);
    $workDay = WorkDay::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $center->id,
    ]);
    $foreignEvent = TimeEvent::factory()->create();
    $foreignWorkDay = WorkDay::factory()->create();
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = timeReadApiToken($user, $company);

    $this->withToken($token)->getJson('/api/v1/time/time-events/'.$event->id)
        ->assertOk()->assertJsonPath('data.id', (string) $event->id);
    $this->withToken($token)->getJson('/api/v1/time/work-days/'.$workDay->id)
        ->assertOk()->assertJsonPath('data.id', (string) $workDay->id);
    $this->withToken($token)->getJson('/api/v1/time/time-events/'.$foreignEvent->id)->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/time/work-days/'.$foreignWorkDay->id)->assertNotFound();
});
