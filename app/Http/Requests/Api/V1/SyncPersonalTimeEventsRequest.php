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
            'events.*.security' => ['nullable', 'array'],
            'events.*.security.company_id' => ['prohibited'],
            'events.*.security.worker_id' => ['prohibited'],
            'events.*.security.user_id' => ['prohibited'],
            'events.*.security.policy_id' => ['nullable', 'uuid'],
            'events.*.security.policy_version' => ['nullable', 'integer', 'min:1'],
            'events.*.security.binding_id' => ['nullable', 'uuid'],
            'events.*.security.time_reference_id' => ['nullable', 'uuid'],
            'events.*.security.offline_authorization_id' => ['nullable', 'uuid'],
            'events.*.security.monotonic_elapsed_milliseconds' => ['nullable', 'integer', 'min:1'],
            'events.*.security.signature' => ['nullable', 'string', 'max:1024'],
            'events.*.security.location' => ['nullable', 'array'],
            'events.*.security.location.latitude' => ['nullable', 'string', 'regex:/^-?(?:[0-8]?\\d(?:\\.\\d{1,7})?|90(?:\\.0{1,7})?)$/'],
            'events.*.security.location.longitude' => ['nullable', 'string', 'regex:/^-?(?:1[0-7]\\d|[0-9]?\\d)(?:\\.\\d{1,7})?$|^-?180(?:\\.0{1,7})?$/'],
            'events.*.security.location.accuracy_meters' => ['nullable', 'string', 'regex:/^\\d{1,5}(?:\\.\\d{1,2})?$/'],
            'events.*.security.location.captured_at' => ['nullable', 'date'],
            'events.*.security.location.is_mocked' => ['nullable', 'boolean'],
        ];
    }
}
