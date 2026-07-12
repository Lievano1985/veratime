<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\TimeEvent;
use App\Models\Worker;
use App\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class RegisterKioskTimeEventAction
{
    public function __construct(
        private readonly CreateTimeEventAction $createTimeEvent,
        private readonly ResolveCurrentTimeRecordStateAction $resolveCurrentState,
    ) {}

    public function handle(WorkerCredential $credential, string $eventType): TimeEvent
    {
        $credential->loadMissing(['company', 'worker']);

        $company = $credential->company;
        $worker = $credential->worker;

        $this->assertCredentialCanRegister($credential, $company, $worker);

        $employmentRelationship = $this->resolveActiveEmploymentRelationship($company, $worker);
        $center = $employmentRelationship?->center;
        $timezone = $center?->timezone ?: $company->timezone;
        $localNow = CarbonImmutable::now($timezone);

        $state = $this->resolveCurrentState->handle($company, $worker, $localNow, $center);

        if (! in_array($eventType, $state['allowed_actions'], true)) {
            throw new InvalidArgumentException('La accion solicitada no esta disponible para el estado actual.');
        }

        return $this->createTimeEvent->handle(
            $company,
            $worker,
            [
                'event_type' => $eventType,
                'occurred_local_date' => $localNow->toDateString(),
                'occurred_local_time' => $localNow->format('H:i:s'),
                'timezone' => $timezone,
                'received_at' => CarbonImmutable::now('UTC'),
                'source' => 'kiosk',
                'status' => 'valid',
                'metadata' => [
                    'channel' => 'kiosk',
                    'credential_id' => $credential->id,
                    'context' => 'kiosk_time_registration',
                ],
            ],
            $employmentRelationship,
            $center,
            null,
        );
    }

    private function assertCredentialCanRegister(WorkerCredential $credential, ?Company $company, ?Worker $worker): void
    {
        if ($credential->status !== 'active') {
            throw new InvalidArgumentException('Kiosk credential is not active.');
        }

        if (! $company || $company->status !== 'active') {
            throw new InvalidArgumentException('Kiosk registration requires an active company.');
        }

        if (! $worker || $worker->company_id !== $company->id || $worker->status !== 'active') {
            throw new InvalidArgumentException('Worker must be active and belong to the credential company.');
        }
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