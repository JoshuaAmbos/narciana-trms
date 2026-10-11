<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates Room creation by the Owner.
 */
class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Room::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'room_no' => trim((string) $this->input('room_no')),
            'is_metered' => $this->boolean('is_metered'),
            'is_rentable' => $this->has('is_rentable') ? $this->boolean('is_rentable') : true,
            'disabled_reason' => $this->filled('disabled_reason') ? trim((string) $this->input('disabled_reason')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'room_no' => [
                'required',
                'string',
                'max:10',
                'regex:/^[0-9]+$/',
                'unique:tbl_room,room_no',
            ],
            'room_type' => ['required', Rule::in(Room::TYPES)],
            'is_metered' => ['boolean'],
            'is_rentable' => ['boolean'],
            'disabled_reason' => ['nullable', 'string', 'max:255'],
            'max_occupants' => ['sometimes', 'integer', 'between:1,'.\App\Models\Room::maxCapacity()],
            'monthly_rent' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        $max = \App\Models\Room::maxCapacity();

        return [
            'room_no.required' => 'The room number is required.',
            'room_no.unique' => 'This room number already exists.',
            'room_no.regex' => 'The room number must be a number.',
            'room_no.max' => 'The room number cannot exceed 10 digits.',
            'max_occupants.between' => "A room can have 1 or {$max} occupants at most.",
            'monthly_rent.required' => 'The monthly rent is required.',
            'monthly_rent.numeric' => 'The monthly rent must be a valid money amount (e.g. 3000.00).',
            'monthly_rent.decimal' => 'Use at most 2 decimal places (centavos).',
            'monthly_rent.min' => 'The monthly rent must be more than ₱0.00.',
            'monthly_rent.max' => 'The monthly rent cannot exceed ₱99,999,999.99.',
        ];
    }
}
