<?php

namespace App\Domains\TimeRecords\Actions;

use App\Domains\TimeRecords\Exceptions\PersonalTimeEventConflictException;
use App\Domains\TimeRecords\Exceptions\PersonalTimeEventSecurityException;
use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;

class SyncPersonalTimeEventsAction
{
    public function __construct(
        private readonly RegisterPersonalTimeEventAction $registerEvent,
        private readonly ReconcileOfflinePersonalTimeEventAction $reconcileOffline,
    ) {}

    /**
     * @param  list<array{client_event_id: string, event_type: string, occurred_at: string, timezone?: ?string, metadata?: ?array, device?: ?array}>  $events
     * @return list<array{client_event_id: string, status: string, event?: TimeEvent, error?: string, error_code?: string, retryable?: bool, retain_local?: bool}>
     */
    public function handle(Company $company, User $user, Worker $worker, array $events, ?string $traceId = null): array
    {
        $results = [];

        foreach ($events as $event) {
            try {
                if (filled($event['security']['offline_authorization_id'] ?? null)) {
                    $result = $this->reconcileOffline->handle($company, $user, $worker, [
                        ...$event,
                        'idempotency_key' => $event['client_event_id'],
                        'trace_id' => $traceId,
                    ]);

                    if (! $result['event']) {
                        $results[] = [
                            'client_event_id' => $event['client_event_id'],
                            'status' => 'pending_review',
                            'error' => 'El marcaje se conservó como evidencia y requiere revisión de RH.',
                            'error_code' => $result['capture']->review_reason,
                            'retryable' => false,
                            'retain_local' => true,
                        ];

                        continue;
                    }

                    $results[] = [
                        'client_event_id' => $event['client_event_id'],
                        'status' => $result['created'] ? 'accepted' : 'already_registered',
                        'event' => $result['event'],
                        'retain_local' => false,
                    ];

                    continue;
                }

                $result = $this->registerEvent->handle($company, $user, $worker, [
                    ...$event,
                    'idempotency_key' => $event['client_event_id'],
                    'trace_id' => $traceId,
                ]);

                $results[] = [
                    'client_event_id' => $event['client_event_id'],
                    'status' => $result['created'] ? 'accepted' : 'already_registered',
                    'event' => $result['event'],
                    'retain_local' => false,
                ];
            } catch (PersonalTimeEventConflictException $exception) {
                $results[] = [
                    'client_event_id' => $event['client_event_id'],
                    'status' => 'conflict',
                    'error' => $exception->getMessage(),
                    'error_code' => 'idempotency_conflict',
                    'retryable' => false,
                    'retain_local' => true,
                ];
            } catch (PersonalTimeEventSecurityException $exception) {
                $results[] = [
                    'client_event_id' => $event['client_event_id'],
                    'status' => 'rejected',
                    'error' => $exception->getMessage(),
                    'error_code' => $exception->reason,
                    'retryable' => $exception->retryable,
                    'retain_local' => true,
                ];
            } catch (\InvalidArgumentException $exception) {
                $results[] = [
                    'client_event_id' => $event['client_event_id'],
                    'status' => 'rejected',
                    'error' => $exception->getMessage(),
                    'error_code' => 'invalid_event',
                    'retryable' => false,
                    'retain_local' => true,
                ];
            }
        }

        return $results;
    }
}
