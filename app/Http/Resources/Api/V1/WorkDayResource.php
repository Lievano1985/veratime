<?php

namespace App\Http\Resources\Api\V1;

use App\Models\WorkDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkDay */
class WorkDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $calculation = $this->activeCalculation;

        return [
            'id' => (string) $this->id,
            'work_date' => $this->work_date?->toDateString(),
            'status' => $this->status,
            'schedule_status' => $this->schedule_status,
            'worker' => [
                'id' => (string) $this->worker_id,
                'employee_code' => $this->worker?->employee_code,
                'full_name' => $this->worker?->full_name,
            ],
            'center' => $this->center ? [
                'id' => (string) $this->center->id,
                'name' => $this->center->name,
            ] : null,
            'active_calculation' => $calculation ? [
                'id' => (string) $calculation->id,
                'version' => $calculation->version,
                'classification' => $calculation->classification,
                'total_work_minutes' => $calculation->total_work_minutes,
                'ordinary_minutes' => $calculation->ordinary_minutes,
                'overtime_minutes' => $calculation->overtime_minutes,
                'late_arrival_minutes' => $calculation->late_arrival_minutes,
                'early_departure_minutes' => $calculation->early_departure_minutes,
            ] : null,
            'alerts_count' => $this->open_alerts_count ?? 0,
        ];
    }
}
