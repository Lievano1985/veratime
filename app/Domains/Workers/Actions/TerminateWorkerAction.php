<?php

namespace App\Domains\Workers\Actions;

use App\Models\Worker;
use Illuminate\Support\Facades\DB;

class TerminateWorkerAction
{
    public function handle(Worker $worker, ?string $endedAt = null): Worker
    {
        $endedAt ??= now()->toDateString();

        return DB::transaction(function () use ($worker, $endedAt): Worker {
            $worker->forceFill([
                'status' => 'terminated',
            ])->save();

            $activeRelationships = $worker->employmentRelationships()
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($activeRelationships as $relationship) {
                $relationship->employmentUnitAssignments()
                    ->where('status', 'active')
                    ->where(function ($query) use ($endedAt): void {
                        $query->whereNull('effective_to')
                            ->orWhereDate('effective_to', '>', $endedAt);
                    })
                    ->update([
                        'status' => 'inactive',
                        'effective_to' => $endedAt,
                        'updated_at' => now(),
                    ]);

                $relationship->forceFill([
                    'status' => 'ended',
                    'ended_at' => $endedAt,
                ])->save();
            }

            return $worker->refresh();
        });
    }
}
