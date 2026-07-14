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
            throw new InvalidArgumentException('La credencial de kiosco no esta activa.');
        }

        if (! $company || $company->status !== 'active') {
            throw new InvalidArgumentException('El registro de kiosco requiere una empresa activa.');
        }

        if (! $worker || $worker->company_id !== $company->id || $worker->status !== 'active') {
            throw new InvalidArgumentException('La persona trabajadora debe estar activa y pertenecer a la empresa de la credencial.');
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
            throw new InvalidArgumentException('La relacion laboral debe pertenecer a la misma persona trabajadora y empresa.');
        }

        if ($relationship->center && $relationship->center->company_id !== $company->id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa activa.');
        }

        return $relationship;
    }
}