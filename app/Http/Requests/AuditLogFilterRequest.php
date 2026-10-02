<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters of the Audit Log viewer (GET): user, action type, date range. */
class AuditLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the 'role:Owner' route middleware already checks access
    }

    public function rules(): array
    {
        return [
            'user' => ['nullable', 'integer', Rule::exists('tbl_user', 'user_id')],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
        ];
    }

    public function messages(): array
    {
        return [
            'user.exists' => 'Choose a user from the list.',
            'from.date_format' => 'Enter a valid date.',
            'to.date_format' => 'Enter a valid date.',
            'to.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }
}
