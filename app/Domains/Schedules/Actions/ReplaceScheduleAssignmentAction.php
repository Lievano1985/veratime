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

class ReplaceScheduleAssignmentAction
{
    public function __construct(private CreateScheduleAssignmentAction $createAction)
    {
    }

    public function handle(
        Company $company,
        Worker $worker,
        Schedule $schedule,
        ?EmploymentRelationship $employmentRelationship,
        array $data
    ): ScheduleAssignment {
        if (blank($data['effective_from'] ?? null)) {
            throw new InvalidArgumentException('Assignment effective_from is required.');
        }

        $effectiveFrom = CarbonImmutable::parse($data['effective_from'])->startOfDay();

        return DB::transaction(function () use ($company, $worker, $schedule, $employmentRelationship, $data, $effectiveFrom): ScheduleAssignment {
            $activeAssignments = ScheduleAssignment::query()
                ->where('company_id', $company->id)
                ->where('worker_id', $worker->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->orderBy('effective_from')
                ->get();

            foreach ($activeAssignments as $assignment) {
                if ($assignment->effective_from->gte($effectiveFrom)) {
                    throw new InvalidArgumentException('New assignment must start after the current active assignment.');
                }

                if ($assignment->effective_to && $assignment->effective_to->lt($effectiveFrom)) {
                    continue;
                }

                $assignment->forceFill([
                    'effective_to' => $effectiveFrom->subDay()->toDateString(),
                    'status' => 'replaced',
                ])->save();
            }

            return $this->createAction->handle($company, $worker, $schedule, $employmentRelationship, [
                ...$data,
                'status' => 'active',
            ]);
        });
    }
}
