<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\TimeEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ApproveManualTimeEventAction
{
    public function handle(TimeEvent $timeEvent, User $actor, ?CarbonImmutable $reviewedAt = null): TimeEvent
    {
        return DB::transaction(function () use ($timeEvent, $actor, $reviewedAt): TimeEvent {
            $event = TimeEvent::query()
                ->whereKey($timeEvent->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('approve', $event);

            if ($event->status !== 'pending_review' || $event->source !== 'admin_manual') {
                throw new InvalidArgumentException('Solo se pueden aprobar capturas manuales pendientes de revision.');
            }

            $reviewedAt = ($reviewedAt ?? CarbonImmutable::now('UTC'))->utc();
            $metadata = $event->metadata ?? [];
            $metadata['review'] = [
                'decision' => 'approved',
                'actor_user_id' => $actor->id,
                'reviewed_at' => $reviewedAt->toISOString(),
                'previous_status' => $event->status,
                'resulting_status' => 'valid',
            ];

            $event->forceFill([
                'status' => 'valid',
                'metadata' => $metadata,
            ])->save();

            return $event->refresh();
        });
    }
}
