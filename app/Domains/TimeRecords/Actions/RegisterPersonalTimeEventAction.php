<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

class RegisterPersonalTimeEventAction
{
    public function __construct(
        private readonly CreateTimeEventAction $createTimeEvent,
    ) {}

    /**
     * @param  array{event_type: string, occurred_at: string, timezone?: ?string, metadata?: ?array, device?: ?array, idempotency_key: string, trace_id?: ?string}  $data
     * @return array{event: TimeEvent, created: bool}
     */
    public function handle(Company $company, User $user, Worker $worker, array $data): array
    {
        $existing = $this->findExistingEvent($company, $user, $worker, $data['idempotency_key']);

        if ($existing) {
            return ['event' => $existing, 'created' => false];
        }

        $relationship = $worker->activeEmploymentRelationship()
            ->where('company_id', $company->id)
            ->with('center')
            ->first();

        try {
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
