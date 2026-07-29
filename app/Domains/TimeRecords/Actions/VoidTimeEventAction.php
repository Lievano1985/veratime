<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\TimeEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class VoidTimeEventAction
{
    public function handle(TimeEvent $timeEvent, User $actor, string $reason, ?CarbonImmutable $voidedAt = null): TimeEvent
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('El motivo de anulacion es requerido.');
        }

        return DB::transaction(function () use ($timeEvent, $actor, $reason, $voidedAt): TimeEvent {
            $event = TimeEvent::query()
                ->whereKey($timeEvent->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('void', $event);

            if ($event->isVoided()) {
                throw new InvalidArgumentException('El evento de jornada ya fue anulado.');
            }

            $voidedAt = ($voidedAt ?? CarbonImmutable::now('UTC'))->utc();
            $metadata = $event->metadata ?? [];
            $metadata['void'] = [
                'reason' => $reason,
                'actor_user_id' => $actor->id,
                'voided_at' => $voidedAt->toISOString(),
                'resulting_status' => 'voided',
                'previous_status' => $event->status,
            ];

            $event->forceFill([
                'status' => 'voided',
                'voided_at' => $voidedAt,
                'voided_by_user_id' => $actor->id,
                'void_reason' => $reason,
                'metadata' => $metadata,
            ])->save();

            return $event->refresh();
        });
    }
}
