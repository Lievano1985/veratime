<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TimeEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTimeEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['nullable', 'integer'],
            'employee_code' => ['nullable', 'string', 'max:50'],
            'center_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'event_type' => ['nullable', Rule::in(TimeEvent::EVENT_TYPES)],
            'source' => ['nullable', Rule::in(TimeEvent::SOURCES)],
            'status' => ['nullable', Rule::in(TimeEvent::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
