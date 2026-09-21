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
