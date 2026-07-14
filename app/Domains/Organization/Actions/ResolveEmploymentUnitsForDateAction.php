<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ResolveEmploymentUnitsForDateAction
{
    public function handle(Company $company, EmploymentRelationship $relationship, string $date): array
    {
        if ($relationship->company_id !== $company->id) {
            throw new InvalidArgumentException('La relacion laboral debe pertenecer a la empresa activa.');
        }

        $date = CarbonImmutable::parse($date)->toDateString();

        $assignments = $relationship->employmentUnitAssignments()
            ->with('organizationalUnit')
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->get();

        return [
            'date' => $date,
            'center' => $relationship->center,
            'primary' => $assignments->firstWhere('assignment_type', 'primary')?->organizationalUnit,
            'temporary_supports' => $assignments
                ->where('assignment_type', 'temporary_support')
                ->pluck('organizationalUnit')
                ->filter()
                ->values(),
        ];
    }
}