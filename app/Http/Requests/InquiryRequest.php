<?php

namespace App\Http\Requests;

use App\Models\Inquiry;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Any role logs or updates an inquiry. */
class InquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // every logged-in role may log and update inquiries
    }

    protected function prepareForValidation(): void
    {
        $contact = preg_replace('/[^\d+]/', '', (string) $this->input('contact_no'));

        $data = [
            'prospect_name' => trim((string) $this->input('prospect_name')),
            'contact_no' => $contact,
            'via_representative' => $this->boolean('via_representative'),
            'wants_contact' => $this->boolean('wants_contact'),
            'status' => $this->input('status', 'Waiting'),
        ];

        if ($this->has('room_id')) {
            $data['room_id'] = $this->input('room_id') ?: null;
        } elseif ($inquiry = $this->route('inquiry')) {
            $data['room_id'] = $inquiry->room_id;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'prospect_name' => ['required', 'string', 'max:255'],
            'contact_no' => ['required', 'string', 'max:15', 'regex:/^(09|\+639)\d{9}$/'],
            'preferred_type' => ['required', Rule::in(Inquiry::TYPES)],
            'no_of_occupants' => ['required', 'integer', 'between:1,2'],
            'channel' => ['required', Rule::in(Inquiry::CHANNELS)],
            'via_representative' => ['boolean'],
            'wants_contact' => ['boolean'],
            'inquiry_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status' => ['required', Rule::in(Inquiry::STATUSES)],
            'room_id' => ['nullable', 'required_if:status,Rented', Rule::exists('tbl_room', 'room_id')],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Extra checks when the outcome is Rented (run after the basic rules pass):
     * occupants fit the room, the room is Vacant, and no other Rented inquiry still holds it.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->input('status') !== 'Rented') {
                    return;
                }

                $room = Room::find($this->input('room_id'));
                $problems = app(RoomAvailabilityService::class)
                    ->rentedProblems($room, (int) $this->input('no_of_occupants'), $this->route('inquiry'));

                foreach ($problems as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'contact_no.regex' => 'The contact number must be a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).',
            'no_of_occupants.between' => 'A room allows at most 2 occupants (1 or 2 occupants).',
            'inquiry_date.before_or_equal' => 'The inquiry date cannot be in the future.',
            'room_id.required_if' => 'Choose the room that was rented.',
        ];
    }

    public function attributes(): array
    {
        return ['no_of_occupants' => 'number of occupants', 'room_id' => 'room'];
    }
}
