<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ShowPersonalMarkingSecurityRequest extends FormRequest
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
            'binding_id' => ['prohibited'],
            'policy_id' => ['prohibited'],
        ];
    }
}
