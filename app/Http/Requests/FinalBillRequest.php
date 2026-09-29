<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Generate the final bill of a move-out (final meter reading for metered rooms + ticked concerns). */
class FinalBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'concerns' => ['nullable', 'array'],
            'concerns.*' => ['integer', 'distinct'],
        ] + ($this->route('moveout')->lease->room->is_metered ? BillRequest::readingRules() : []);
    }

    public function messages(): array
    {
        return BillRequest::readingMessages();
    }

    public function reading(): ?array
    {
        return $this->has('current_reading')
            ? $this->safe()->only(['previous_reading', 'current_reading', 'reading_date', 'source'])
            : null;
    }
}
