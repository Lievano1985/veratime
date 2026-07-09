<?php

namespace App\Domains\Workers\Actions;

use App\Models\Worker;

class TerminateWorkerAction
{
    public function handle(Worker $worker, ?string $endedAt = null): Worker
    {
        $endedAt ??= now()->toDateString();

        $worker->forceFill([
            'status' => 'terminated',
        ])->save();

        $worker->employmentRelationships()
            ->where('status', 'active')
            ->update([
                'status' => 'ended',
                'ended_at' => $endedAt,
                'updated_at' => now(),
            ]);

        return $worker->refresh();
    }
}
