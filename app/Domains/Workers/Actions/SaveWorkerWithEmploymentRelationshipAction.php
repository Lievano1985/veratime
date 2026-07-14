<?php

namespace App\Domains\Workers\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
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
    ) {}

    public function handle(Company $company, ?Worker $worker, Center $center, array $data): Worker
    {
        if ($center->company_id !== $company->id || ($worker && $worker->company_id !== $company->id)) {
            throw new InvalidArgumentException('La persona trabajadora y el centro deben pertenecer a la empresa activa.');
        }

        return DB::transaction(function () use ($company, $worker, $center, $data): Worker {
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
                $this->closeRelationshipBeforeNewStart($relationship, $relationshipData['started_at']);
                $this->createRelationshipAction->handle($company, $worker, $center, $relationshipData);
            }

            if (($data['status'] ?? null) === 'terminated') {
                $worker = $this->terminateWorkerAction->handle($worker);
            }

            return $worker->refresh();
        });
    }

    private function relationshipChanged(EmploymentRelationship $relationship, array $data): bool
    {
        return (int) $relationship->center_id !== (int) $data['center_id']
            || (string) ($relationship->position_name ?? '') !== (string) ($data['position_name'] ?? '')
            || $relationship->started_at?->toDateString() !== $this->dateString($data['started_at']);
    }

    private function closeRelationshipBeforeNewStart(EmploymentRelationship $relationship, string $newStartedAt): void
    {
        $newStart = CarbonImmutable::parse($newStartedAt)->startOfDay();
        $currentStart = CarbonImmutable::parse($relationship->started_at)->startOfDay();

        if ($newStart->lessThanOrEqualTo($currentStart)) {
            throw new InvalidArgumentException('La nueva relacion laboral debe iniciar despues de la relacion activa.');
        }

        // Sprint 1C has no separate effective-date field, so the new started_at closes the previous relation the day before.
        $relationship->forceFill([
            'status' => 'ended',
            'ended_at' => $newStart->subDay()->toDateString(),
        ])->save();
    }

    private function dateString(string $date): string
    {
        return CarbonImmutable::parse($date)->toDateString();
    }
}
