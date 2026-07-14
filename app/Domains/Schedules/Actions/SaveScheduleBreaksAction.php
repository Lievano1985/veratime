<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\ScheduleBreak;
use App\Models\ScheduleDay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaveScheduleBreaksAction
{
    public function handle(Company $company, ScheduleDay $scheduleDay, array $breaks): Collection
    {
        if ($scheduleDay->company_id !== $company->id || $scheduleDay->schedule?->company_id !== $company->id) {
            throw new InvalidArgumentException('El dia del horario debe pertenecer a la empresa activa.');
        }

        foreach ($breaks as $break) {
            if (array_key_exists('duration_minutes', $break)
                && filled($break['duration_minutes'])
                && (int) $break['duration_minutes'] <= 0) {
                throw new InvalidArgumentException('La duracion de la pausa programada debe ser positiva.');
            }
        }

        return DB::transaction(function () use ($company, $scheduleDay, $breaks): Collection {
            return collect($breaks)->map(function (array $break) use ($company, $scheduleDay): ScheduleBreak {
                $scheduleBreak = null;

                if (! empty($break['id'])) {
                    $scheduleBreak = $scheduleDay->breaks()
                        ->whereKey($break['id'])
                        ->where('company_id', $company->id)
                        ->firstOrFail();
                }

                $payload = [
                    'name' => $break['name'] ?? null,
                    'start_time' => $break['start_time'] ?? null,
                    'end_time' => $break['end_time'] ?? null,
                    'duration_minutes' => $break['duration_minutes'] ?? null,
                    'is_paid' => (bool) ($break['is_paid'] ?? false),
                    'is_required' => (bool) ($break['is_required'] ?? false),
                ];

                if ($scheduleBreak) {
                    $scheduleBreak->update($payload);

                    return $scheduleBreak->refresh();
                }

                $scheduleBreak = new ScheduleBreak($payload);
                $scheduleBreak->company()->associate($company);
                $scheduleBreak->scheduleDay()->associate($scheduleDay);
                $scheduleBreak->save();

                return $scheduleBreak->refresh();
            });
        });
    }
}
