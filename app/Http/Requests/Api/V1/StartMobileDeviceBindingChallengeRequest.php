<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StartMobileDeviceBindingChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'authorization_code' => ['required', 'string', 'size:24'],
            'device_name' => ['required', 'string', 'max:120'],
            'company_id' => ['prohibited'], 'worker_id' => ['prohibited'], 'user_id' => ['prohibited'],
            'center_id' => ['prohibited'], 'policy_id' => ['prohibited'], 'binding_id' => ['prohibited'],
        ];
    }
}
