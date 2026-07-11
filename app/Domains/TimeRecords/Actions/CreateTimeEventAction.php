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
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTimeEventAction
{
    public function handle(
        Company $company,
        Worker $worker,
        array $data,
        ?EmploymentRelationship $employmentRelationship = null,
        ?Center $center = null,
        ?User $sourceUser = null,
    ): TimeEvent {
        $this->assertCompanyIsActive($company);
        $this->assertWorkerBelongsToCompany($worker, $company);
        $this->assertEmploymentRelationshipBelongsToWorker($employmentRelationship, $worker, $company);
        $this->assertCenterBelongsToCompany($center, $company);
        $this->assertSourceUserBelongsToCompany($sourceUser, $company);

        $eventType = $this->validateInList($data['event_type'] ?? null, TimeEvent::EVENT_TYPES, 'Time event type is invalid.');
        $source = $this->validateInList($data['source'] ?? null, TimeEvent::SOURCES, 'Time event source is invalid.');
        $status = $this->validateInList($data['status'] ?? $this->defaultStatusForSource($source), TimeEvent::STATUSES, 'Time event status is invalid.');
        $times = $this->normalizeTimes($company, $center, $data);

        return DB::transaction(function () use ($company, $worker, $employmentRelationship, $center, $sourceUser, $data, $eventType, $source, $status, $times): TimeEvent {
            if ($existing = $this->findExistingIdempotentEvent($company, $source, $data)) {
                return $existing;
            }

            $event = new TimeEvent([
                'event_type' => $eventType,
                'occurred_at_utc' => $times['occurred_at_utc'],
                'occurred_local_date' => $times['occurred_local_date'],
                'occurred_local_time' => $times['occurred_local_time'],
                'timezone' => $times['timezone'],
                'received_at' => $times['received_at'],
                'source' => $source,
                'external_id' => $data['external_id'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'status' => $status,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $event->company()->associate($company);
            $event->worker()->associate($worker);
            $event->employmentRelationship()->associate($employmentRelationship);
            $event->center()->associate($center);
            $event->sourceUser()->associate($sourceUser);
            $event->save();

            return $event->refresh();
        });
    }

    private function assertCompanyIsActive(Company $company): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('Time events require an active company.');
        }
    }

    private function assertWorkerBelongsToCompany(Worker $worker, Company $company): void
    {
        if ($worker->company_id !== $company->id) {
            throw new InvalidArgumentException('Worker must belong to the active company.');
        }
    }

    private function assertEmploymentRelationshipBelongsToWorker(?EmploymentRelationship $employmentRelationship, Worker $worker, Company $company): void
    {
        if (! $employmentRelationship) {
            return;
        }

        if ($employmentRelationship->company_id !== $company->id || $employmentRelationship->worker_id !== $worker->id) {
            throw new InvalidArgumentException('Employment relationship must belong to the same worker and company.');
        }
    }

    private function assertCenterBelongsToCompany(?Center $center, Company $company): void
    {
        if ($center && $center->company_id !== $company->id) {
            throw new InvalidArgumentException('Center must belong to the active company.');
        }
    }

    private function assertSourceUserBelongsToCompany(?User $sourceUser, Company $company): void
    {
        if ($sourceUser && ! $sourceUser->belongsToCompany($company)) {
            throw new InvalidArgumentException('Source user must belong to the active company.');
        }
    }

    /**
     * @param array<int, string> $allowed
     */
    private function validateInList(?string $value, array $allowed, string $message): string
    {
        if (! $value || ! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private function defaultStatusForSource(string $source): string
    {
        return $source === 'admin_manual' ? 'pending_review' : 'valid';
    }

    /**
     * @return array{occurred_at_utc: CarbonImmutable, occurred_local_date: string, occurred_local_time: string, timezone: string, received_at: CarbonImmutable}
     */
    private function normalizeTimes(Company $company, ?Center $center, array $data): array
    {
        $timezone = $data['timezone'] ?? $center?->timezone ?? $company->timezone;

        if (! $timezone || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Time event timezone is invalid.');
        }

        if (! blank($data['occurred_at_utc'] ?? null)) {
            $occurredAtUtc = CarbonImmutable::parse($data['occurred_at_utc'], 'UTC')->utc();
            $local = $occurredAtUtc->setTimezone($timezone);
        } elseif (! blank($data['occurred_local_date'] ?? null) && ! blank($data['occurred_local_time'] ?? null)) {
            $local = CarbonImmutable::parse($data['occurred_local_date'].' '.$data['occurred_local_time'], $timezone);
            $occurredAtUtc = $local->utc();
        } else {
            throw new InvalidArgumentException('Time event occurrence time is required.');
        }

        $receivedAt = blank($data['received_at'] ?? null)
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($data['received_at'], 'UTC')->utc();

        return [
            'occurred_at_utc' => $occurredAtUtc,
            'occurred_local_date' => $local->toDateString(),
            'occurred_local_time' => $local->format('H:i:s'),
            'timezone' => $timezone,
            'received_at' => $receivedAt,
        ];
    }

    private function findExistingIdempotentEvent(Company $company, string $source, array $data): ?TimeEvent
    {
        if (! blank($data['idempotency_key'] ?? null)) {
            $event = TimeEvent::query()
                ->where('company_id', $company->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($event) {
                return $event;
            }
        }

        if (! blank($data['external_id'] ?? null)) {
            return TimeEvent::query()
                ->where('company_id', $company->id)
                ->where('source', $source)
                ->where('external_id', $data['external_id'])
                ->lockForUpdate()
                ->first();
        }

        return null;
    }
}