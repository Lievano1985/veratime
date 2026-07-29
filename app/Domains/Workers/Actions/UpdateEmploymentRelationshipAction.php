<?php

namespace App\Domains\Workers\Actions;

use App\Models\Center;
use App\Models\EmploymentRelationship;
use App\Models\User;
use InvalidArgumentException;

class UpdateEmploymentRelationshipAction
{
    public function __construct(
        private readonly AssessEmploymentRelationshipEvidenceAction $assessEvidenceAction,
    ) {}

    public function handle(EmploymentRelationship $relationship, Center $center, array $data, ?User $actor = null): EmploymentRelationship
    {
        if ($center->company_id !== $relationship->company_id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa de la relacion laboral.');
        }

        $positionName = $data['position_name'] ?? $relationship->position_name;
        $startedAt = $data['started_at'] ?? $relationship->started_at?->toDateString();

        if ((int) $relationship->center_id !== (int) $center->id
            || (string) ($relationship->position_name ?? '') !== (string) ($positionName ?? '')
            || $relationship->started_at?->toDateString() !== $startedAt) {
            if ($this->assessEvidenceAction->handle($relationship)['has_protected_evidence']) {
                throw new InvalidArgumentException('La relacion laboral ya tiene evidencia protegida. Para cambiar fechas publicadas usa correccion versionada de horario.');
            }

            $reason = trim((string) ($data['relationship_change_reason'] ?? $data['correction_reason'] ?? ''));

            if ($reason === '') {
                throw new InvalidArgumentException('Indica el motivo del cambio de relacion laboral.');
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
                    'center_id' => $center->id,
                    'position_name' => $positionName,
                    'started_at' => $startedAt,
                ],
            ];

            $relationship->forceFill([
                'center_id' => $center->id,
                'position_name' => $positionName,
                'started_at' => $startedAt,
                'metadata' => $metadata,
            ])->save();
        }

        $relationship->fill([
            'status' => $data['status'] ?? $relationship->status,
            'metadata' => $data['metadata'] ?? $relationship->metadata ?? [],
        ]);

        $relationship->save();

        return $relationship->refresh();
    }
}
