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

        $firstName = trim((string) $this->input('first_name'));
        $middleName = trim((string) $this->input('middle_name'));
        $lastName = trim((string) $this->input('last_name'));

        if ($firstName === '' && $lastName === '' && $this->has('prospect_name')) {
            $rawProspect = trim((string) $this->input('prospect_name'));
            if ($rawProspect !== '') {
                $parts = preg_split('/\s+/', $rawProspect, 2);
                $firstName = $parts[0] ?? '';
                $lastName = $parts[1] ?? ($parts[0] ?? '');
            }
        }

        $fullName = trim("{$firstName} " . ($middleName !== '' ? "{$middleName} " : '') . "{$lastName}");

        $this->merge([
            'first_name' => $firstName,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'last_name' => $lastName,
            'prospect_name' => $fullName,
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
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'prospect_name' => ['nullable', 'string', 'max:255'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('first_name') || $validator->errors()->has('last_name')) {
                if (! $validator->errors()->has('prospect_name')) {
                    $validator->errors()->add('prospect_name', 'The prospect name field is required.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'Names can only contain letters, spaces, hyphens, and periods.',
            'middle_name.regex' => 'Names can only contain letters, spaces, hyphens, and periods.',
            'last_name.regex' => 'Names can only contain letters, spaces, hyphens, and periods.',
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
