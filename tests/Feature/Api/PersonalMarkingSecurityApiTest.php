<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Domains\Workers\Actions\CreateMobileDeviceBindingAuthorizationAction;
use App\Domains\Workers\Actions\RevokeMobileDeviceBindingAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\MobileDeviceBinding;
use App\Models\MobileDeviceBindingAuthorization;
use App\Models\MobileMarkingPolicy;
use App\Models\MobileMarkingTimeReference;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

it('returns a non-enforcing security policy when the company has no active policy', function (): void {
    [$company, $user, $worker, $token] = personalSecurityContext();

    $response = $this->withToken($token)->getJson('/api/v1/time/me/marking-security')
        ->assertOk()
        ->assertJsonPath('data.security_version', 1)
        ->assertJsonPath('data.binding', null)
        ->assertJsonPath('data.policy', null)
        ->assertJsonStructure(['data' => ['time_reference' => ['id', 'server_time', 'expires_at']], 'meta' => ['trace_id']]);

    $reference = MobileMarkingTimeReference::query()->sole();
    expect($reference->company_id)->toBe($company->id)
        ->and($reference->user_id)->toBe($user->id)
        ->and($reference->worker_id)->toBe($worker->id)
        ->and($reference->mobile_marking_policy_id)->toBeNull()
        ->and($reference->expires_at->greaterThan($reference->issued_at))->toBeTrue();

    $this->withToken($token)->getJson('/api/v1/time/me/marking-security')
        ->assertOk()
        ->assertJsonPath('data.time_reference.id', $response->json('data.time_reference.id'));
    $this->assertDatabaseCount('mobile_marking_time_references', 1);
});

it('returns only the active policy of the token company', function (): void {
    [$company, $user, $worker, $token] = personalSecurityContext();
    $policy = MobileMarkingPolicy::factory()->for($company)->active()->create([
        'version' => 3,
        'mode' => MobileMarkingPolicy::MODE_CIRCLE,
        'requires_device_binding' => true,
        'requires_biometric_unlock' => true,
        'center_latitude' => 19.4326080,
        'center_longitude' => -99.1332090,
        'radius_meters' => 150,
        'max_accuracy_meters' => 25,
        'max_location_age_seconds' => 45,
        'offline_valid_until' => now()->addHours(6),
    ]);

    $otherCompany = Company::factory()->create();
    MobileMarkingPolicy::factory()->for($otherCompany)->active()->create([
        'mode' => MobileMarkingPolicy::MODE_CIRCLE,
        'center_latitude' => 20.0000000,
        'center_longitude' => -100.0000000,
        'radius_meters' => 999,
    ]);

    $this->withToken($token)->getJson('/api/v1/time/me/marking-security')
        ->assertOk()
        ->assertJsonPath('data.binding', null)
        ->assertJsonPath('data.policy.id', $policy->public_id)
        ->assertJsonPath('data.policy.version', 3)
        ->assertJsonPath('data.policy.mode', 'circle')
        ->assertJsonPath('data.policy.device_binding_required', true)
        ->assertJsonPath('data.policy.center.latitude', 19.432608)
        ->assertJsonPath('data.policy.center.longitude', -99.133209)
        ->assertJsonPath('data.policy.radius_meters', 150)
        ->assertJsonPath('data.policy.max_accuracy_meters', 25)
        ->assertJsonPath('data.policy.max_location_age_seconds', 45)
        ->assertJsonMissing(['radius_meters' => 999]);

    $this->assertDatabaseHas('mobile_marking_time_references', [
        'company_id' => $company->id,
        'user_id' => $user->id,
        'worker_id' => $worker->id,
        'mobile_marking_policy_id' => $policy->id,
        'policy_version' => 3,
    ]);
});

it('does not expose a draft, inactive, or expired policy as effective', function (array $attributes): void {
    [$company, $user, $worker, $token] = personalSecurityContext();
    MobileMarkingPolicy::factory()->for($company)->create($attributes);

    $this->withToken($token)->getJson('/api/v1/time/me/marking-security')
        ->assertOk()
        ->assertJsonPath('data.policy', null);
})->with([
    'draft' => [['status' => MobileMarkingPolicy::STATUS_DRAFT]],
    'inactive' => [['status' => MobileMarkingPolicy::STATUS_INACTIVE]],
    'not started' => [['status' => MobileMarkingPolicy::STATUS_ACTIVE, 'valid_from' => '2099-01-01 00:00:00']],
    'expired' => [['status' => MobileMarkingPolicy::STATUS_ACTIVE, 'valid_until' => '2000-01-01 00:00:00']],
]);

