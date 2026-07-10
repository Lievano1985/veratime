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
            throw new InvalidArgumentException('Schedule assignment must belong to the active company.');
        }

        $assignment->forceFill([
            'status' => 'inactive',
        ])->save();

        return $assignment->refresh();
    }
}
