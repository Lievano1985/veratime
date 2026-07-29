<?php

namespace App\Domains\Workers\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaveWorkerWithEmploymentRelationshipAction
{
    public function __construct(
        private readonly CreateWorkerAction $createWorkerAction,
        private readonly UpdateWorkerAction $updateWorkerAction,
        private readonly CreateEmploymentRelationshipAction $createRelationshipAction,
        private readonly TerminateWorkerAction $terminateWorkerAction,
        private readonly AssessEmploymentRelationshipEvidenceAction $assessEvidenceAction,
    ) {}

    public function handle(Company $company, ?Worker $worker, Center $center, array $data, ?User $actor = null): Worker
    {
        if ($center->company_id !== $company->id || ($worker && $worker->company_id !== $company->id)) {
            throw new InvalidArgumentException('La persona trabajadora y el centro deben pertenecer a la empresa activa.');
        }

        return DB::transaction(function () use ($company, $worker, $center, $data, $actor): Worker {
            $worker = $worker
                ? $this->updateWorkerAction->handle($worker, $data)
                : $this->createWorkerAction->handle($company, $data);

            $relationshipData = [
                'center_id' => $center->id,
                'position_name' => $data['position_name'] ?? null,
                'started_at' => $data['started_at'],
                'status' => 'active',
                'source' => 'web',
            ];

            $relationship = $worker->activeEmploymentRelationship()->first();

            if (! $relationship) {
                $this->createRelationshipAction->handle($company, $worker, $center, $relationshipData);
            } elseif ($this->relationshipChanged($relationship, $relationshipData)) {
                $this->applyRelationshipChange($company, $worker, $relationship, $center, $relationshipData, $data, $actor);
            }

            if (($data['status'] ?? null) === 'terminated') {
                $worker = $this->terminateWorkerAction->handle($worker);
            }

            return $worker->refresh();
        });
    }

    private function applyRelationshipChange(
        Company $company,
        Worker $worker,
        EmploymentRelationship $relationship,
        Center $center,
        array $relationshipData,
        array $data,
        ?User $actor,
    ): void {
        $evidence = $this->assessEvidenceAction->handle($relationship);
        $reason = trim((string) ($data['relationship_change_reason'] ?? ''));

        if ($reason === '') {
            throw new InvalidArgumentException('Indica el motivo del cambio de relacion laboral.');
        }

        if (! $evidence['has_protected_evidence']) {
            $this->correctRelationship($relationship, $center, $relationshipData, $reason, $actor);

            return;
        }

        $this->closeRelationshipBeforeNewStart($relationship, $relationshipData['started_at'], $evidence['latest_evidence_date']);

        $metadata = $relationshipData['metadata'] ?? [];
        $metadata['created_after_protected_evidence'] = [
            'reason' => $reason,
            'actor_user_id' => $actor?->id,
            'changed_at' => now()->toISOString(),
            'previous_relationship_id' => $relationship->id,
        ];

        $this->createRelationshipAction->handle($company, $worker, $center, [
            ...$relationshipData,
            'metadata' => $metadata,
        ]);
    }

    private function correctRelationship(
        EmploymentRelationship $relationship,
        Center $center,
        array $data,
        string $reason,
        ?User $actor,
    ): void {
        if ($center->company_id !== $relationship->company_id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa de la relacion laboral.');
        }

        $metadata = $relationship->metadata ?? [];
        $metadata['administrative_corrections'] ??= [];
        $metadata['administrative_corrections'][] = [
            'reason' => $reason,
            'actor_user_id' => $actor?->id,
            'corrected_at' => now()->toISOString(),
            'previous' => [
                'center_id' => $relationship->center_id,
                'position_name' => $relationship->position_name,
                'started_at' => $relationship->started_at?->toDateString(),
            ],
            'new' => [
                'center_id' => (int) $data['center_id'],
                'position_name' => $data['position_name'] ?? null,
                'started_at' => $this->dateString($data['started_at']),
            ],
        ];

        $relationship->forceFill([
            'center_id' => $center->id,
            'position_name' => $data['position_name'] ?? null,
            'started_at' => $this->dateString($data['started_at']),
            'metadata' => $metadata,
        ])->save();
    }

    private function relationshipChanged(EmploymentRelationship $relationship, array $data): bool
    {
        return (int) $relationship->center_id !== (int) $data['center_id']
            || (string) ($relationship->position_name ?? '') !== (string) ($data['position_name'] ?? '')
            || $relationship->started_at?->toDateString() !== $this->dateString($data['started_at']);
    }

    private function closeRelationshipBeforeNewStart(EmploymentRelationship $relationship, string $newStartedAt, ?string $latestEvidenceDate = null): void
    {
        $newStart = CarbonImmutable::parse($newStartedAt)->startOfDay();
        $currentStart = CarbonImmutable::parse($relationship->started_at)->startOfDay();

        if ($newStart->lessThanOrEqualTo($currentStart)) {
            throw new InvalidArgumentException('La relacion laboral ya tiene evidencia protegida. Para corregir fechas publicadas o con asistencia usa la correccion del horario publicado; no se puede sobrescribir desde trabajadores.');
        }

        $previousEnd = $newStart->subDay();

        if ($latestEvidenceDate && $previousEnd->lessThan(CarbonImmutable::parse($latestEvidenceDate)->startOfDay())) {
            throw new InvalidArgumentException('La nueva vigencia corta horarios publicados o asistencias existentes. Ajusta una fecha posterior a la evidencia o usa correccion versionada de horario.');
        }

        // Sprint 1C has no separate effective-date field, so the new started_at closes the previous relation the day before.
        $relationship->forceFill([
            'status' => 'ended',
            'ended_at' => $previousEnd->toDateString(),
        ])->save();
    }

    private function dateString(string $date): string
    {
        return CarbonImmutable::parse($date)->toDateString();
    }
}
