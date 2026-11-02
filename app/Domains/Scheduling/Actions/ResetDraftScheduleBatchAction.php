<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ResetDraftScheduleBatchAction
{
    public function handle(User $actor, Company $company, ScheduleBatch $batch): int
    {
        return DB::transaction(function () use ($actor, $company, $batch): int {
            $batch = ScheduleBatch::query()->where('company_id', $company->id)->lockForUpdate()->findOrFail($batch->id);
            Gate::forUser($actor)->authorize('update', $batch);
            if ($batch->status !== 'draft' || $company->status !== 'active') {
                throw new InvalidArgumentException('Solo se pueden restablecer horarios de un borrador de la empresa activa.');
            }
            $days = $batch->dailyAssignments()->with('employmentRelationship')->lockForUpdate()->get();
            foreach ($days as $day) {
                app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $batch, $day->employmentRelationship, [
                    'work_date' => $day->work_date->toDateString(), 'day_type' => 'unassigned',
                    'shift_template_id' => null, 'organizational_unit_id' => $day->organizational_unit_id,
                    'source_type' => 'manual', 'source_reference' => ['reason' => 'planner_draft_reset', 'reset_by' => $actor->id],
                ]);
            }

            return $days->count();
        });
    }
}
