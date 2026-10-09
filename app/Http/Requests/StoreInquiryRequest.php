<?php

namespace App\Http\Requests;

use App\Models\Inquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates intake for new inquiries logged by any staff member (Owner, Manager, Caretaker).
 * Room pre-assignment is strictly excluded on initial intake.
 */
class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $contact = preg_replace('/[^\d+]/', '', (string) $this->input('contact_no'));

        $this->merge([
            'prospect_name' => trim((string) $this->input('prospect_name')),
            'contact_no' => $contact,
            'via_representative' => $this->boolean('via_representative'),
            'wants_contact' => $this->boolean('wants_contact'),
            'status' => 'Waiting',
            'inquiry_date' => $this->input('inquiry_date', today()->toDateString()),
            'remarks' => trim((string) $this->input('remarks')),
        ]);
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
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_no.regex' => 'The contact number must be a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).',
            'no_of_occupants.between' => 'A room allows at most 2 occupants (1 or 2 occupants).',
            'inquiry_date.before_or_equal' => 'The inquiry date cannot be in the future.',
        ];
    }

    public function attributes(): array
    {
        return [
            'no_of_occupants' => 'number of occupants',
        ];
    }
}
