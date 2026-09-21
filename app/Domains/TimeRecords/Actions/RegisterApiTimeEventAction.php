<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

class RegisterApiTimeEventAction
{
    public function __construct(
        private readonly CreateTimeEventAction $createTimeEvent,
    ) {}

    /**
     * @return array{event: TimeEvent, created: bool}
     */
    public function handle(Company $company, User $actor, array $data): array
    {
        $worker = $this->resolveWorker($company, $data);
        $relationship = $this->resolveRelationship($company, $worker);
        $center = $this->resolveCenter($company, $relationship, $data);

        $existing = $this->findExistingEvent($company, $data);

        if ($existing) {
            return ['event' => $existing, 'created' => false];
        }

        try {
            $event = $this->createTimeEvent->handle(
                $company,
                $worker,
                [
                    'event_type' => $data['event_type'],
                    'occurred_at_utc' => $data['occurred_at'],
                    'timezone' => $data['timezone'] ?? null,
                    'source' => 'api',
                    'status' => 'valid',
                    'external_id' => $data['external_id'] ?? null,
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'metadata' => [
                        'channel' => 'api',
                        'token_id' => $actor->currentAccessToken()?->getKey(),
                        'trace_id' => $data['trace_id'] ?? null,
                        'device' => $data['device'] ?? null,
                        'metadata' => $data['metadata'] ?? [],
                    ],
                ],
                $relationship,
                $center,
                $actor,
            );
        } catch (UniqueConstraintViolationException) {
            $event = $this->findExistingEvent($company, $data);

            if (! $event) {
                throw new InvalidArgumentException('No fue posible registrar el evento de jornada.');
            }

            return ['event' => $event, 'created' => false];
        }

        return ['event' => $event, 'created' => true];
    }

    private function resolveWorker(Company $company, array $data): Worker
    {
        $query = $company->workers()->where('status', 'active');
        $workerId = $data['worker_id'] ?? null;
        $employeeCode = $data['employee_code'] ?? null;

        if ($workerId) {
            $worker = $query->whereKey($workerId)->first();

            if ($worker && $employeeCode && $worker->employee_code !== $employeeCode) {
                throw new InvalidArgumentException('El trabajador y el código de empleado no corresponden.');
            }

            if ($worker) {
                return $worker;
            }
        }

        if ($employeeCode) {
            $worker = $company->workers()
                ->where('status', 'active')
                ->where('employee_code', $employeeCode)
                ->first();

            if ($worker) {
                return $worker;
            }
        }

        throw new InvalidArgumentException('No se encontró una persona trabajadora activa para el evento.');
    }

    private function resolveRelationship(Company $company, Worker $worker): ?EmploymentRelationship
    {
        return $worker->activeEmploymentRelationship()
            ->where('company_id', $company->id)
            ->with('center')
            ->first();
    }

    private function resolveCenter(Company $company, ?EmploymentRelationship $relationship, array $data): ?Center
    {
        if (! empty($data['center_id'])) {
            $center = $company->centers()->whereKey($data['center_id'])->where('status', 'active')->first();

            if (! $center) {
                throw new InvalidArgumentException('El centro indicado no está activo para esta empresa.');
            }

            return $center;
        }

        return $relationship?->center;
    }

    private function findExistingEvent(Company $company, array $data): ?TimeEvent
    {
        if (! empty($data['idempotency_key'])) {
            return TimeEvent::query()
                ->where('company_id', $company->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
        }

        if (! empty($data['external_id'])) {
            return TimeEvent::query()
                ->where('company_id', $company->id)
                ->where('source', 'api')
                ->where('external_id', $data['external_id'])
                ->first();
        }

        return null;
    }
}
