<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AttendanceIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAttendanceIncidentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([AttendanceIncident::STATUS_APPROVED, AttendanceIncident::STATUS_CANCELLED])],
            'incident_type' => ['nullable', Rule::in(AttendanceIncident::types())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
