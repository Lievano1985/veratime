<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\EmploymentUnitAssignment;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class EndTemporarySupportAction
{
    public function handle(Company $company, EmploymentUnitAssignment $assignment, string $effectiveTo): EmploymentUnitAssignment
    {
        if ($assignment->company_id !== $company->id || $assignment->assignment_type !== 'temporary_support') {
            throw new InvalidArgumentException('El apoyo temporal debe pertenecer a la empresa activa.');
        }

        $to = CarbonImmutable::parse($effectiveTo)->startOfDay();
        if ($to->lt($assignment->effective_from)) {
            throw new InvalidArgumentException('La fecha final del apoyo no puede ser anterior a su inicio.');
        }

        $assignment->forceFill([
            'effective_to' => $to->toDateString(),
            'status' => 'inactive',
        ])->save();

        return $assignment->refresh();
    }
}