<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\Schedule;
use InvalidArgumentException;

class InactivateScheduleAction
{
    public function handle(Company $company, Schedule $schedule): Schedule
    {
        if ($schedule->company_id !== $company->id) {
            throw new InvalidArgumentException('El horario debe pertenecer a la empresa activa.');
        }

        $schedule->forceFill(['status' => 'inactive'])->save();

        return $schedule->refresh();
    }
}
