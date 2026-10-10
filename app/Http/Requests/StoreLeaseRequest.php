<?php

namespace App\Http\Requests;

use App\Models\Room;
use App\Models\Tenant;
use App\Services\PaymentService;
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

        $mode = $this->input('payment_mode') ?? $this->input('mode');
        if (blank($mode)) {
            $mode = 'Cash';
        }
        $gcashRef = PaymentService::normalizeRef($this->input('gcash_ref_no'));

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
            'mode' => $mode,
            'payment_mode' => $mode,
            'gcash_ref_no' => $gcashRef,
            'inquiry_id' => $this->input('inquiry_id') ? (int) $this->input('inquiry_id') : null,
            'appliance_name' => trim((string) $this->input('appliance_name')) ?: null,
            'min_stay_months' => ($this->input('min_stay_months') !== null && $this->input('min_stay_months') !== '')
                ? $this->input('min_stay_months')
                : ((($r = Room::find($this->input('room_id'))) && $r->room_type === 'Aircon') ? 6 : 4),
        ]);
    }

    public function rules(): array
    {
        $new = 'required_if:tenant_mode,new';

        $rules = parent::rules();
        $rules['min_stay_months'] = [
            'required',
            'integer',
            function ($attribute, $value, $fail) {
                $room = $this->targetRoom();
                if ($room) {
                    $expected = ($room->room_type === 'Aircon') ? 6 : 4;
                    if ((int) $value !== $expected) {
                        $fail("The minimum stay for a {$room->room_type} room must be {$expected} months.");
                    }
                } elseif ((int) $value < 4) {
                    $fail('The contractual minimum stay must be at least 4 months per property policy.');
                }
            },
        ];

        return $rules + [
            'tenant_mode' => ['required', Rule::in(['new', 'existing'])],
            // Returning tenant: must not already have an active lease, and must not be blacklisted.
            'tenant_id' => [
                'nullable',
                'required_if:tenant_mode,existing',
                Rule::exists('tbl_tenant', 'tenant_id')
                    ->whereNotIn('status', ['Blacklisted']),
                Rule::unique('tbl_lease', 'tenant_id')->where('status', 'Active'),
            ],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:10'],
            'full_name' => ['nullable', $new, 'string', 'max:255'],
            'contact_no' => ['nullable', $new, 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'valid_id_type' => ['nullable', $new, 'string', 'max:60'],
            'other_id_type' => ['required_if:valid_id_type,Other', 'nullable', 'string', 'max:100'],
            'valid_id_no' => [
                'nullable',
                $new,
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if ($this->input('tenant_mode') === 'new') {
                        $type = $this->input('valid_id_type');
                        if ($type && $value && ! Tenant::validateIdFormat($type, $value)) {
                            $fail("The ID number does not match the expected format for {$type}.");
                        }
                    }
                },
            ],
            'provincial_address' => ['nullable', $new, 'string', 'max:255'],
            'occupation' => ['nullable', $new, 'string', 'max:100'],
            'prev_building' => ['nullable', 'string', 'max:150'],
            'room_id' => ['required', Rule::exists('tbl_room', 'room_id')->where('status', 'Vacant')->where('is_rentable', 1)],
            'co_tenant_name' => ['nullable', 'string', 'max:255'],
            // Baseline move-in reading: required for aircon rooms per Aircon MOA Clause 6, nullable for unmetered units.
            'move_in_reading' => [
                $this->isAirconRoom() ? 'required' : 'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:99999999.99',
            ],
            // Upfront settlement payment
            'mode' => ['required', Rule::in(['Cash', 'GCash'])],
            'payment_mode' => ['nullable', Rule::in(['Cash', 'GCash'])],
            'gcash_ref_no' => [
                'nullable',
                'required_if:mode,GCash',
                'regex:/^\d{13}$/',
                Rule::unique('tbl_payment', 'gcash_ref_no'),
            ],
            'inquiry_id' => ['nullable', 'integer', Rule::exists('tbl_inquiry', 'inquiry_id')],
            'appliance_name' => ['nullable', 'string', 'max:100'],
            'declared_appliances' => ['nullable', 'array'],
            'declared_appliances.*.device_type' => ['nullable', 'string', 'max:100'],
            'declared_appliances.*.custom_name' => ['nullable', 'string', 'max:100'],
            'declared_appliances.*.monthly_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rent = (float) $this->input('monthly_rent');
            $advance = (float) $this->input('advance_amount');
            $depositMaintenance = (float) $this->input('deposit_maintenance');
            $depositDamage = (float) $this->input('deposit_damage');
            $depositBills = (float) $this->input('deposit_bills');
            $depositKeys = (float) $this->input('deposit_keys');
            $totalDeposit = round($depositMaintenance + $depositDamage + $depositBills + $depositKeys, 2);

            if ($rent > 0) {
                if (round($advance, 2) !== round($rent, 2)) {
                    $validator->errors()->add('advance_amount', 'The advance rent must be exactly one month\'s rent (₱'.number_format($rent, 2).') per MOA Clause 6.');
                }
                if (abs($totalDeposit - round($rent, 2)) >= 0.005) {
                    $validator->errors()->add('deposit_maintenance', 'The total deposit (₱'.number_format($totalDeposit, 2).') must equal exactly one month\'s rent (₱'.number_format($rent, 2).') per MOA Clause 6.');
                }
            }

            // Prohibited device guardrail for Basic rooms
            if (! $this->isAirconRoom()) {
                $prohibitedKeywords = ['refrigerator', 'fridge', 'television', 'tv', 'air conditioner', 'aircon'];
                $isProhibited = function (?string $name) use ($prohibitedKeywords) {
                    if (! $name) return false;
                    $lower = strtolower(trim($name));
                    foreach ($prohibitedKeywords as $kw) {
                        if ($kw === 'tv') {
                            if (preg_match('/\btv\b/i', $lower)) return true;
                        } elseif (str_contains($lower, $kw)) {
                            return true;
                        }
                    }
                    return false;
                };

                foreach ((array) $this->input('declared_appliances', []) as $idx => $item) {
                    $name = ($item['device_type'] ?? '') === 'Other Surchargeable Appliance'
                        ? ($item['custom_name'] ?? '')
                        : ($item['device_type'] ?? '');
                    if ($isProhibited($name)) {
                        $validator->errors()->add(
                            "declared_appliances.{$idx}.custom_name",
                            'Refrigerators and TVs are strictly prohibited in Basic rooms per house policy. The tenant must transfer to an Aircon/Metered room to use heavy appliances.'
                        );
                    }
                }

                if ($legacy = $this->input('appliance_name')) {
                    if ($isProhibited($legacy)) {
                        $validator->errors()->add(
                            'appliance_name',
                            'Refrigerators and TVs are strictly prohibited in Basic rooms per house policy. The tenant must transfer to an Aircon/Metered room to use heavy appliances.'
                        );
                    }
                }
            }
        });
    }

    public function moveInReading(): ?float
    {
        if (! $this->isAirconRoom()) {
            return null;
        }

        $value = $this->validated('move_in_reading');

        return $value === null || $value === '' ? null : (float) $value;
    }

    public function inquiryId(): ?int
    {
        return $this->validated('inquiry_id') ? (int) $this->validated('inquiry_id') : null;
    }

    public function applianceName(): ?string
    {
        return $this->validated('appliance_name') ?: null;
    }

    public function declaredAppliances(): array
    {
        $list = [];
        $defaultFee = (float) (\App\Models\Setting::current()->appliance_fee ?? 200.00);

        foreach ((array) $this->input('declared_appliances', []) as $item) {
            $type = trim((string) ($item['device_type'] ?? ''));
            $name = $type === 'Other Surchargeable Appliance' ? trim((string) ($item['custom_name'] ?? '')) : $type;
            if ($name !== '') {
                $fee = isset($item['monthly_fee']) && is_numeric($item['monthly_fee']) ? (float) $item['monthly_fee'] : $defaultFee;
                $list[] = [
                    'name' => $name,
                    'monthly_fee' => $fee,
                ];
            }
        }

        if (empty($list) && ($legacy = trim((string) $this->input('appliance_name', '')))) {
            $list[] = [
                'name' => $legacy,
                'monthly_fee' => $defaultFee,
            ];
        }

        return $list;
    }

    public function paymentData(): array
    {
        $mode = $this->input('payment_mode') ?: ($this->validated('mode') ?: 'Cash');

        return [
            'mode' => $mode,
            'gcash_ref_no' => $mode === 'GCash' ? $this->validated('gcash_ref_no') : null,
        ];
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
        $data = parent::leaseData() + ['room_id' => (int) $this->validated('room_id')];
        $room = Room::find($data['room_id']);
        if ($room) {
            $data['min_stay_months'] = ($room->room_type === 'Aircon') ? 6 : 4;
        }

        return $data;
    }

    public function messages(): array
    {
        return parent::messages() + [
            'move_in_reading.required' => 'The baseline move-in meter reading is required for air-conditioned rooms per Aircon MOA Clause 6.',
            'room_id.exists' => 'Choose a Vacant room. A new MOA can only be made for a Vacant room.',
            'tenant_id.exists' => 'The selected returning tenant is not eligible for a lease (not found or blacklisted).',
            'tenant_id.unique' => 'This tenant already has an active lease.',
            'contact_no.regex' => 'The contact number must be a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).',
            'other_id_type.required_if' => 'Please specify the ID type when "Other" is selected.',
            'gcash_ref_no.regex' => 'The GCash reference number must be exactly 13 digits.',
            'gcash_ref_no.unique' => 'This GCash reference number was already used for another payment.',
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
