<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\TimeEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class RejectManualTimeEventAction
{
    public function handle(TimeEvent $timeEvent, User $actor, string $reason, ?CarbonImmutable $reviewedAt = null): TimeEvent
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('El motivo de rechazo es requerido.');
        }

        return DB::transaction(function () use ($timeEvent, $actor, $reason, $reviewedAt): TimeEvent {
            $event = TimeEvent::query()
                ->whereKey($timeEvent->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('reject', $event);

            if ($event->status !== 'pending_review' || $event->source !== 'admin_manual') {
                throw new InvalidArgumentException('Solo se pueden rechazar capturas manuales pendientes de revision.');
            }

            $reviewedAt = ($reviewedAt ?? CarbonImmutable::now('UTC'))->utc();
            $metadata = $event->metadata ?? [];
            $metadata['review'] = [
                'decision' => 'rejected',
                'reason' => $reason,
                'actor_user_id' => $actor->id,
                'reviewed_at' => $reviewedAt->toISOString(),
                'previous_status' => $event->status,
                'resulting_status' => 'ignored',
            ];

            $event->forceFill([
                'status' => 'ignored',
                'metadata' => $metadata,
            ])->save();

            return $event->refresh();
        });
    }
}
