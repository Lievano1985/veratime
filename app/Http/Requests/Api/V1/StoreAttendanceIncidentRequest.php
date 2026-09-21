<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AttendanceIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'incident_type' => ['required', Rule::in(AttendanceIncident::types())],
            'payment_status' => ['required', Rule::in(AttendanceIncident::paymentStatuses())],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
