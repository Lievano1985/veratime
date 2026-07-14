<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\ScheduleAssignment;
use InvalidArgumentException;

class InactivateScheduleAssignmentAction
{
    public function handle(Company $company, ScheduleAssignment $assignment): ScheduleAssignment
    {
        if ($assignment->company_id !== $company->id) {
            throw new InvalidArgumentException('La asignacion de horario debe pertenecer a la empresa activa.');
        }

        $assignment->forceFill([
            'status' => 'inactive',
        ])->save();

        return $assignment->refresh();
    }
}
