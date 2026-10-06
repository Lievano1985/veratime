<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\MobileMarkingEventEvidence;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegisterPersonalTimeEventAction
{
    public function __construct(
        private readonly CreateTimeEventAction $createTimeEvent,
        private readonly ValidatePersonalTimeEventSecurityAction $validateSecurity,
    ) {}

    /**
     * @param  array{event_type: string, occurred_at: string, timezone?: ?string, metadata?: ?array, device?: ?array, security?: ?array, idempotency_key: string, trace_id?: ?string}  $data
     * @return array{event: TimeEvent, created: bool}
     */
    public function handle(Company $company, User $user, Worker $worker, array $data): array
    {
        $existing = $this->findExistingEvent($company, $user, $worker, $data['idempotency_key']);

        if ($existing) {
            return ['event' => $existing, 'created' => false];
        }

        try {
            $event = DB::transaction(function () use ($company, $user, $worker, $data): TimeEvent {
                $security = $this->validateSecurity->handle($company, $user, $worker, $data);
                $relationship = $worker->activeEmploymentRelationship()
                    ->where('company_id', $company->id)
                    ->with('center')
                    ->first();
                $event = $this->createTimeEvent->handle(
                    $company,
                    $worker,
                    [
                        'event_type' => $data['event_type'],
                        'occurred_at_utc' => $data['occurred_at'],
                        'timezone' => $data['timezone'] ?? null,
                        'source' => 'pwa',
                        'status' => 'valid',
                        'idempotency_key' => $data['idempotency_key'],
                        'metadata' => [
                            'channel' => 'pwa_personal',
                            'token_id' => $user->currentAccessToken()?->getKey(),
                            'trace_id' => $data['trace_id'] ?? null,
                            'device' => $data['device'] ?? null,
                            'metadata' => $data['metadata'] ?? [],
                        ],
                    ],
                    $relationship,
                    $relationship?->center,
                    $user,
                );

                if ($security) {
                    MobileMarkingEventEvidence::query()->create([
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                        'worker_id' => $worker->id,
                        'time_event_id' => $event->id,
                        'mobile_marking_policy_id' => $security['policy']->id,
                        'mobile_device_binding_id' => $security['binding']?->id,
                        'mobile_marking_time_reference_id' => $security['reference']->id,
                        'policy_public_id' => $security['policy']->public_id,
                        'policy_version' => $security['policy']->version,
                        'schema_version' => 1,
                        'signature' => $security['signature'] ?: null,
                        'payload_hash' => $security['payload_hash'],
                        'policy_snapshot' => $security['policy_snapshot'],
                        'location_latitude' => $security['location']['latitude'] ?? null,
                        'location_longitude' => $security['location']['longitude'] ?? null,
                        'location_accuracy_meters' => $security['location']['accuracy_meters'] ?? null,
                        'location_captured_at' => $security['location']['captured_at'] ?? null,
                        'location_is_mocked' => $security['location']['is_mocked'] ?? null,
                        'distance_meters' => $security['location']['distance_meters'] ?? null,
                    ]);
                }

                return $event;
            });
        } catch (UniqueConstraintViolationException) {
            $event = $this->findExistingEvent($company, $user, $worker, $data['idempotency_key']);

            if (! $event) {
                throw new InvalidArgumentException('No fue posible registrar el evento personal de jornada.');
            }

            return ['event' => $event, 'created' => false];
        }

        return ['event' => $event, 'created' => true];
    }

    private function findExistingEvent(Company $company, User $user, Worker $worker, string $idempotencyKey): ?TimeEvent
    {
        $event = TimeEvent::query()
            ->where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($event && ($event->worker_id !== $worker->id || $event->source !== 'pwa' || $event->source_user_id !== $user->id)) {
            throw new InvalidArgumentException('La llave de idempotencia no es válida para este marcaje.');
        }

        return $event;
    }
}
