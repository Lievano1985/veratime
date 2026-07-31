<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReplacePrimaryOrganizationalUnitAction
{
    public function __construct(private AssignPrimaryOrganizationalUnitAction $assignAction)
    {
    }

    public function handle(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, array $data): EmploymentUnitAssignment
    {
        if (blank($data['effective_from'] ?? null)) {
            $data['effective_from'] = $relationship->started_at?->toDateString() ?? now()->toDateString();
        }

        $data['effective_to'] = null;

        [$from, $to] = $this->assignAction->dates($data);
        $this->assignAction->assertValid($company, $relationship, $unit, $from, $to, $data);

        return DB::transaction(function () use ($company, $relationship, $unit, $data, $from, $to): EmploymentUnitAssignment {
            $assignment = EmploymentUnitAssignment::query()
                ->where('company_id', $company->id)
                ->where('employment_relationship_id', $relationship->id)
                ->where('assignment_type', 'primary')
                ->where('status', 'active')
                ->lockForUpdate()
                ->latest('updated_at')
                ->first();

            if (! $assignment) {
                return $this->assignAction->create($company, $relationship, $unit, 'primary', $data, $from, $to);
            }

            $this->correctAssignmentInPlace($assignment, $unit, $data);

            return $assignment->refresh();
        });
    }

    private function correctAssignmentInPlace(
        EmploymentUnitAssignment $assignment,
        OrganizationalUnit $unit,
        array $data,
    ): void {
        if (blank($data['reason'] ?? null)) {
            throw new InvalidArgumentException('Indica el motivo para corregir la asignacion organizacional.');
        }

        $metadata = $assignment->metadata ?? [];
        $metadata['administrative_corrections'] ??= [];
        $metadata['administrative_corrections'][] = [
            'reason' => $data['reason'],
            'actor_user_id' => $data['created_by'] ?? null,
            'corrected_at' => now()->toISOString(),
            'previous' => [
                'organizational_unit_id' => $assignment->organizational_unit_id,
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_to' => $assignment->effective_to?->toDateString(),
            ],
            'new' => [
                'organizational_unit_id' => $unit->id,
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_to' => null,
            ],
            'note' => 'Administrative segmentation correction only; worker validity and published schedules remain unchanged.',
        ];

        $assignment->forceFill([
            'organizational_unit_id' => $unit->id,
            'effective_to' => null,
            'reason' => $data['reason'],
            'metadata' => $metadata,
        ])->save();
    }
}
