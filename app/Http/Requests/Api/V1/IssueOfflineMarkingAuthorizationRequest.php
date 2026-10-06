<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IssueOfflineMarkingAuthorizationRequest extends FormRequest
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
            'user_id' => ['prohibited'],
            'policy_id' => ['prohibited'],
            'binding_id' => ['required', 'uuid'],
            'issued_monotonic_milliseconds' => ['required', 'integer', 'min:1'],
        ];
    }
}
