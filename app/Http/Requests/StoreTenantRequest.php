<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $contact = preg_replace('/[^\d+]/', '', (string) $this->input('contact_no'));

        $firstName = trim((string) $this->input('first_name'));
        $lastName = trim((string) $this->input('last_name'));
        $fullName = trim((string) $this->input('full_name'));

        // Fallback for legacy requests or tests sending full_name
        if ($firstName === '' && $fullName !== '') {
            $parts = preg_split('/\s+/', $fullName, 2);
            $firstName = $parts[0] ?? '';
            $lastName = $parts[1] ?? ($parts[0] ?? '');
        }

        $middleName = trim((string) $this->input('middle_name'));
        $nameExtension = trim((string) $this->input('name_extension'));

        $computedFullName = trim("{$firstName} {$middleName} {$lastName} {$nameExtension}");
        $computedFullName = preg_replace('/\s+/', ' ', $computedFullName);

        $this->merge([
            'first_name' => $firstName ?: null,
            'middle_name' => $middleName ?: null,
            'last_name' => $lastName ?: null,
            'name_extension' => $nameExtension ?: null,
            'full_name' => $computedFullName ?: ($fullName ?: null),
            'contact_no' => $contact,
            'other_id_type' => trim((string) $this->input('other_id_type')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:10'],
            'full_name' => ['required', 'string', 'max:255'],
            'contact_no' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'valid_id_type' => ['required', 'string', 'max:60'],
            'other_id_type' => ['required_if:valid_id_type,Other', 'nullable', 'string', 'max:100'],
            'valid_id_no' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $type = $this->input('valid_id_type');
                    if ($type && $value && ! \App\Models\Tenant::validateIdFormat($type, $value)) {
                        $fail("The ID number does not match the expected format for {$type}.");
                    }
                },
            ],
            'provincial_address' => ['required', 'string', 'max:255'],
            'occupation' => ['required', 'string', 'max:100'],
            'prev_building' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:Active,Inactive,Blacklisted'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_no.regex' => 'The contact number must be a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).',
            'other_id_type.required_if' => 'Please specify the ID type when "Other" is selected.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'middle_name' => 'middle name',
            'last_name' => 'last name',
            'name_extension' => 'name extension',
            'full_name' => 'tenant name',
            'contact_no' => 'contact number',
            'valid_id_type' => 'valid ID type',
            'other_id_type' => 'specified ID type',
            'valid_id_no' => 'valid ID number',
            'provincial_address' => 'provincial address',
            'occupation' => 'occupation',
            'prev_building' => 'previous building name',
        ];
    }
}
