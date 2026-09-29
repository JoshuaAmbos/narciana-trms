<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Encode a new MOA: tenant (new or returning) + room + MOA details + optional co-tenant. */
class StoreLeaseRequest extends LeaseRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'tenant_mode' => $this->input('tenant_mode', 'new'),
            'full_name' => trim((string) $this->input('full_name')),
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
            'full_name' => ['nullable', $new, 'string', 'max:255'],
            'contact_no' => ['nullable', $new, 'string', 'max:15', 'regex:/^[0-9+\- ]+$/'],
            'valid_id_type' => ['nullable', $new, 'string', 'max:50'],
            'valid_id_no' => ['nullable', $new, 'string', 'max:50'],
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
        return $this->input('tenant_mode') === 'existing'
            ? ['tenant_id' => (int) $this->validated('tenant_id')]
            : $this->safe()->only(['full_name', 'contact_no', 'valid_id_type', 'valid_id_no']);
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
            'contact_no.regex' => 'Use only numbers, spaces, + or -.',
            'required_if' => 'The :attribute field is required.',
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'tenant_id' => 'returning tenant', 'full_name' => 'tenant name', 'contact_no' => 'contact number',
            'valid_id_type' => 'valid ID type', 'valid_id_no' => 'valid ID number', 'room_id' => 'room', 'co_tenant_name' => 'co-tenant',
        ];
    }
}
