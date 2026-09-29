<?php

namespace App\Http\Requests;

use App\Models\Inquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Any role logs or updates an inquiry. */
class InquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // every logged-in role may log and update inquiries
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'prospect_name' => trim((string) $this->input('prospect_name')),
            'contact_no' => trim((string) $this->input('contact_no')),
            'via_representative' => $this->boolean('via_representative'),
            'status' => $this->input('status', 'Waiting'),
        ]);
    }

    public function rules(): array
    {
        return [
            'prospect_name' => ['required', 'string', 'max:255'],
            'contact_no' => ['required', 'string', 'max:15', 'regex:/^[0-9+\- ]+$/'],
            'preferred_type' => ['required', Rule::in(Inquiry::TYPES)],
            // Above 2 is allowed so requests the house cannot take are still counted for the demand report.
            'no_of_occupants' => ['required', 'integer', 'between:1,10'],
            'channel' => ['required', Rule::in(Inquiry::CHANNELS)],
            'via_representative' => ['boolean'],
            'inquiry_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status' => ['required', Rule::in(Inquiry::STATUSES)],
            'room_id' => ['nullable', 'required_if:status,Rented', Rule::exists('tbl_room', 'room_id')],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_no.regex' => 'Use only numbers, spaces, + or -.',
            'inquiry_date.before_or_equal' => 'The inquiry date cannot be in the future.',
            'room_id.required_if' => 'Choose the room that was rented.',
        ];
    }

    public function attributes(): array
    {
        return ['no_of_occupants' => 'number of occupants', 'room_id' => 'room'];
    }
}
