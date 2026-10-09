<?php

namespace App\Http\Requests;

use App\Models\Inquiry;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates updating an inquiry's status and remarks outcome.
 */
class UpdateInquiryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remarks' => trim((string) $this->input('remarks')),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Inquiry::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'required_if:status,Rented', Rule::exists('tbl_room', 'room_id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->input('status') !== 'Rented') {
                    return;
                }

                /** @var Inquiry $inquiry */
                $inquiry = $this->route('inquiry');
                $roomId = $this->input('room_id', $inquiry?->room_id);
                $room = $roomId ? Room::find($roomId) : null;

                if (! $room) {
                    return;
                }

                $occupants = (int) ($inquiry?->no_of_occupants ?? 1);
                $problems = app(RoomAvailabilityService::class)
                    ->rentedProblems($room, $occupants, $inquiry);

                foreach ($problems as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required_if' => 'Choose the room that was rented.',
        ];
    }
}
