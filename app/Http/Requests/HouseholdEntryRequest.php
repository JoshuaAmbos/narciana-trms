<?php

namespace App\Http\Requests;

use App\Models\Appliance;
use App\Models\Visitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add an occupant, appliance or visitor to a lease. The route name tells which one.
 * (Access is checked by route middleware and LeasePolicy.)
 */
class HouseholdEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Separate error bags so each panel on the page shows only its own errors.
        $this->errorBag = match ($this->route()->getName()) {
            'leases.occupants.store' => 'occupant',
            'leases.appliances.store' => 'appliance',
            'leases.visitors.store' => 'visitor',
        };

        foreach (['full_name', 'appliance_name', 'visitor_name'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        $pastDate = ['required', 'date_format:Y-m-d', 'before_or_equal:today'];

        return match ($this->route()->getName()) {
            'leases.occupants.store' => [
                'full_name' => ['required', 'string', 'max:255'],
                'date_added' => $pastDate,
            ],
            'leases.appliances.store' => [
                'appliance_name' => ['required', 'string', 'max:100'],
                'source' => ['required', Rule::in(Appliance::SOURCES)],
                'date_added' => $pastDate,
            ],
            'leases.visitors.store' => [
                'visitor_name' => ['required', 'string', 'max:255'],
                'relationship' => ['required', Rule::in(Visitor::RELATIONSHIPS)],
                'arrival_date' => $pastDate,
                'departure_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:arrival_date', 'before_or_equal:today'],
            ],
        };
    }

    public function messages(): array
    {
        return [
            '*.before_or_equal' => 'The date cannot be in the future.',
            'departure_date.after_or_equal' => 'The departure date cannot be before the arrival date.',
        ];
    }
}
