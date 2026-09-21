<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['nullable', 'integer', 'required_without:employee_code'],
            'employee_code' => ['nullable', 'string', 'max:50', 'required_without:worker_id'],
            'event_type' => ['required', Rule::in(['clock_in', 'clock_out', 'break_start', 'break_end'])],
            'occurred_at' => ['required', 'date'],
            'timezone' => ['nullable', 'timezone'],
            'center_id' => ['nullable', 'integer'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'device' => ['nullable', 'array'],
            'device.code' => ['nullable', 'string', 'max:100'],
            'device.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
