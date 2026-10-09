<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Company $company */
        $company = $this->attributes->get('api.company');

        return [
            'employee_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('workers', 'employee_code')
                    ->where('company_id', $company->id)
                    ->ignore($this->route('workerId')),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'curp' => ['nullable', 'string', 'max:30'],
            'center_id' => [
                'required',
                Rule::exists('centers', 'id')
                    ->where('company_id', $company->id)
                    ->where('status', 'active'),
            ],
            'position_name' => ['nullable', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive', 'terminated', 'suspended'])],
            'relationship_change_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
