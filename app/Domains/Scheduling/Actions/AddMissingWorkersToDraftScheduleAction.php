<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AddMissingWorkersToDraftScheduleAction
{
    public function __construct(
        private ResolveScheduleBatchExpectedRelationshipDatesAction $expectedDates,
        private ResolveEmploymentUnitsForDateAction $resolveUnits,
        private ReplaceDraftDailyScheduleAssignmentAction $replaceDay,
        private ValidateScheduleBatchAction $validateBatch,
    ) {}

    public function handle(User $actor, Company $company, ScheduleBatch $batch): int
    {
        return DB::transaction(function () use ($actor, $company, $batch): int {
            $batch = ScheduleBatch::query()->where('company_id', $company->id)->lockForUpdate()->findOrFail($batch->id);
            Gate::forUser($actor)->authorize('update', $batch);
            $this->validateBatch->assertDraft($batch);

            $existing = $batch->dailyAssignments()->distinct()->pluck('employment_relationship_id')->flip();
            $added = 0;
            foreach ($this->expectedDates->handle($company, $batch, true) as ['relationship' => $relationship, 'dates' => $dates]) {
                if ($existing->has($relationship->id)) {
                    continue;
                }

                foreach ($dates as $date) {
                    $unit = $this->resolveUnits->handle($company, $relationship, $date)['primary'];
                    $this->replaceDay->handle($company, $batch, $relationship, [
                        'work_date' => $date,
                        'day_type' => 'unassigned',
                        'organizational_unit_id' => $unit?->id,
                        'source_type' => 'manual',
                    ]);
                }
                $added++;
            }

            return $added;
        });
    }
}
