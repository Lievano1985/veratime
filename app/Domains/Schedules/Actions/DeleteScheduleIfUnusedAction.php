<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteScheduleIfUnusedAction
{
    public function handle(Company $company, Schedule $schedule): void
    {
        if ($schedule->company_id !== $company->id) {
            throw new InvalidArgumentException('El horario debe pertenecer a la empresa activa.');
        }

        DB::transaction(function () use ($company, $schedule): void {
            $lockedSchedule = Schedule::query()
                ->where('company_id', $company->id)
                ->whereKey($schedule->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSchedule->assignments()->exists()) {
                throw new InvalidArgumentException('No se puede eliminar el horario porque ya tiene asignaciones de trabajadores. Puedes inactivarlo.');
            }

            $lockedSchedule->delete();
        });
    }
}
