<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SyncPersonalTimeEventsRequest extends FormRequest
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
            'external_id' => ['prohibited'],
            'events' => ['required', 'array', 'min:1', 'max:25'],
            'events.*.client_event_id' => ['required', 'string', 'max:255', 'distinct:strict'],
            'events.*.company_id' => ['prohibited'],
            'events.*.worker_id' => ['prohibited'],
            'events.*.employee_code' => ['prohibited'],
            'events.*.center_id' => ['prohibited'],
            'events.*.user_id' => ['prohibited'],
            'events.*.external_id' => ['prohibited'],
            'events.*.event_type' => ['required', 'string', 'in:clock_in,clock_out,break_start,break_end'],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.timezone' => ['nullable', 'timezone'],
            'events.*.metadata' => ['nullable', 'array'],
            'events.*.device' => ['nullable', 'array'],
            'events.*.device.code' => ['nullable', 'string', 'max:100'],
            'events.*.device.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
