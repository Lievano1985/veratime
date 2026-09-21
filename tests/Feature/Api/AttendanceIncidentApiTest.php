<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\AttendanceIncident;
use App\Models\Center;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

it('creates, lists and cancels attendance incidents within the token company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    EmploymentRelationship::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id, 'center_id' => $center->id, 'status' => 'active']);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'incidents', ['incidents:read', 'incidents:write'])->plainTextToken;
    $response = $this->withToken($token)->postJson('/api/v1/time/attendance-incidents', ['worker_id' => $worker->id, 'start_date' => '2026-09-15', 'end_date' => '2026-09-16', 'incident_type' => AttendanceIncident::TYPE_VACATION, 'payment_status' => AttendanceIncident::PAYMENT_PAID, 'reference' => 'VAC-01']);
    $response->assertCreated()->assertJsonPath('data.worker.id', (string) $worker->id)->assertJsonPath('data.status', 'approved');
    $id = $response->json('data.id');
    $this->withToken($token)->getJson('/api/v1/time/attendance-incidents?worker_id='.$worker->id)->assertOk()->assertJsonPath('data.0.id', $id);
    $this->withToken($token)->getJson('/api/v1/time/attendance-incidents/'.$id)->assertOk()->assertJsonPath('data.id', $id);
    $this->withToken($token)->postJson('/api/v1/time/attendance-incidents/'.$id.'/cancel', ['reason' => 'Vacaciones capturadas por error.'])->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('does not show an attendance incident from another company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $foreignIncident = AttendanceIncident::factory()->create();
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'incidents-read', ['incidents:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/time/attendance-incidents/'.$foreignIncident->id)->assertNotFound();
});
