<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Worker */
class WorkerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $relationship = $this->activeEmploymentRelationship;
        $center = $relationship?->center;

        return [
            'id' => (string) $this->id,
            'employee_code' => $this->employee_code,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'rfc' => $this->rfc,
            'curp' => $this->curp,
            'status' => $this->status,
            'source' => $this->source,
            'external_id' => $this->external_id,
            'center' => $center ? [
                'id' => (string) $center->id,
                'name' => $center->name,
            ] : null,
            'position_name' => $relationship?->position_name,
            'started_at' => $relationship?->started_at?->toDateString(),
        ];
    }
}
