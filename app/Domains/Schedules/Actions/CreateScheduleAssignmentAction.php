<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateScheduleAssignmentAction
{
    public function handle(
        Company $company,
        Worker $worker,
        Schedule $schedule,
        ?EmploymentRelationship $employmentRelationship,
        array $data
    ): ScheduleAssignment {
        $this->assertSameCompany($company, $worker, $schedule, $employmentRelationship);

        if (blank($data['effective_from'] ?? null)) {
            throw new InvalidArgumentException('Assignment effective_from is required.');
        }

        $effectiveFrom = CarbonImmutable::parse($data['effective_from'])->startOfDay();
        $effectiveTo = filled($data['effective_to'] ?? null)
            ? CarbonImmutable::parse($data['effective_to'])->startOfDay()
            : null;
        if ($effectiveTo && $effectiveTo->lt($effectiveFrom)) {
            throw new InvalidArgumentException('Assignment effective_to must be after effective_from.');
        }

        if ($this->hasActiveOverlap($company, $worker, $effectiveFrom, $effectiveTo)) {
            throw new InvalidArgumentException('Worker already has an active schedule assignment for this period.');
        }

        return DB::transaction(function () use ($company, $worker, $schedule, $employmentRelationship, $data, $effectiveFrom, $effectiveTo): ScheduleAssignment {
            $assignment = new ScheduleAssignment([
                'worker_id' => $worker->id,
                'employment_relationship_id' => $employmentRelationship?->id,
                'schedule_id' => $schedule->id,
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_to' => $effectiveTo?->toDateString(),
                'status' => 'active',
                'source' => $data['source'] ?? 'web',
                'metadata' => $data['metadata'] ?? [],
            ]);

            $assignment->company()->associate($company);
            $assignment->save();

            return $assignment->refresh();
        });
    }

    private function assertSameCompany(
        Company $company,
        Worker $worker,
        Schedule $schedule,
        ?EmploymentRelationship $employmentRelationship
    ): void {
        if ($worker->company_id !== $company->id || $schedule->company_id !== $company->id) {
            throw new InvalidArgumentException('Worker and schedule must belong to the active company.');
        }

        if ($employmentRelationship
            && ($employmentRelationship->company_id !== $company->id || $employmentRelationship->worker_id !== $worker->id)) {
            throw new InvalidArgumentException('Employment relationship must belong to the worker and active company.');
        }
    }


    private function hasActiveOverlap(
        Company $company,
        Worker $worker,
        CarbonImmutable $effectiveFrom,
        ?CarbonImmutable $effectiveTo
    ): bool {
        $periodEnd = $effectiveTo?->toDateString() ?? '9999-12-31';

        return ScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(function ($query) use ($effectiveFrom): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveFrom->toDateString());
            })
            ->exists();
    }
}
