<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmploymentRelationship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmploymentRelationship */
class EmploymentRelationshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'status' => $this->status,
            'position_name' => $this->position_name,
            'started_at' => $this->started_at?->toDateString(),
            'ended_at' => $this->ended_at?->toDateString(),
            'source' => $this->source,
            'external_id' => $this->external_id,
            'center' => $this->center ? [
                'id' => (string) $this->center->id,
                'name' => $this->center->name,
            ] : null,
        ];
    }
}
