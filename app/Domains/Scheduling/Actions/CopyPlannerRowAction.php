<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class CopyPlannerRowAction
{
    public function handle(User $actor, Company $company, ScheduleBatch $batch, int $sourceId, array $targetIds, bool $replace = false): array
    {
        Gate::forUser($actor)->authorize('update', $batch);
        Validator::make(['targets' => $targetIds], ['targets' => 'required|array|min:1', 'targets.*' => 'integer|distinct'])->validate();

        return DB::transaction(function () use ($actor, $company, $batch, $sourceId, $targetIds, $replace) {
            $batch = ScheduleBatch::query()->where('company_id', $company->id)->lockForUpdate()->findOrFail($batch->id);
            Gate::forUser($actor)->authorize('update', $batch);
            $people = EmploymentRelationship::query()->where('company_id', $company->id)->where('center_id', $batch->center_id)
                ->whereIn('id', array_unique([$sourceId, ...$targetIds]))->lockForUpdate()->get()->keyBy('id');
            if ($people->count() !== count(array_unique([$sourceId, ...$targetIds]))) {
                throw new InvalidArgumentException('El origen y las personas seleccionadas deben pertenecer al centro de la semana.');
            }
            $sourceDays = $batch->dailyAssignments()->where('employment_relationship_id', $sourceId)->with('segments')->get();
            if ($sourceDays->isEmpty() || $sourceDays->contains('day_type', 'unassigned')) {
                throw new InvalidArgumentException('Completa los días de la fila origen antes de copiarla.');
            }
            $existing = $batch->dailyAssignments()->whereIn('employment_relationship_id', $targetIds)->get()
                ->keyBy(fn ($day) => $day->employment_relationship_id.'|'.$day->work_date->toDateString());
            $changed = $preserved = 0;
            foreach ($targetIds as $targetId) {
                if ((int) $targetId === $sourceId) {
                    continue;
                }
                $target = $people[$targetId];
                foreach ($sourceDays as $day) {
                    $date = $day->work_date->toDateString();
                    $current = $existing->get($targetId.'|'.$date);
                    if (! $target->isEffectiveOn($date) && ! ($batch->previous_batch_id && $current)) {
                        continue;
                    }
                    if ($current && $current->day_type !== 'unassigned' && ! $replace) {
                        $preserved++;

                        continue;
                    }
                    $unit = app(ResolveEmploymentUnitsForDateAction::class)->handle($company, $target, $date)['primary'];
                    $data = array_replace($day->attributesToArray(), [
                        'work_date' => $date, 'source_type' => 'manual',
                        'source_reference' => ['reason' => 'planner_row_copy', 'source_assignment_id' => $day->id],
                        'organizational_unit_id' => $unit?->id,
                    ]);
                    app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $batch, $target, $data,
                        $day->segments->map(fn ($segment) => $segment->attributesToArray())->all());
                    $changed++;
                }
            }

            return compact('changed', 'preserved');
        });
    }
}
