<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AttendancePeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAttendancePeriodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['center_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(AttendancePeriod::STATUSES)], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
