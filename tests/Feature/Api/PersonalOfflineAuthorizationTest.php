<?php

namespace Tests\Feature\Api;

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Domains\Workers\Actions\RevokeMobileDeviceBindingAction;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\MobileDeviceBinding;
use App\Models\MobileMarkingPolicy;
use App\Models\MobileOfflineMarkingCapture;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalOfflineAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgkNzeTcOuga3rNnYm
Yhyv6Ju3Qxfy4xjCQC9ylDyVhfehRANCAATvry0fKoOa22Bxs4JE6Rwbf9NlGrvt
g3WABzqxvdEk7/00UWWKGvkjel1DtLX38W4uzLMtuWKfg9Mt91bHeZk1
-----END PRIVATE KEY-----
PEM;

    private const PUBLIC_KEY_SPKI = 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE768tHyqDmttgcbOCROkcG3/TZRq77YN1gAc6sb3RJO/9NFFlihr5I3pdQ7S19/FuLsyzLblin4PTLfdWx3mZNQ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_a_full_offline_workday_is_reconciled_once_after_the_authorization_has_expired(): void
    {
        [$company, $user, $worker, $token, $policy, $binding] = $this->offlineContext();
        $issuedMonotonic = 1_000_000;
        $authorization = $this->issueAuthorization($token, $binding, $issuedMonotonic);
        $startedAt = CarbonImmutable::parse($authorization['issued_at'])->utc()->startOfSecond();
        $events = [
            $this->offlineEvent('offline-full-day-in', 'clock_in', $startedAt, $issuedMonotonic, $authorization, $binding, $policy),
            $this->offlineEvent('offline-full-day-break-start', 'break_start', $startedAt->addHours(4), $issuedMonotonic + 14_400_000, $authorization, $binding, $policy),
            $this->offlineEvent('offline-full-day-break-end', 'break_end', $startedAt->addHours(4)->addMinutes(30), $issuedMonotonic + 16_200_000, $authorization, $binding, $policy),
            $this->offlineEvent('offline-full-day-out', 'clock_out', $startedAt->addHours(8), $issuedMonotonic + 28_800_000, $authorization, $binding, $policy),
        ];

        $this->travel(13)->hours();

        $this->withToken($token)->postJson('/api/v1/time/me/time-events/sync', ['events' => $events])
            ->assertOk()
            ->assertJsonPath('meta.accepted', 4)
            ->assertJsonPath('meta.pending_review', 0)
            ->assertJsonPath('data.0.status', 'accepted')
            ->assertJsonPath('data.3.status', 'accepted');

        $this->withToken($token)->postJson('/api/v1/time/me/time-events/sync', ['events' => $events])
            ->assertOk()
            ->assertJsonPath('meta.already_registered', 4);

        $this->assertDatabaseCount('time_events', 4);
        $this->assertDatabaseCount('mobile_offline_marking_captures', 4);
        $this->assertDatabaseHas('mobile_offline_marking_captures', [
            'company_id' => $company->id,
            'client_event_id' => 'offline-full-day-out',
            'status' => MobileOfflineMarkingCapture::STATUS_ACCEPTED,
            'offline_authorization_public_id' => $authorization['id'],
        ]);
    }

    public function test_a_capture_before_binding_revocation_is_verified_at_capture_time(): void
    {
        [, $user, , $token, $policy, $binding] = $this->offlineContext();
        $authorization = $this->issueAuthorization($token, $binding, 2_000_000);
        $capturedAt = CarbonImmutable::parse($authorization['issued_at'])->utc()->startOfSecond();
        $event = $this->offlineEvent('offline-before-revocation', 'clock_in', $capturedAt, 2_000_000, $authorization, $binding, $policy);

        $this->travel(1)->minute();
        app(RevokeMobileDeviceBindingAction::class)->handle($binding, $user);

        $this->withToken($token)->postJson('/api/v1/time/me/time-events/sync', ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'accepted');

        $this->assertDatabaseCount('time_events', 1);
    }

    public function test_a_restarted_monotonic_clock_is_preserved_for_review_without_creating_attendance(): void
    {
        [$company, , , $token, $policy, $binding] = $this->offlineContext();
        $authorization = $this->issueAuthorization($token, $binding, 3_000_000);
        $capturedAt = CarbonImmutable::parse($authorization['issued_at'])->utc()->startOfSecond();
        $event = $this->offlineEvent('offline-after-reboot', 'clock_in', $capturedAt, 1_000, $authorization, $binding, $policy);

        $this->withToken($token)->postJson('/api/v1/time/me/time-events/sync', ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending_review')
            ->assertJsonPath('data.0.error_code', 'monotonic_restarted')
            ->assertJsonPath('data.0.retain_local', true);

        $this->assertDatabaseCount('time_events', 0);
        $this->assertDatabaseHas('mobile_offline_marking_captures', [
            'company_id' => $company->id,
            'client_event_id' => 'offline-after-reboot',
            'status' => MobileOfflineMarkingCapture::STATUS_PENDING_REVIEW,
            'review_reason' => 'monotonic_restarted',
        ]);
    }

    /** @return array{Company, User, Worker, string, MobileMarkingPolicy, MobileDeviceBinding} */
    private function offlineContext(): array
    {
        $account = CustomerAccount::factory()->create();
        $company = Company::factory()->create(['customer_account_id' => $account->id]);
        $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
        $worker = Worker::factory()->create(['company_id' => $company->id]);
        UserWorkerLink::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
        $policy = MobileMarkingPolicy::factory()->for($company)->active()->create([
            'version' => 4,
            'requires_device_binding' => true,
            'requires_biometric_unlock' => true,
            'offline_authorization_duration_minutes' => 720,
        ]);
        $binding = MobileDeviceBinding::factory()->for($company)->for($user)->for($worker)->create([
            'status' => MobileDeviceBinding::STATUS_ACTIVE,
            'activated_at' => now(),
            'public_key_spki' => self::PUBLIC_KEY_SPKI,
        ]);
        $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'offline-authorization', ['self:read', 'self:write'])->plainTextToken;

        return [$company, $user, $worker, $token, $policy, $binding];
    }

    /** @return array<string, mixed> */
    private function issueAuthorization(string $token, MobileDeviceBinding $binding, int $issuedMonotonic): array
    {
        return $this->withToken($token)->postJson('/api/v1/time/me/offline-marking-authorizations', [
            'binding_id' => $binding->public_id,
            'issued_monotonic_milliseconds' => $issuedMonotonic,
        ])->assertCreated()->assertJsonPath('data.binding_id', $binding->public_id)->json('data');
    }

    /** @param array<string, mixed> $authorization @return array<string, mixed> */
    private function offlineEvent(string $id, string $type, CarbonImmutable $occurredAt, int $monotonic, array $authorization, MobileDeviceBinding $binding, MobileMarkingPolicy $policy): array
    {
        $timestamp = $occurredAt->format('Y-m-d\\TH:i:s\\Z');
        $location = ['latitude' => '19.4326080', 'longitude' => '-99.1332090', 'accuracy_meters' => '8.50', 'captured_at' => $timestamp, 'is_mocked' => false];
        $payload = implode("\n", [
            'VERA-MOBILE-OFFLINE-EVENT-V1', $id, $type, $timestamp, 'America/Mexico_City', $binding->public_id,
            $policy->public_id, (string) $policy->version, $authorization['id'], (string) $monotonic,
            $location['latitude'], $location['longitude'], $location['accuracy_meters'], $location['captured_at'], '0',
        ]);
        openssl_sign($payload, $signature, self::PRIVATE_KEY, OPENSSL_ALGO_SHA256);

        return [
            'client_event_id' => $id,
            'event_type' => $type,
            'occurred_at' => $timestamp,
            'timezone' => 'America/Mexico_City',
            'security' => [
                'offline_authorization_id' => $authorization['id'],
                'binding_id' => $binding->public_id,
                'policy_id' => $policy->public_id,
                'policy_version' => $policy->version,
                'monotonic_elapsed_milliseconds' => $monotonic,
                'signature' => $this->base64Url($signature),
                'location' => $location,
            ],
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
