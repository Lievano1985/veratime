<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;

class SyncPersonalTimeEventsAction
{
    public function __construct(
        private readonly RegisterPersonalTimeEventAction $registerEvent,
    ) {}

    /**
     * @param  list<array{client_event_id: string, event_type: string, occurred_at: string, timezone?: ?string, metadata?: ?array, device?: ?array}>  $events
     * @return list<array{client_event_id: string, status: string, event?: TimeEvent, error?: string}>
     */
    public function handle(Company $company, User $user, Worker $worker, array $events, ?string $traceId = null): array
    {
        $results = [];

        foreach ($events as $event) {
            try {
                $result = $this->registerEvent->handle($company, $user, $worker, [
                    ...$event,
                    'idempotency_key' => $event['client_event_id'],
                    'trace_id' => $traceId,
                ]);

                $results[] = [
                    'client_event_id' => $event['client_event_id'],
                    'status' => $result['created'] ? 'accepted' : 'already_registered',
                    'event' => $result['event'],
                ];
            } catch (\InvalidArgumentException $exception) {
                $results[] = [
                    'client_event_id' => $event['client_event_id'],
                    'status' => 'rejected',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }
}
