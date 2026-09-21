<?php

namespace App\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListPersonalScheduleRequest extends FormRequest
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
            'employment_relationship_id' => ['prohibited'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $timezone = $this->attributes->get('api.company')?->timezone ?? config('app.timezone');
            $from = CarbonImmutable::parse($this->input('date_from') ?: 'today', $timezone);
            $to = CarbonImmutable::parse($this->input('date_to') ?: $from->addDays(13)->toDateString(), $timezone);

            if ($to->lessThan($from)) {
                $validator->errors()->add('date_to', 'La fecha final debe ser igual o posterior a la fecha inicial.');
            } elseif ($from->diffInDays($to) > 31) {
                $validator->errors()->add('date_to', 'El rango de horario no puede exceder 31 días.');
            }
        });
    }
}
