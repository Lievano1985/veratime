<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\DailyScheduleAssignment;
use App\Models\ScheduleBatch;
use Illuminate\Support\Facades\DB;

class RemoveDraftDailyScheduleAssignmentAction
{
    public function __construct(private ValidateScheduleBatchAction $batchValidator)
    {
    }

    public function handle(DailyScheduleAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $lockedAssignment = DailyScheduleAssignment::query()
                ->with('scheduleBatch')
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            $lockedBatch = ScheduleBatch::query()->lockForUpdate()->findOrFail($lockedAssignment->schedule_batch_id);
            $this->batchValidator->assertDraft($lockedBatch);

            $lockedAssignment->segments()->delete();
            $lockedAssignment->delete();
        });
    }
}
