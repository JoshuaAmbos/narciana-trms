<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Encode a new MOA: tenant (new or returning) + room + MOA details + optional co-tenant. */
class StoreLeaseRequest extends LeaseRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $contact = preg_replace('/[^\d+]/', '', (string) $this->input('contact_no'));

        $firstName = trim((string) $this->input('first_name'));
        $lastName = trim((string) $this->input('last_name'));
        $fullName = trim((string) $this->input('full_name'));

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
            'tenant_mode' => $this->input('tenant_mode', 'new'),
            'first_name' => $firstName ?: null,
            'middle_name' => $middleName ?: null,
            'last_name' => $lastName ?: null,
            'name_extension' => $nameExtension ?: null,
            'full_name' => $computedFullName ?: ($fullName ?: null),
            'contact_no' => $contact,
            'other_id_type' => trim((string) $this->input('other_id_type')) ?: null,
            'co_tenant_name' => trim((string) $this->input('co_tenant_name')) ?: null,
        ]);
    }

    public function rules(): array
    {
        $new = 'required_if:tenant_mode,new';

        return parent::rules() + [
            'tenant_mode' => ['required', Rule::in(['new', 'existing'])],
            // Returning tenant: must not already have an active lease.
            'tenant_id' => ['nullable', 'required_if:tenant_mode,existing',
                Rule::exists('tbl_tenant', 'tenant_id'),
                Rule::unique('tbl_lease', 'tenant_id')->where('status', 'Active')],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:10'],
            'full_name' => ['nullable', $new, 'string', 'max:255'],
            'contact_no' => ['nullable', $new, 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'valid_id_type' => ['nullable', $new, 'string', 'max:60'],
            'other_id_type' => ['required_if:valid_id_type,Other', 'nullable', 'string', 'max:100'],
            'valid_id_no' => ['nullable', $new, 'string', 'max:50'],
            'provincial_address' => ['nullable', $new, 'string', 'max:255'],
            'occupation' => ['nullable', $new, 'string', 'max:100'],
            'prev_building' => ['nullable', 'string', 'max:150'],
            'room_id' => ['required', Rule::exists('tbl_room', 'room_id')->where('status', 'Vacant')],
            'co_tenant_name' => ['nullable', 'string', 'max:255'],
            // Optional; used only when the room has its own meter.
            'move_in_reading' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }

    public function moveInReading(): ?float
    {
        $value = $this->validated('move_in_reading');

        return $value === null || $value === '' ? null : (float) $value;
    }

    /** ['tenant_id' => id] for a returning tenant, otherwise the new tenant's fields. */
    public function tenantData(): array
    {
        if ($this->input('tenant_mode') === 'existing') {
            return ['tenant_id' => (int) $this->validated('tenant_id')];
        }

        return $this->safe()->only([
            'first_name',
            'middle_name',
            'last_name',
            'name_extension',
            'full_name',
            'contact_no',
            'valid_id_type',
            'other_id_type',
            'valid_id_no',
            'provincial_address',
            'occupation',
        ]);
    }

    public function leaseData(): array
    {
        return parent::leaseData() + ['room_id' => (int) $this->validated('room_id')];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'room_id.exists' => 'Choose a Vacant room. A new MOA can only be made for a Vacant room.',
            'tenant_id.unique' => 'This tenant already has an active lease.',
            'contact_no.regex' => 'The contact number must be a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).',
            'other_id_type.required_if' => 'Please specify the ID type when "Other" is selected.',
            'required_if' => 'The :attribute field is required.',
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'tenant_id' => 'returning tenant',
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
            'room_id' => 'room',
            'co_tenant_name' => 'co-tenant',
        ];
    }
}
