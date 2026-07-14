<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\ScheduleAssignment;
use App\Models\Worker;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ResolveScheduleForWorkerDateAction
{
    public function handle(Company $company, Worker $worker, CarbonInterface|string $date): ?ScheduleAssignment
    {
        if ($worker->company_id !== $company->id) {
            throw new InvalidArgumentException('La persona trabajadora debe pertenecer a la empresa activa.');
        }

        $dateString = is_string($date) ? $date : $date->toDateString();

        return ScheduleAssignment::query()
            ->with('schedule')
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->whereIn('status', ['active', 'replaced'])
            ->whereDate('effective_from', '<=', $dateString)
            ->where(function ($query) use ($dateString): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $dateString);
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}
