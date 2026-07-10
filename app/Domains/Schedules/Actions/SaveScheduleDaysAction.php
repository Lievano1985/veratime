<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\Schedule;
use App\Models\ScheduleDay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaveScheduleDaysAction
{
    public function handle(Company $company, Schedule $schedule, array $days): Collection
    {
        if ($schedule->company_id !== $company->id) {
            throw new InvalidArgumentException('Schedule must belong to the active company.');
        }

        $seenDays = [];

        foreach ($days as $day) {
            $dayOfWeek = (int) ($day['day_of_week'] ?? -1);

            if ($dayOfWeek < 0 || $dayOfWeek > 6 || in_array($dayOfWeek, $seenDays, true)) {
                throw new InvalidArgumentException('Schedule day must be unique and between 0 and 6.');
            }

            $seenDays[] = $dayOfWeek;

            if ((bool) ($day['is_working_day'] ?? false)
                && (blank($day['start_time'] ?? null) || blank($day['end_time'] ?? null))) {
                throw new InvalidArgumentException('Working schedule days require start and end time.');
            }

            if ((bool) ($day['is_working_day'] ?? false)
                && filled($day['start_time'] ?? null)
                && filled($day['end_time'] ?? null)
                && ($day['end_time'] <= $day['start_time'])
                && ! (bool) ($day['crosses_midnight'] ?? false)) {
                throw new InvalidArgumentException('Schedule days ending before start time must cross midnight.');
            }
        }

        return DB::transaction(function () use ($company, $schedule, $days): Collection {
            return collect($days)->map(function (array $day) use ($company, $schedule): ScheduleDay {
                $isWorkingDay = (bool) ($day['is_working_day'] ?? false);

                $scheduleDay = $schedule->days()
                    ->where('day_of_week', (int) $day['day_of_week'])
                    ->first();

                $payload = [
                    'day_of_week' => (int) $day['day_of_week'],
                    'is_working_day' => $isWorkingDay,
                    'start_time' => $isWorkingDay ? ($day['start_time'] ?? null) : null,
                    'end_time' => $isWorkingDay ? ($day['end_time'] ?? null) : null,
                    'crosses_midnight' => $isWorkingDay ? (bool) ($day['crosses_midnight'] ?? false) : false,
                ];

                if ($scheduleDay) {
                    $scheduleDay->update($payload);

                    return $scheduleDay->refresh();
                }

                $scheduleDay = new ScheduleDay($payload);
                $scheduleDay->company()->associate($company);
                $scheduleDay->schedule()->associate($schedule);
                $scheduleDay->save();

                return $scheduleDay->refresh();
            });
        });
    }
}
