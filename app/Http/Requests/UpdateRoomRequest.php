<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates Room updates by the Owner.
 */
class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Room|null $room */
        $room = $this->route('room');

        return $room ? ($this->user()?->can('update', $room) ?? false) : false;
    }

    protected function prepareForValidation(): void
    {
        /** @var Room|null $room */
        $room = $this->route('room');

        $this->merge([
            'room_no' => trim((string) $this->input('room_no')),
            'is_metered' => $this->boolean('is_metered'),
            'is_rentable' => $this->has('is_rentable') ? $this->boolean('is_rentable') : ($room?->is_rentable ?? true),
            'disabled_reason' => $this->filled('disabled_reason') ? trim((string) $this->input('disabled_reason')) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Room|null $room */
        $room = $this->route('room');

        return [
            'room_no' => [
                'required',
                'string',
                'max:10',
                'regex:/^[0-9]+$/',
                Rule::unique('tbl_room', 'room_no')->ignore($room?->room_id, 'room_id'),
            ],
            'room_type' => ['required', Rule::in(Room::TYPES)],
            'is_metered' => ['boolean'],
            'is_rentable' => ['boolean'],
            'disabled_reason' => ['nullable', 'string', 'max:255'],
            'max_occupants' => ['required', 'integer', 'between:1,'.max((int) ($room?->max_occupants ?? 1), Room::maxCapacity())],
            'monthly_rent' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Room|null $room */
            $room = $this->route('room');

            if ($room && $room->status === 'Occupied' && ! $this->boolean('is_rentable')) {
                $validator->errors()->add('is_rentable', 'An occupied room cannot be disabled for renting.');
            }
        });
    }

    public function messages(): array
    {
        /** @var Room|null $room */
        $room = $this->route('room');
        $max = max((int) ($room?->max_occupants ?? 1), Room::maxCapacity());

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
