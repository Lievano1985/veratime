<?php

namespace App\Domains\TimeRecords\Actions;

use App\Domains\Organization\Support\ScopedOperationalAccess;
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
        private readonly ScopedOperationalAccess $scopedAccess,
    ) {}

    public function handle(Company $company, User $sourceUser, Worker $worker, array $data): TimeEvent
    {
        Gate::forUser($sourceUser)->authorize('create', [TimeEvent::class, $company]);

        $this->assertWorkerCanReceiveManualEvent($company, $worker);

        $eventType = $this->validateEventType($data['event_type'] ?? null);
        $reason = $this->validateReason($data['reason'] ?? null);
        $employmentRelationship = $this->resolveActiveEmploymentRelationship($company, $worker);
        $center = $employmentRelationship?->center;
        $this->assertActorCanCaptureForCenter($company, $sourceUser, $center);
        $timezone = $this->validateTimezone($data['timezone'] ?? $center?->timezone ?? $company->timezone);
        $local = $this->validateLocalDateTime($data['occurred_local_date'] ?? null, $data['occurred_local_time'] ?? null, $timezone);
        $capturedAt = CarbonImmutable::now('UTC');

        return $this->createTimeEvent->handle(
            $company,
            $worker,
            [
                'event_type' => $eventType,
                'occurred_local_date' => $local->toDateString(),
                'occurred_local_time' => $local->format('H:i:s'),
                'timezone' => $timezone,
                'received_at' => $capturedAt,
                'source' => 'admin_manual',
                'status' => 'valid',
                'metadata' => [
                    'channel' => 'manual',
                    'reason' => $reason,
                    'captured_by' => $sourceUser->id,
                    'captured_at' => $capturedAt->toISOString(),
                    'context' => 'manual_justified_entry',
                    'review' => [
                        'decision' => 'auto_approved',
                        'actor_user_id' => $sourceUser->id,
                        'reviewed_at' => $capturedAt->toISOString(),
                        'reason' => 'Captura justificada por usuario autorizado.',
                        'previous_status' => null,
                        'resulting_status' => 'valid',
                    ],
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
            throw new InvalidArgumentException('La captura manual requiere una empresa activa.');
        }

        if ($worker->company_id !== $company->id || $worker->status !== 'active') {
            throw new InvalidArgumentException('La persona trabajadora debe estar activa y pertenecer a la empresa activa.');
        }
    }

    private function validateEventType(?string $eventType): string
    {
        if (! $eventType || ! in_array($eventType, self::ALLOWED_EVENT_TYPES, true)) {
            throw new InvalidArgumentException('El tipo de evento manual no es valido.');
        }

        return $eventType;
    }

    private function validateReason(?string $reason): string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw new InvalidArgumentException('El motivo de la captura manual es requerido.');
        }

        return $reason;
    }

    private function validateTimezone(?string $timezone): string
    {
        if (! $timezone || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('La zona horaria del evento manual no es valida.');
        }

        return $timezone;
    }

    private function validateLocalDateTime(?string $date, ?string $time, string $timezone): CarbonImmutable
    {
        if (blank($date) || blank($time)) {
            throw new InvalidArgumentException('La fecha y hora del evento manual son requeridas.');
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
            throw new InvalidArgumentException('La relacion laboral debe pertenecer a la misma persona trabajadora y empresa.');
        }

        if ($relationship->center && $relationship->center->company_id !== $company->id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa activa.');
        }

        return $relationship;
    }

    private function assertActorCanCaptureForCenter(Company $company, User $sourceUser, ?Center $center): void
    {
        if (! $this->scopedAccess->canOperateFullCenter($sourceUser, $company, $center)) {
            throw new InvalidArgumentException('El usuario no puede capturar eventos para este centro.');
        }
    }
}
