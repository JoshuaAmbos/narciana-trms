<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Correct a tenant's profile (Owner/Manager). */
class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['full_name' => trim((string) $this->input('full_name'))]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'contact_no' => ['required', 'string', 'max:15', 'regex:/^[0-9+\- ]+$/'],
            'valid_id_type' => ['required', 'string', 'max:50'],
            'valid_id_no' => ['required', 'string', 'max:50'],
            'provincial_address' => ['required', 'string', 'max:255'],
            'occupation' => ['required', 'string', 'max:100'],
            'prev_building' => ['nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'in:Active,Inactive,Blacklisted'],
        ];
    }

    public function messages(): array
    {
        return ['contact_no.regex' => 'Use only numbers, spaces, + or -.'];
    }

    public function attributes(): array
    {
        return ['prev_building' => 'previous building name'];
    }
}
