<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Alert;
use App\Models\AlertType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPersonalAlertsRequest extends FormRequest
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
            'center_id' => ['prohibited'],
            'assigned_to' => ['prohibited'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'alert_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                Alert::STATUS_NEW,
                Alert::STATUS_IN_REVIEW,
                Alert::STATUS_PENDING_INFORMATION,
                Alert::STATUS_JUSTIFIED,
                Alert::STATUS_CORRECTED,
                Alert::STATUS_CLOSED,
            ])],
            'severity' => ['nullable', Rule::in([
                AlertType::SEVERITY_INFORMATIONAL,
                AlertType::SEVERITY_WARNING,
                AlertType::SEVERITY_HIGH,
                AlertType::SEVERITY_CRITICAL,
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
