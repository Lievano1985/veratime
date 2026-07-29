<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReplacePrimaryOrganizationalUnitAction
{
    public function __construct(private AssignPrimaryOrganizationalUnitAction $assignAction)
    {
    }

    public function handle(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, array $data): EmploymentUnitAssignment
    {
        [$from, $to] = $this->assignAction->dates($data);
        $this->assignAction->assertValid($company, $relationship, $unit, $from, $to);

        return DB::transaction(function () use ($company, $relationship, $unit, $data, $from, $to): EmploymentUnitAssignment {
            $active = EmploymentUnitAssignment::query()
                ->where('company_id', $company->id)
                ->where('employment_relationship_id', $relationship->id)
                ->where('assignment_type', 'primary')
                ->where('status', 'active')
                ->lockForUpdate()
                ->orderBy('effective_from')
                ->get();

            foreach ($active as $assignment) {
                if ($assignment->effective_from->gte($from)) {
                    $this->correctAssignmentInPlace($assignment, $unit, $data, $from, $to);

                    return $assignment->refresh();
                }

                if ($assignment->effective_to && $assignment->effective_to->lt($from)) {
                    continue;
                }

                $assignment->forceFill([
                    'effective_to' => $from->subDay()->toDateString(),
                    'status' => 'replaced',
                ])->save();
            }

            $new = $this->assignAction->create($company, $relationship, $unit, 'primary', $data, $from, $to);

            foreach ($active as $assignment) {
                if ($assignment->status === 'replaced') {
                    $assignment->forceFill(['replaced_by_id' => $new->id])->save();
                }
            }

            return $new->refresh();
        });
    }

    private function correctAssignmentInPlace(
        EmploymentUnitAssignment $assignment,
        OrganizationalUnit $unit,
        array $data,
        CarbonImmutable $from,
        ?CarbonImmutable $to,
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
                'effective_from' => $from->toDateString(),
                'effective_to' => $to?->toDateString(),
            ],
            'note' => 'Administrative correction only; published schedules remain unchanged.',
        ];

        $assignment->forceFill([
            'organizational_unit_id' => $unit->id,
            'effective_from' => $from->toDateString(),
            'effective_to' => $to?->toDateString(),
            'reason' => $data['reason'],
            'metadata' => $metadata,
        ])->save();
    }
}
