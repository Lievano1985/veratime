<?php

namespace App\Domains\TimeRecords\Actions;

use App\Domains\WorkDays\Jobs\RecalculateWorkDayFromTimeEventJob;
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

        $eventType = $this->validateInList($data['event_type'] ?? null, TimeEvent::EVENT_TYPES, 'El tipo de evento de jornada no es valido.');
        $source = $this->validateInList($data['source'] ?? null, TimeEvent::SOURCES, 'La fuente del evento de jornada no es valida.');
        $status = $this->validateInList($data['status'] ?? $this->defaultStatusForSource($source), TimeEvent::STATUSES, 'El estado del evento de jornada no es valido.');
        $times = $this->normalizeTimes($company, $center, $data);

        $result = DB::transaction(function () use ($company, $worker, $employmentRelationship, $center, $sourceUser, $data, $eventType, $source, $status, $times): array {
            if ($existing = $this->findExistingIdempotentEvent($company, $source, $data)) {
                return ['event' => $existing, 'created' => false];
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

            return ['event' => $event->refresh(), 'created' => true];
        });

        if ($result['created'] && $this->shouldDispatchWorkDayRecalculation($result['event'])) {
            RecalculateWorkDayFromTimeEventJob::dispatch($result['event']->id, 'time_event_created')->afterCommit();
        }

        return $result['event'];
    }

    private function assertCompanyIsActive(Company $company): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('Los eventos de jornada requieren una empresa activa.');
        }
    }

    private function assertWorkerBelongsToCompany(Worker $worker, Company $company): void
    {
        if ($worker->company_id !== $company->id) {
            throw new InvalidArgumentException('La persona trabajadora debe pertenecer a la empresa activa.');
        }
    }

    private function assertEmploymentRelationshipBelongsToWorker(?EmploymentRelationship $employmentRelationship, Worker $worker, Company $company): void
    {
        if (! $employmentRelationship) {
            return;
        }

        if ($employmentRelationship->company_id !== $company->id || $employmentRelationship->worker_id !== $worker->id) {
            throw new InvalidArgumentException('La relacion laboral debe pertenecer a la misma persona trabajadora y empresa.');
        }
    }

    private function assertCenterBelongsToCompany(?Center $center, Company $company): void
    {
        if ($center && $center->company_id !== $company->id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa activa.');
        }
    }

    private function assertSourceUserBelongsToCompany(?User $sourceUser, Company $company): void
    {
        if ($sourceUser && ! $sourceUser->belongsToCompany($company)) {
            throw new InvalidArgumentException('La persona usuaria origen debe pertenecer a la empresa activa.');
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
            throw new InvalidArgumentException('La zona horaria del evento de jornada no es valida.');
        }

        if (! blank($data['occurred_at_utc'] ?? null)) {
            $occurredAtUtc = CarbonImmutable::parse($data['occurred_at_utc'], 'UTC')->utc();
            $local = $occurredAtUtc->setTimezone($timezone);
        } elseif (! blank($data['occurred_local_date'] ?? null) && ! blank($data['occurred_local_time'] ?? null)) {
            $local = CarbonImmutable::parse($data['occurred_local_date'].' '.$data['occurred_local_time'], $timezone);
            $occurredAtUtc = $local->utc();
        } else {
            throw new InvalidArgumentException('La hora del evento de jornada es requerida.');
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

    private function shouldDispatchWorkDayRecalculation(TimeEvent $event): bool
    {
        return $event->status === 'valid'
            && $event->employment_relationship_id !== null
            && ($event->event_type === 'clock_out' || $event->source === 'admin_manual');
    }
}
