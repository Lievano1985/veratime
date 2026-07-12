<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class RegisterManualTimeEventAction
{
    private const ALLOWED_EVENT_TYPES = [
        'clock_in',
        'clock_out',
        'break_start',
        'break_end',
    ];

    public function __construct(
        private readonly CreateTimeEventAction $createTimeEvent,
    ) {}

    public function handle(Company $company, User $sourceUser, Worker $worker, array $data): TimeEvent
    {
        Gate::forUser($sourceUser)->authorize('create', [TimeEvent::class, $company]);

        $this->assertWorkerCanReceiveManualEvent($company, $worker);

        $eventType = $this->validateEventType($data['event_type'] ?? null);
        $reason = $this->validateReason($data['reason'] ?? null);
        $employmentRelationship = $this->resolveActiveEmploymentRelationship($company, $worker);
        $center = $employmentRelationship?->center;
        $timezone = $this->validateTimezone($data['timezone'] ?? $center?->timezone ?? $company->timezone);
        $local = $this->validateLocalDateTime($data['occurred_local_date'] ?? null, $data['occurred_local_time'] ?? null, $timezone);

        return $this->createTimeEvent->handle(
            $company,
            $worker,
            [
                'event_type' => $eventType,
                'occurred_local_date' => $local->toDateString(),
                'occurred_local_time' => $local->format('H:i:s'),
                'timezone' => $timezone,
                'received_at' => CarbonImmutable::now('UTC'),
                'source' => 'admin_manual',
                'metadata' => [
                    'channel' => 'manual',
                    'reason' => $reason,
                    'captured_by' => $sourceUser->id,
                    'context' => 'manual_justified_entry',
                ],
            ],
            $employmentRelationship,
            $center,
            $sourceUser,
        );
    }

    private function assertWorkerCanReceiveManualEvent(Company $company, Worker $worker): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('Manual capture requires an active company.');
        }

        if ($worker->company_id !== $company->id || $worker->status !== 'active') {
            throw new InvalidArgumentException('Worker must be active and belong to the active company.');
        }
    }

    private function validateEventType(?string $eventType): string
    {
        if (! $eventType || ! in_array($eventType, self::ALLOWED_EVENT_TYPES, true)) {
            throw new InvalidArgumentException('Manual event type is invalid.');
        }

        return $eventType;
    }

    private function validateReason(?string $reason): string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Manual capture reason is required.');
        }

        return $reason;
    }

    private function validateTimezone(?string $timezone): string
    {
        if (! $timezone || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Manual event timezone is invalid.');
        }

        return $timezone;
    }

    private function validateLocalDateTime(?string $date, ?string $time, string $timezone): CarbonImmutable
    {
        if (blank($date) || blank($time)) {
            throw new InvalidArgumentException('Manual event date and time are required.');
        }

        return CarbonImmutable::parse($date.' '.$time, $timezone);
    }

    private function resolveActiveEmploymentRelationship(Company $company, Worker $worker): ?EmploymentRelationship
    {
        $relationship = $worker->activeEmploymentRelationship()
            ->with('center')
            ->first();

        if (! $relationship) {
            return null;
        }

        if ($relationship->company_id !== $company->id || $relationship->worker_id !== $worker->id) {
            throw new InvalidArgumentException('Employment relationship must belong to the same worker and company.');
        }

        if ($relationship->center && $relationship->center->company_id !== $company->id) {
            throw new InvalidArgumentException('Center must belong to the active company.');
        }

        return $relationship;
    }
}