it('prefers the active policy for the worker center over the general company policy', function (): void {
    [$company, $user, $worker, $token] = personalSecurityContext();
    $center = Center::factory()->for($company)->create();
    EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
        'started_at' => now()->subDay()->toDateString(),
    ]);
    $generalPolicy = MobileMarkingPolicy::factory()->for($company)->active()->create([
        'version' => 8,
        'mode' => MobileMarkingPolicy::MODE_FREE,
    ]);
    $centerPolicy = MobileMarkingPolicy::factory()->for($company)->active()->create([
        'center_id' => $center->id,
        'version' => 1,
        'mode' => MobileMarkingPolicy::MODE_CIRCLE,
        'center_latitude' => 19.4000000,
        'center_longitude' => -99.1000000,
        'radius_meters' => 100,
    ]);

    $this->withToken($token)->getJson('/api/v1/time/me/marking-security')
        ->assertOk()
        ->assertJsonPath('data.policy.id', $centerPolicy->public_id)
        ->assertJsonPath('data.policy.center.latitude', 19.4)
        ->assertJsonMissing(['id' => $generalPolicy->public_id]);
});

it('prefers the active policy for the workers organizational unit over the center policy', function (): void {
    [$company, $user, $worker, $token] = personalSecurityContext();
    $center = Center::factory()->for($company)->create();
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
        'started_at' => now()->subDay()->toDateString(),
    ]);
    $unit = OrganizationalUnit::factory()->forCenter($center)->create();
    EmploymentUnitAssignment::query()->forceCreate([
        'company_id' => $company->id,
        'employment_relationship_id' => $relationship->id,
        'organizational_unit_id' => $unit->id,
        'assignment_type' => 'primary',
        'status' => 'active',
        'effective_from' => now()->toDateString(),
        'source' => 'manual',
        'metadata' => [],
    ]);
    $centerPolicy = MobileMarkingPolicy::factory()->for($company)->active()->create([
        'center_id' => $center->id,
        'version' => 7,
        'mode' => MobileMarkingPolicy::MODE_CIRCLE,
        'center_latitude' => 19.4000000,
        'center_longitude' => -99.1000000,
        'radius_meters' => 100,
    ]);
    $unitPolicy = MobileMarkingPolicy::factory()->for($company)->active()->create([
        'organizational_unit_id' => $unit->id,
        'version' => 1,
        'mode' => MobileMarkingPolicy::MODE_CIRCLE,
        'center_latitude' => 19.4010000,
        'center_longitude' => -99.1010000,
        'radius_meters' => 25,
    ]);

    $this->withToken($token)->getJson('/api/v1/time/me/marking-security')
        ->assertOk()
        ->assertJsonPath('data.policy.id', $unitPolicy->public_id)
        ->assertJsonPath('data.policy.radius_meters', 25)
        ->assertJsonMissing(['id' => $centerPolicy->public_id]);
});

it('does not allow a personal client to override its security context', function (): void {
    [$company, $user, $worker, $token] = personalSecurityContext();

    $this->withToken($token)->getJson('/api/v1/time/me/marking-security?company_id=999&worker_id=998&policy_id=997')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id', 'worker_id', 'policy_id']);
});

it('requires a personal bearer token', function (): void {
    $this->getJson('/api/v1/time/me/marking-security')->assertUnauthorized();
});

it('issues one supervised mobile binding authorization at a time for a linked worker', function (): void {
    [$company, $user, $worker] = personalSecurityContext();

    $first = app(CreateMobileDeviceBindingAuthorizationAction::class)->handle($company, $user, $user, $worker);
    $second = app(CreateMobileDeviceBindingAuthorizationAction::class)->handle($company, $user, $user, $worker);

    expect($first['authorization_code'])->toHaveLength(24)
        ->and($first['authorization']->authorization_secret_hash)->toBe(hash('sha256', $first['authorization_code']))
        ->and($first['authorization']->refresh()->status)->toBe(MobileDeviceBindingAuthorization::STATUS_REVOKED)
        ->and($second['authorization']->status)->toBe(MobileDeviceBindingAuthorization::STATUS_PENDING)
        ->and($second['authorization']->expires_at->greaterThan(now()))->toBeTrue();
});

it('exchanges a supervised authorization code for one mobile binding challenge', function (): void {
    [$company, $user, $worker, $token] = personalSecurityContext();
    $issued = app(CreateMobileDeviceBindingAuthorizationAction::class)->handle($company, $user, $user, $worker);

    $response = $this->withToken($token)->postJson('/api/v1/time/me/device-binding/challenge', [
        'authorization_code' => $issued['authorization_code'],
        'device_name' => 'Teléfono de prueba',
    ])->assertOk()
        ->assertJsonPath('data.authorization_id', $issued['authorization']->public_id)
        ->assertJsonPath('data.algorithm', 'ES256')
        ->assertJsonPath('data.signature_format', 'der');

    $authorization = $issued['authorization']->refresh();
    expect($authorization->status)->toBe(MobileDeviceBindingAuthorization::STATUS_CHALLENGED)
        ->and($authorization->requested_device_name)->toBe('Teléfono de prueba')
        ->and($authorization->challenge_hash)->toBe(hash('sha256', $response->json('data.challenge')))
        ->and($response->json('data.payload'))->toContain($response->json('data.challenge'));

    $this->withToken($token)->postJson('/api/v1/time/me/device-binding/challenge', [
        'authorization_code' => $issued['authorization_code'],
        'device_name' => 'Teléfono de prueba',
    ])->assertUnprocessable()->assertJsonValidationErrors('authorization_code');
});

