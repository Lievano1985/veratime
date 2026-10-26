<?php

use App\Domains\Attendance\Actions\CloseAttendancePeriodAction;
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
    $closedPeriod = AttendancePeriod::factory()->forCompany($company)->create(['status' => AttendancePeriod::STATUS_OPEN]);
    $openPeriod = AttendancePeriod::factory()->forCompany($company)->create(['status' => AttendancePeriod::STATUS_OPEN]);
    $foreignPeriod = AttendancePeriod::factory()->create(['status' => AttendancePeriod::STATUS_CLOSED]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'period-export', ['exports:read'])->plainTextToken;
    $closedPeriod = app(CloseAttendancePeriodAction::class)->handle($company, $closedPeriod, $user);

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

it('rejects legacy downloads through the API without producing current-data files', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $period = AttendancePeriod::factory()->forCompany($company)->create(['status' => AttendancePeriod::STATUS_CLOSED,
        'snapshot_schema_version' => 'attendance_period.v1']);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'legacy-export', ['exports:read'])->plainTextToken;
    foreach (['csv', 'xlsx'] as $format) {
        $this->withToken($token)->getJson('/api/v1/time/attendance-periods/'.$period->id.'/payroll-'.$format)
            ->assertUnprocessable()->assertJsonValidationErrors('payroll_export');
    }
});
