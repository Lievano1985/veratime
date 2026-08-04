<?php

namespace App\Domains\WorkDays\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\WorkDay;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ResolveWorkDayForRelationshipDateAction
{
    public function handle(Company $company, EmploymentRelationship $relationship, string|CarbonInterface $workDate): ?WorkDay
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('La consulta de jornadas requiere una empresa activa.');
        }

        if ($relationship->company_id !== $company->id) {
            throw new InvalidArgumentException('La relacion laboral debe pertenecer a la empresa activa.');
        }

        $date = $workDate instanceof CarbonInterface
            ? $workDate->toDateString()
            : (string) $workDate;

        return WorkDay::query()
            ->with(['worker', 'employmentRelationship', 'center', 'scheduleBatch', 'dailyScheduleAssignment'])
            ->where('company_id', $company->id)
            ->where('employment_relationship_id', $relationship->id)
            ->whereDate('work_date', $date)
            ->first();
    }
}