it('activates a device only after it proves possession of its P-256 key', function (): void {
    [$company, $user, $worker, $token] = personalSecurityContext();
    $issued = app(CreateMobileDeviceBindingAuthorizationAction::class)->handle($company, $user, $user, $worker);
    $challenge = $this->withToken($token)->postJson('/api/v1/time/me/device-binding/challenge', ['authorization_code' => $issued['authorization_code'], 'device_name' => 'Android prueba'])->assertOk()->json('data');
    $privateKey = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgkNzeTcOuga3rNnYm
Yhyv6Ju3Qxfy4xjCQC9ylDyVhfehRANCAATvry0fKoOa22Bxs4JE6Rwbf9NlGrvt
g3WABzqxvdEk7/00UWWKGvkjel1DtLX38W4uzLMtuWKfg9Mt91bHeZk1
-----END PRIVATE KEY-----
PEM;
    $publicPem = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE768tHyqDmttgcbOCROkcG3/TZRq7
7YN1gAc6sb3RJO/9NFFlihr5I3pdQ7S19/FuLsyzLblin4PTLfdWx3mZNQ==
-----END PUBLIC KEY-----
PEM;
    preg_match('/-----BEGIN PUBLIC KEY-----(.*?)-----END PUBLIC KEY-----/s', $publicPem, $matches);
    $spki = base64_decode(preg_replace('/\s+/', '', $matches[1]), true);
    openssl_sign($challenge['payload'], $signature, $privateKey, OPENSSL_ALGO_SHA256);

    $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $this->withToken($token)->postJson('/api/v1/time/me/device-binding/complete', ['authorization_id' => $challenge['authorization_id'], 'public_key_spki' => $encode($spki), 'signature' => $encode($signature)])
        ->assertCreated()->assertJsonPath('data.binding.status', 'active');
    expect(MobileDeviceBinding::query()->sole()->worker_id)->toBe($worker->id)
        ->and($issued['authorization']->refresh()->status)->toBe(MobileDeviceBindingAuthorization::STATUS_CONSUMED);
});

it('requires personal write ability to bind a mobile device', function (): void {
    [$company, $user, $worker] = personalSecurityContext();
    $issued = app(CreateMobileDeviceBindingAuthorizationAction::class)->handle($company, $user, $user, $worker);
    $readOnlyToken = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal-read-only', ['self:read'])->plainTextToken;

    $this->withToken($readOnlyToken)->postJson('/api/v1/time/me/device-binding/challenge', [
        'authorization_code' => $issued['authorization_code'],
        'device_name' => 'Android prueba',
    ])->assertForbidden();
});

it('lists only the linked workers devices and preserves a revoked binding as evidence', function (): void {
    [$company, $user, $worker, $token] = personalSecurityContext();
    $binding = MobileDeviceBinding::factory()->for($company)->for($user)->for($worker)->create([
        'device_name' => 'Teléfono de trabajo',
        'status' => MobileDeviceBinding::STATUS_ACTIVE,
        'activated_at' => now(),
    ]);
    $otherCompany = Company::factory()->create(['customer_account_id' => CustomerAccount::factory()]);
    MobileDeviceBinding::factory()->for($otherCompany)->for(User::factory())->for(Worker::factory(['company_id' => $otherCompany->id]))->create();

    $this->withToken($token)->getJson('/api/v1/time/me/device-bindings')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $binding->public_id)
        ->assertJsonPath('data.0.status', MobileDeviceBinding::STATUS_ACTIVE)
        ->assertJsonMissing(['public_key_spki' => $binding->public_key_spki]);

    app(RevokeMobileDeviceBindingAction::class)->handle($binding, $user);

    $this->withToken($token)->getJson('/api/v1/time/me/device-bindings')
        ->assertOk()
        ->assertJsonPath('data.0.status', MobileDeviceBinding::STATUS_REVOKED);
    $this->assertDatabaseHas('mobile_device_bindings', [
        'id' => $binding->id,
        'status' => MobileDeviceBinding::STATUS_REVOKED,
        'revoked_by_user_id' => $user->id,
    ]);
});

it('requires an active linked worker and the personal read ability', function (): void {
    [$company, $user, $worker, $token] = personalSecurityContext();
    $readlessToken = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal-without-read', ['self:write'])->plainTextToken;

    $this->withToken($readlessToken)->getJson('/api/v1/time/me/marking-security')->assertForbidden();

    UserWorkerLink::query()->where('company_id', $company->id)->where('user_id', $user->id)->update(['status' => 'revoked']);

    $this->withToken($token)->getJson('/api/v1/time/me/marking-security')->assertForbidden();
});

/**
 * @return array{Company, User, Worker, string}
 */
function personalSecurityContext(): array
{
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'worker_id' => $worker->id,
        'status' => 'active',
    ]);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal-security', ['self:read', 'self:write'])->plainTextToken;

    return [$company, $user, $worker, $token];
}
