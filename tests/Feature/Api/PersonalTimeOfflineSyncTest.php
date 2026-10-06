<?php

namespace Tests\Feature\Api;

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\MobileMarkingPolicy;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalTimeOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductSeeder::class);
    }

    public function test_sync_replays_the_same_pending_event_without_creating_a_duplicate(): void
    {
        [$company, , , $token] = $this->personalContext();
        $event = [
            'client_event_id' => 'offline-clock-in-001',
            'event_type' => 'clock_in',
            'occurred_at' => '2026-10-05T15:00:00Z',
            'timezone' => 'America/Mexico_City',
            'device' => ['code' => 'android-pwa', 'name' => 'Teléfono de prueba'],
        ];

        $this->withToken($token)->postJson('/api/v1/time/me/time-events/sync', ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'accepted')
            ->assertJsonPath('meta.accepted', 1);

        $this->withToken($token)->postJson('/api/v1/time/me/time-events/sync', ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'already_registered')
            ->assertJsonPath('meta.already_registered', 1);

        $this->assertDatabaseCount('time_events', 1);
        $this->assertDatabaseHas('personal_time_event_submissions', [
            'company_id' => $company->id,
            'client_event_id' => 'offline-clock-in-001',
        ]);
    }

    public function test_sync_returns_a_conflict_for_a_reused_client_event_id_with_different_content(): void
    {
        [, , , $token] = $this->personalContext();
        $event = [
            'client_event_id' => 'offline-conflict-001',
            'event_type' => 'clock_in',
            'occurred_at' => '2026-10-05T15:00:00Z',
        ];

        $this->withToken($token)->postJson('/api/v1/time/me/time-events/sync', ['events' => [$event]])->assertOk();

        $this->withToken($token)->postJson('/api/v1/time/me/time-events/sync', ['events' => [[
            ...$event,
            'event_type' => 'clock_out',
        ]]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'conflict')
            ->assertJsonPath('data.0.error_code', 'idempotency_conflict')
            ->assertJsonPath('data.0.retryable', false)
            ->assertJsonPath('meta.conflict', 1);

        $this->assertDatabaseCount('time_events', 1);
        $this->assertDatabaseCount('personal_time_event_submissions', 1);
    }

    public function test_individual_marking_returns_a_safe_conflict_response_without_exposing_the_original_payload(): void
    {
        [, , , $token] = $this->personalContext();
        $key = 'individual-conflict-001';

        $this->withToken($token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/time/me/time-events', [
                'event_type' => 'clock_in',
                'occurred_at' => '2026-10-05T15:00:00Z',
                'metadata' => ['note' => 'Marcaje original'],
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/time/me/time-events', [
                'event_type' => 'clock_out',
                'occurred_at' => '2026-10-05T18:00:00Z',
                'metadata' => ['note' => 'Contenido diferente'],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'idempotency_conflict')
            ->assertJsonPath('error.retryable', false)
            ->assertJsonPath('error.retain_local', true)
            ->assertJsonMissing(['Marcaje original'])
            ->assertJsonMissing(['Contenido diferente']);

        $this->assertDatabaseCount('time_events', 1);
    }

    public function test_sync_marks_expired_security_reference_as_retryable_without_losing_the_pending_event(): void
    {
        [$company, , , $token] = $this->personalContext();
        MobileMarkingPolicy::factory()->for($company)->active()->create([
            'mode' => MobileMarkingPolicy::MODE_CIRCLE,
            'center_latitude' => '19.4326080',
            'center_longitude' => '-99.1332090',
            'radius_meters' => 150,
        ]);

        $reference = $this->withToken($token)->getJson('/api/v1/time/me/marking-security')
            ->assertOk()
            ->json('data.time_reference');

        $occurredAt = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $this->travel(6)->minutes();

        $this->withToken($token)->postJson('/api/v1/time/me/time-events/sync', ['events' => [[
            'client_event_id' => 'offline-stale-reference-001',
            'event_type' => 'clock_in',
            'occurred_at' => $occurredAt,
            'security' => [
                'time_reference_id' => $reference['id'],
                'location' => [
                    'latitude' => '19.4326080',
                    'longitude' => '-99.1332090',
                    'accuracy_meters' => '5.00',
                    'captured_at' => $occurredAt,
                    'is_mocked' => false,
                ],
            ],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'rejected')
            ->assertJsonPath('data.0.error_code', 'time_unverifiable')
            ->assertJsonPath('data.0.retryable', false)
            ->assertJsonPath('data.0.retain_local', true);

        $this->assertDatabaseCount('time_events', 0);
        $this->assertDatabaseCount('personal_time_event_submissions', 0);
    }

    /**
     * @return array{0: Company, 1: User, 2: Worker, 3: string}
     */
    private function personalContext(): array
    {
        $account = CustomerAccount::factory()->create();
        $company = Company::factory()->create(['customer_account_id' => $account->id]);
        $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
        $worker = Worker::factory()->create(['company_id' => $company->id]);
        UserWorkerLink::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'worker_id' => $worker->id,
            'status' => 'active',
        ]);
        $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal-offline', ['self:read', 'self:write'])->plainTextToken;

        return [$company, $user, $worker, $token];
    }
}
