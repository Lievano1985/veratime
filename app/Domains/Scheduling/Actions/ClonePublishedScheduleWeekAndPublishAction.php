<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\PublishScheduleBatchResult;
use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClonePublishedScheduleWeekAndPublishAction
{
    public function __construct(
        private ClonePublishedScheduleWeekToDraftAction $cloneToDraft,
        private PublishScheduleBatchAction $publish,
    ) {
    }

    public function handle(User $actor, Company $company, ScheduleBatch $published, string $targetDate): PublishScheduleBatchResult
    {
        return DB::transaction(function () use ($actor, $company, $published, $targetDate): PublishScheduleBatchResult {
            $cloned = $this->cloneToDraft->handle($actor, $company, $published, $targetDate);

            return $this->publish->handle($actor, $company, $cloned['batch']);
        });
    }
}
