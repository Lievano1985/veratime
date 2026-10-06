<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePersonalTimeEventRequest extends FormRequest
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
            'event_type' => ['required', 'string', 'in:'.implode(',', [
                'clock_in',
                'clock_out',
                'break_start',
                'break_end',
            ])],
            'occurred_at' => ['required', 'date'],
            'timezone' => ['nullable', 'timezone'],
            'metadata' => ['nullable', 'array'],
            'device' => ['nullable', 'array'],
            'device.code' => ['nullable', 'string', 'max:100'],
            'device.name' => ['nullable', 'string', 'max:255'],
            'security' => ['nullable', 'array'],
            'security.company_id' => ['prohibited'],
            'security.worker_id' => ['prohibited'],
            'security.user_id' => ['prohibited'],
            'security.policy_id' => ['nullable', 'uuid'],
            'security.policy_version' => ['nullable', 'integer', 'min:1'],
            'security.binding_id' => ['nullable', 'uuid'],
            'security.time_reference_id' => ['nullable', 'uuid'],
            'security.offline_authorization_id' => ['nullable', 'uuid'],
            'security.monotonic_elapsed_milliseconds' => ['nullable', 'integer', 'min:1'],
            'security.signature' => ['nullable', 'string', 'max:1024'],
            'security.location' => ['nullable', 'array'],
            'security.location.latitude' => ['nullable', 'string', 'regex:/^-?(?:[0-8]?\\d(?:\\.\\d{1,7})?|90(?:\\.0{1,7})?)$/'],
            'security.location.longitude' => ['nullable', 'string', 'regex:/^-?(?:1[0-7]\\d|[0-9]?\\d)(?:\\.\\d{1,7})?$|^-?180(?:\\.0{1,7})?$/'],
            'security.location.accuracy_meters' => ['nullable', 'string', 'regex:/^\\d{1,5}(?:\\.\\d{1,2})?$/'],
            'security.location.captured_at' => ['nullable', 'date'],
            'security.location.is_mocked' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $key = trim((string) $this->header('Idempotency-Key'));

            if ($key === '') {
                $validator->errors()->add('idempotency_key', 'El encabezado Idempotency-Key es requerido.');
            } elseif (mb_strlen($key) > 255) {
                $validator->errors()->add('idempotency_key', 'El encabezado Idempotency-Key no puede exceder 255 caracteres.');
            }
        });
    }
}
