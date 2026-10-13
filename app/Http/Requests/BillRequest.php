<?php

namespace App\Http\Requests;

use App\Models\Lease;
use App\Models\MeterReading;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Generate the next bill of a lease (Owner/Manager; route middleware checks the role). */
class BillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $lease = Lease::with('room')->find($this->input('lease_id'));

        return [
            'lease_id' => ['required', Rule::exists('tbl_lease', 'lease_id')->where('status', 'Active')],
            'due_date' => ['required', 'date_format:Y-m-d'],
            // Concerns ticked as other charges (checked again in BillingService::generate).
            'concerns' => ['nullable', 'array'],
            'concerns.*' => ['integer', 'distinct'],
            'concerns_submitted' => ['nullable', 'boolean'],
        ] + ($lease?->room->is_metered ? self::readingRules() : []);
    }

    /** Meter reading rules, also used for the "Show statement" preview. */
    public static function readingRules(): array
    {
        return [
            'previous_reading' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'current_reading' => ['required', 'numeric', 'decimal:0,2', 'gte:previous_reading', 'max:99999999.99'],
            'reading_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'source' => ['required', Rule::in(MeterReading::SOURCES)],
        ];
    }

    public static function readingMessages(): array
    {
        return [
            'current_reading.gte' => 'The current reading cannot be lower than the previous reading.',
            'reading_date.before_or_equal' => 'The reading date cannot be in the future.',
            '*.decimal' => 'Use at most 2 decimal places.',
        ];
    }

    public function messages(): array
    {
        return self::readingMessages() + ['lease_id.exists' => 'Only an active lease can be billed.'];
    }

    /** The reading fields, or null for a room without a meter. */
    public function reading(): ?array
    {
        return $this->has('current_reading')
            ? $this->safe()->only(['previous_reading', 'current_reading', 'reading_date', 'source'])
            : null;
    }
}
