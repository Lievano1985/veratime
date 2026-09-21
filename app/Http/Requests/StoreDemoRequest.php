<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDemoRequest extends FormRequest
{
    protected $errorBag = 'demoRequest';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'team_size' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'message' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
