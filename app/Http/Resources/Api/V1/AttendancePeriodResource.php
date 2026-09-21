<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendancePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendancePeriod */
class AttendancePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'name' => $this->name, 'period_start' => $this->period_start?->toDateString(), 'period_end' => $this->period_end?->toDateString(), 'timezone' => $this->timezone, 'status' => $this->status, 'center' => $this->center ? ['id' => (string) $this->center->id, 'name' => $this->center->name] : null, 'validation_summary' => $this->validation_summary, 'report_summary' => $this->report_summary];
    }
}
