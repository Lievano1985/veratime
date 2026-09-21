<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Center;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

function alertApiFixture(Company $company): array
{
    $center = Center::factory()->create(['company_id' => $company->id]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);
    $workDay = WorkDay::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $center->id,
    ]);

    return [$worker, $workDay];
}

it('lists only alerts belonging to the token company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    [$worker, $workDay] = alertApiFixture($company);
    [$otherWorker, $otherWorkDay] = alertApiFixture(Company::factory()->create());
    $type = AlertType::query()->create([
        'code' => 'api_alert_test',
        'name' => 'Alerta API de prueba',
        'default_severity' => AlertType::SEVERITY_WARNING,
        'category' => 'attendance',
        'status' => AlertType::STATUS_ACTIVE,
    ]);
    $visible = Alert::query()->create([
        'company_id' => $company->id,
        'alert_type_id' => $type->id,
        'worker_id' => $worker->id,
        'work_day_id' => $workDay->id,
        'severity' => AlertType::SEVERITY_WARNING,
        'status' => Alert::STATUS_NEW,
        'title' => 'Situacion pendiente de revision',
        'detected_at' => now('UTC'),
        'fingerprint' => 'api-alert-visible',
    ]);
    $hidden = Alert::query()->create([
        'company_id' => $otherWorkDay->company_id,
        'alert_type_id' => $type->id,
        'worker_id' => $otherWorker->id,
        'work_day_id' => $otherWorkDay->id,
        'severity' => AlertType::SEVERITY_WARNING,
        'status' => Alert::STATUS_NEW,
        'title' => 'Alerta de otra empresa',
        'detected_at' => now('UTC'),
        'fingerprint' => 'api-alert-hidden',
    ]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)
        ->handle($user, $company, 'alerts-api-test', ['alerts:read'])
        ->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/time/alerts?worker_id='.$worker->id.'&severity=warning')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $visible->id)
        ->assertJsonMissing(['id' => (string) $hidden->id])
        ->assertJsonStructure(['meta' => ['current_page', 'per_page', 'total', 'trace_id'], 'links']);

    $this->withToken($token)->getJson('/api/v1/time/alerts/'.$visible->id)
        ->assertOk()
        ->assertJsonPath('data.id', (string) $visible->id);

    $this->withToken($token)->getJson('/api/v1/time/alerts/'.$hidden->id)->assertNotFound();
});
