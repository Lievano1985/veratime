<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\Schedule;
use InvalidArgumentException;

class UpdateScheduleAction
{
    public function handle(Company $company, Schedule $schedule, array $data): Schedule
    {
        if ($schedule->company_id !== $company->id) {
            throw new InvalidArgumentException('Schedule must belong to the active company.');
        }

        $schedule->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'legal_type' => $data['legal_type'],
            'timezone' => $data['timezone'] ?? null,
            'status' => $data['status'] ?? $schedule->status,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'metadata' => $data['metadata'] ?? $schedule->metadata ?? [],
        ]);

        return $schedule->refresh();
    }
}
