<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TimeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimeEvent */
class TimeEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'worker_id' => (string) $this->worker_id,
            'event_type' => $this->event_type,
            'occurred_at' => $this->occurred_at_utc?->toISOString(),
            'timezone' => $this->timezone,
            'status' => $this->status,
            'source' => $this->source,
            'external_id' => $this->external_id,
        ];
    }
}
