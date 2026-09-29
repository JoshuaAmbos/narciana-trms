<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The end date of an occupant (date_removed), appliance (date_removed) or visitor (departure_date).
 * It cannot be in the future or before the start date of that record.
 */
class EndDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Same error bag as the panel the form sits in.
        $this->errorBag = match (true) {
            $this->route('visitor') !== null => 'visitor',
            $this->route('appliance') !== null => 'appliance',
            default => 'occupant',
        };
    }

    public function rules(): array
    {
        [$field, $start] = $this->fields();

        return [
            $field => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$start],
        ];
    }

    /** [end field name, start date of the record] */
    public function fields(): array
    {
        if ($visitor = $this->route('visitor')) {
            return ['departure_date', $visitor->arrival_date->toDateString()];
        }
        $record = $this->route('occupant') ?? $this->route('appliance');

        return ['date_removed', $record->date_added->toDateString()];
    }

    public function messages(): array
    {
        return [
            '*.before_or_equal' => 'The date cannot be in the future.',
            '*.after_or_equal' => 'The date cannot be before the record\'s start date.',
        ];
    }
}
