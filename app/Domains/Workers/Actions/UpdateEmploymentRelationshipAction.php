<?php

namespace App\Domains\Workers\Actions;

use App\Models\Center;
use App\Models\EmploymentRelationship;
use InvalidArgumentException;

class UpdateEmploymentRelationshipAction
{
    public function handle(EmploymentRelationship $relationship, Center $center, array $data): EmploymentRelationship
    {
        if ($center->company_id !== $relationship->company_id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa de la relacion laboral.');
        }

        $positionName = $data['position_name'] ?? $relationship->position_name;
        $startedAt = $data['started_at'] ?? $relationship->started_at?->toDateString();

        if ((int) $relationship->center_id !== (int) $center->id
            || (string) ($relationship->position_name ?? '') !== (string) ($positionName ?? '')
            || $relationship->started_at?->toDateString() !== $startedAt) {
            throw new InvalidArgumentException('Los campos historicos de la relacion laboral no se pueden sobrescribir.');
        }

        $relationship->fill([
            'status' => $data['status'] ?? $relationship->status,
            'metadata' => $data['metadata'] ?? $relationship->metadata ?? [],
        ]);

        $relationship->save();

        return $relationship->refresh();
    }
}
