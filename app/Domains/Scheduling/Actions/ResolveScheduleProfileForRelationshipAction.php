<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleProfileAssignment;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ResolveScheduleProfileForRelationshipAction
{
    public function __construct(private ResolveEmploymentUnitsForDateAction $resolveEmploymentUnits)
    {
    }

    public function handle(Company $company, EmploymentRelationship $relationship, string $date): array
    {
        if ($company->status !== 'active' || $relationship->company_id !== $company->id) {
            throw new InvalidArgumentException('La relacion laboral debe pertenecer a la empresa activa.');
        }

        $date = CarbonImmutable::parse($date)->toDateString();
        $units = $this->resolveEmploymentUnits->handle($company, $relationship, $date);
        $primaryUnit = $units['primary'];

        $assignment = $this->assignmentFor($company, 'employment_relationship', $date, employmentRelationshipId: $relationship->id)
            ?? ($primaryUnit ? $this->assignmentFor($company, 'organizational_unit', $date, organizationalUnitId: $primaryUnit->id) : null)
            ?? $this->assignmentFor($company, 'center', $date, centerId: $relationship->center_id)
            ?? $this->assignmentFor($company, 'company', $date);

        return [
            'date' => $date,
            'schedule_profile' => $assignment?->scheduleProfile,
            'assignment' => $assignment,
            'assignment_scope' => $assignment?->assignment_scope,
            'center' => $relationship->center,
            'organizational_unit' => $primaryUnit,
            'employment_relationship' => $relationship,
        ];
    }

    private function assignmentFor(
        Company $company,
        string $scope,
        string $date,
        ?int $centerId = null,
        ?int $organizationalUnitId = null,
        ?int $employmentRelationshipId = null,
    ): ?ScheduleProfileAssignment {
        return ScheduleProfileAssignment::query()
            ->with('scheduleProfile')
            ->where('company_id', $company->id)
            ->where('assignment_scope', $scope)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->when($scope === 'company', fn ($query) => $query->whereNull('center_id')->whereNull('organizational_unit_id')->whereNull('employment_relationship_id'))
            ->when($scope === 'center', fn ($query) => $query->where('center_id', $centerId))
            ->when($scope === 'organizational_unit', fn ($query) => $query->where('organizational_unit_id', $organizationalUnitId))
            ->when($scope === 'employment_relationship', fn ($query) => $query->where('employment_relationship_id', $employmentRelationshipId))
            ->whereHas('scheduleProfile', fn ($query) => $query->where('status', 'active'))
            ->latest('effective_from')
            ->first();
    }
}
