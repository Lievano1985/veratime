<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMobileDeviceBindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['authorization_id' => ['required', 'uuid'], 'public_key_spki' => ['required', 'string', 'max:4096'], 'signature' => ['required', 'string', 'max:1024']];
    }
}
