<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TimeEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPersonalTimeEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['prohibited'],
            'worker_id' => ['prohibited'],
            'employee_code' => ['prohibited'],
            'center_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'event_type' => ['nullable', Rule::in(TimeEvent::EVENT_TYPES)],
            'status' => ['nullable', Rule::in(TimeEvent::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
