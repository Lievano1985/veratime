<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Alert */
class AlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->alertType?->code,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity,
            'status' => $this->status,
            'rule_code' => $this->rule_code,
            'detected_at' => $this->detected_at?->toISOString(),
            'worker' => $this->worker ? [
                'id' => (string) $this->worker->id,
                'employee_code' => $this->worker->employee_code,
                'full_name' => $this->worker->full_name,
            ] : null,
            'work_day' => $this->workDay ? [
                'id' => (string) $this->workDay->id,
                'work_date' => $this->workDay->work_date?->toDateString(),
                'center' => $this->workDay->center ? [
                    'id' => (string) $this->workDay->center->id,
                    'name' => $this->workDay->center->name,
                ] : null,
            ] : null,
            'resolution' => $this->resolution,
            'resolved_at' => $this->resolved_at?->toISOString(),
        ];
    }
}
