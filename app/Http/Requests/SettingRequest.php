<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Owner edits the rates and rules. No negatives; ranges keep typing mistakes (an extra zero) out. */
class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the 'role:Owner' route middleware already checks access
    }

    public function rules(): array
    {
        $money = fn (int $max) => ['required', 'numeric', 'decimal:0,2', 'min:0', "max:{$max}"];

        return [
            'water_rate' => $money(10000),
            'appliance_fee' => $money(10000),
            'kwh_rate' => $money(1000),
            'grace_days' => ['required', 'integer', 'between:0,31'],
            'penalty_basis' => ['required', Rule::in(Setting::PENALTY_BASES)],
            'penalty_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'refund_days' => ['required', 'integer', 'between:0,60'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'This is required.',
            '*.numeric' => 'Enter a number.',
            '*.integer' => 'Enter a whole number of days.',
            '*.decimal' => 'Use at most 2 decimal places.',
            '*.min' => 'This cannot be negative.',
            'water_rate.max' => 'The water rate cannot be more than ₱10,000.00.',
            'appliance_fee.max' => 'The appliance fee cannot be more than ₱10,000.00.',
            'kwh_rate.max' => 'The rate per kWh cannot be more than ₱1,000.00.',
            'penalty_rate.max' => 'The penalty rate cannot be more than 100%.',
            'grace_days.between' => 'Use 0 to 31 days.',
            'refund_days.between' => 'Use 0 to 60 days.',
            'penalty_basis.in' => 'Choose a penalty basis from the list.',
        ];
    }
}
