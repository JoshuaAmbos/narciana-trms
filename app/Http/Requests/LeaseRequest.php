<?php

namespace App\Http\Requests;

use App\Models\Lease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * MOA details shared by "Encode new MOA" and "Edit MOA" (Owner/Manager; route middleware checks the role).
 * Blank due day = the move-in day snapped to staggered days (10th, 15th, 20th, 25th, 30th). Blank advance = one month's rent (decision 12).
 */
class LeaseRequest extends FormRequest
{
    /** The MOA fields stored in tbl_lease that this form edits. */
    protected const LEASE_FIELDS = [
        'moa_date', 'move_in_date', 'due_day', 'monthly_rent', 'min_stay_months', 'advance_amount',
        'deposit_maintenance', 'deposit_damage', 'deposit_bills', 'deposit_keys',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $moveIn = $this->input('move_in_date');
        if (blank($this->input('due_day')) && $moveIn && strtotime($moveIn)) {
            $day = (int) Carbon::parse($moveIn)->day;
            // Snap to nearest staggered due day: 10, 15, 20, 25, 30
            $snapped = collect(Lease::STAGGERED_DUE_DAYS)
                ->sortBy(fn ($d) => abs($d - $day))
                ->first();
            $this->merge(['due_day' => $snapped]);
        }
        if (blank($this->input('advance_amount')) && is_numeric($this->input('monthly_rent'))) {
            $this->merge(['advance_amount' => $this->input('monthly_rent')]);
        }
    }

    /** Rules for the MOA fields (room and tenant are handled by StoreLeaseRequest). */
    public function rules(): array
    {
        $money = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'];

        return [
            'moa_date' => ['required', 'date_format:Y-m-d'],
            'move_in_date' => ['required', 'date_format:Y-m-d'],
            'due_day' => ['required', 'integer', Rule::in(Lease::STAGGERED_DUE_DAYS)],
            'monthly_rent' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'min_stay_months' => ['required', 'integer', 'min:'.Lease::DEFAULT_MIN_STAY_MONTHS, 'max:60'],
            'advance_amount' => $money,
            'deposit_maintenance' => $money,
            'deposit_damage' => $money,
            'deposit_bills' => $money,
            'deposit_keys' => $money,
        ];
    }

    /** Only the MOA fields, as stored in tbl_lease. */
    public function leaseData(): array
    {
        return $this->safe()->only(self::LEASE_FIELDS);
    }

    public function messages(): array
    {
        return [
            'due_day.in' => 'The contractual due day must be one of the staggered billing days: 10th, 15th, 20th, 25th, or 30th.',
            'min_stay_months.min' => 'The contractual minimum stay must be at least '.Lease::DEFAULT_MIN_STAY_MONTHS.' months per property policy.',
            '*.decimal' => 'Use at most 2 decimal places (centavos).',
        ];
    }

    public function attributes(): array
    {
        return [
            'moa_date' => 'MOA date', 'move_in_date' => 'move-in date', 'min_stay_months' => 'minimum stay',
            'advance_amount' => 'advance', 'deposit_maintenance' => 'maintenance deposit', 'deposit_damage' => 'damage deposit',
            'deposit_bills' => 'bill expenses deposit', 'deposit_keys' => 'door knob and keys deposit',
        ];
    }
}
