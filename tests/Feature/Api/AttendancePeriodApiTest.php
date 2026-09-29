<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\User;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

it('lists and shows attendance periods only from the token company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $period = AttendancePeriod::factory()->forCompany($company)->create(['status' => AttendancePeriod::STATUS_CLOSED]);
    $foreign = AttendancePeriod::factory()->create();
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'periods', ['work-days:read'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/time/attendance-periods?status=closed')->assertOk()->assertJsonPath('data.0.id', (string) $period->id);
    $this->withToken($token)->getJson('/api/v1/time/attendance-periods/'.$period->id)->assertOk()->assertJsonPath('data.id', (string) $period->id);
    $this->withToken($token)->getJson('/api/v1/time/attendance-periods/'.$foreign->id)->assertNotFound();
});

it('exports only closed attendance periods from the token company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $closedPeriod = AttendancePeriod::factory()->forCompany($company)->create(['status' => AttendancePeriod::STATUS_CLOSED]);
    $openPeriod = AttendancePeriod::factory()->forCompany($company)->create(['status' => AttendancePeriod::STATUS_OPEN]);
    $foreignPeriod = AttendancePeriod::factory()->create(['status' => AttendancePeriod::STATUS_CLOSED]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'period-export', ['exports:read'])->plainTextToken;

    $response = $this->withToken($token)->get('/api/v1/time/attendance-periods/'.$closedPeriod->id.'/payroll-csv');

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('empresa,centro,unidad');
    $this->withToken($token)
        ->get('/api/v1/time/attendance-periods/'.$closedPeriod->id.'/payroll-xlsx')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->withToken($token)->getJson('/api/v1/time/attendance-periods/'.$openPeriod->id.'/payroll-csv')->assertForbidden();
    $this->withToken($token)->getJson('/api/v1/time/attendance-periods/'.$openPeriod->id.'/payroll-xlsx')->assertForbidden();
    $this->withToken($token)->getJson('/api/v1/time/attendance-periods/'.$foreignPeriod->id.'/payroll-csv')->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/time/attendance-periods/'.$foreignPeriod->id.'/payroll-xlsx')->assertNotFound();
});
