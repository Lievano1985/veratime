<?php

use App\Domains\Companies\Actions\SendCompanyTrialRemindersAction;
use App\Domains\Companies\Support\CompanyTrialStatus;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $time = CarbonImmutable::parse('2026-11-01 12:00:00', 'UTC');
    Carbon::setTestNow($time);
    CarbonImmutable::setTestNow($time);
    config(['services.brevo.enabled' => true, 'services.brevo.api_key' => 'test-only-key',
        'services.brevo.sender.address' => 'support@example.test', 'services.brevo.sender.name' => 'Vera Time',
        'services.brevo.endpoint' => 'https://mail.example.test/send']);
    Http::preventStrayRequests();
    Http::fake(['https://mail.example.test/send' => Http::response(['messageId' => 'test'], 201)]);
});
afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function trialNoticeContext(int $days = 2): array
{
    $company = Company::factory()->create(['is_trial' => true, 'trial_ends_at' => now()->addDays($days), 'status' => 'active', 'timezone' => 'America/Mexico_City']);
    $role = Role::firstOrCreate(['key' => RoleKey::ADMIN_EMPRESA], ['name' => 'Administrador de empresa']);
    $user = User::factory()->create(['status' => 'active']);
    $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

    return [$company, $user];
}

it('shows remaining days and the local expiry to administrators and hides it from workers', function () {
    [$company,$user] = trialNoticeContext(15);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id])
        ->get(route('settings.profile'))->assertOk()->assertSee('Periodo de prueba: quedan 15 días')
        ->assertSee('16/11/2026 06:00');
    $company->update(['trial_ends_at' => now()->addHours(25)]);
    expect(CompanyTrialStatus::forCompany($company)['days'])->toBe(2)
        ->and(CompanyTrialStatus::forCompany($company)['urgent'])->toBeTrue();
    $role = Role::firstOrCreate(['key' => RoleKey::TRABAJADOR], ['name' => 'Trabajador']);
    $user->companies()->updateExistingPivot($company->id, ['role_id' => $role->id]);
    $this->get(route('settings.profile'))->assertOk()->assertDontSee('Periodo de prueba: quedan');
});

it('sends once per administrator and expiry without changing trial dates or suspending the company', function () {
    [$company,$user] = trialNoticeContext();
    $end = $company->trial_ends_at->toIso8601String();
    $action = app(SendCompanyTrialRemindersAction::class);
    expect($action->handle()['sent'])->toBe(1)->and($action->handle()['sent'])->toBe(0);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['to'][0]['email'] === $user->email && str_contains($request['textContent'], $company->name));
    expect($company->fresh()->status)->toBe('active')->and($company->fresh()->trial_ends_at->toIso8601String())->toBe($end);
});

it('does not mark failed sends as delivered and retries them on the next run', function () {
    [$company] = trialNoticeContext();
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake(['https://mail.example.test/send' => Http::sequence()->push([], 503)->push(['messageId' => 'retry'], 201)]);
    $action = app(SendCompanyTrialRemindersAction::class);
    expect($action->handle()['failed'])->toBe(1)->and($action->handle()['sent'])->toBe(1)
        ->and($action->handle()['sent'])->toBe(0);
    Http::assertSentCount(2);
});

it('skips expired and non-trial companies and does not send when mail is disabled', function () {
    [$company] = trialNoticeContext(5);
    $action = app(SendCompanyTrialRemindersAction::class);
    expect($action->handle()['sent'])->toBe(0);
    $company->update(['trial_ends_at' => now()->subMinute()]);
    expect($action->handle()['sent'])->toBe(0);
    $company->update(['trial_ends_at' => now()->addDay(), 'is_trial' => false]);
    expect($action->handle()['sent'])->toBe(0);
    $company->update(['is_trial' => true]);
    config(['services.brevo.enabled' => false]);
    expect($action->handle()['enabled'])->toBeFalse();
    Http::assertNothingSent();
});
