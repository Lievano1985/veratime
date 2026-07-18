<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use Illuminate\Support\Facades\DB;

class ReplaceDraftDailyScheduleAssignmentAction
{
    public function __construct(
        private ValidateScheduleBatchAction $batchValidator,
        private ValidateDailyScheduleAssignmentAction $assignmentValidator,
    ) {
    }

    public function handle(Company $company, ScheduleBatch $batch, EmploymentRelationship $relationship, array $data, array $segments = []): DailyScheduleAssignment
    {
        $validated = $this->assignmentValidator->validate($company, $batch->loadMissing('center'), $relationship, $data, $segments);
        $validatedSegments = $validated['segments'] ?? [];
        unset($validated['segments']);

        return DB::transaction(function () use ($company, $batch, $relationship, $validated, $validatedSegments): DailyScheduleAssignment {
            $lockedBatch = ScheduleBatch::query()->with('center')->lockForUpdate()->findOrFail($batch->id);
            $this->batchValidator->assertDraft($lockedBatch);

            $existing = DailyScheduleAssignment::query()
                ->where('schedule_batch_id', $lockedBatch->id)
                ->where('employment_relationship_id', $relationship->id)
                ->whereDate('work_date', $validated['work_date'])
                ->lockForUpdate()
                ->first();

            $assignment = $existing ?: new DailyScheduleAssignment();
            if ($existing) {
                $existing->segments()->delete();
            }

            $assignment->fill($validated);
            $assignment->company()->associate($company);
            $assignment->scheduleBatch()->associate($lockedBatch);
            $assignment->employmentRelationship()->associate($relationship);
            $assignment->organizationalUnit()->associate($validated['organizational_unit_id'] ?? null);
            $assignment->shiftTemplate()->associate($validated['shift_template_id'] ?? null);
            $assignment->save();

            foreach ($validatedSegments as $segmentPayload) {
                $segment = new DailyScheduleSegment($segmentPayload);
                $segment->company()->associate($company);
                $segment->dailyScheduleAssignment()->associate($assignment);
                $segment->shiftTemplateSegment()->associate($segmentPayload['shift_template_segment_id'] ?? null);
                $segment->save();
            }

            return $assignment->refresh()->load([
                'scheduleBatch.center',
                'employmentRelationship.worker',
                'employmentRelationship.center',
                'organizationalUnit',
                'shiftTemplate',
                'segments.shiftTemplateSegment',
            ]);
        });
    }
}
