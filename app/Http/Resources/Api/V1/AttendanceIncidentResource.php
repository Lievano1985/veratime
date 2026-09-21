<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceIncident */
class AttendanceIncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'worker' => $this->worker ? ['id' => (string) $this->worker->id, 'employee_code' => $this->worker->employee_code, 'full_name' => $this->worker->full_name] : null,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'incident_type' => $this->incident_type,
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
        ];
    }
}
