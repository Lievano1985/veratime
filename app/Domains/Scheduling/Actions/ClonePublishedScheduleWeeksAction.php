<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ClonePublishedScheduleWeeksAction
{
    public function handle(User $actor, Company $company, ScheduleBatch $source, string $targetDate, int $weeks = 1, bool $publish = false): array
    {
        Gate::forUser($actor)->authorize('create', [ScheduleBatch::class, $company]);
        Gate::forUser($actor)->authorize('view', $source);
        Validator::make(['date' => $targetDate, 'weeks' => $weeks], ['date' => 'required|date_format:Y-m-d', 'weeks' => 'required|integer|between:1,12'])->validate();

        return DB::transaction(function () use ($actor, $company, $source, $targetDate, $weeks, $publish): array {
            $start = CarbonImmutable::parse($targetDate)->startOfWeek();
            $batches = [];
            for ($i = 0; $i < $weeks; $i++) {
                $result = app(ClonePublishedScheduleWeekToDraftAction::class)->handle($actor, $company, $source, $start->addWeeks($i)->toDateString(), allowCorrectedSource: true);
                $batch = $result['batch'];
                if ($publish) {
                    Gate::forUser($actor)->authorize('publish', $batch);
                    $batch = app(PublishScheduleBatchAction::class)->handle($actor, $company, $batch)->scheduleBatch;
                }
                $batches[] = $batch;
            }

            return ['batches' => $batches, 'last_batch' => $batches[array_key_last($batches)], 'published' => $publish];
        });
    }
}
