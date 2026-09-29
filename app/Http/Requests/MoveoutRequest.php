<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Give notice (store) or update the move-out dates (update). Owner/Manager; route middleware checks the role. */
class MoveoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->route('moveout') === null;

        return array_filter([
            'lease_id' => $creating ? ['required', Rule::exists('tbl_lease', 'lease_id')->where('status', 'Active')] : null,
            'notice_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'planned_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:notice_date'],
            'actual_date' => $creating ? null : ['nullable', 'date_format:Y-m-d', 'after_or_equal:notice_date', 'before_or_equal:today'],
        ]);
    }

    public function messages(): array
    {
        return [
            'notice_date.before_or_equal' => 'The notice date cannot be in the future.',
            'planned_date.after_or_equal' => 'The planned move-out date cannot be before the notice.',
            'actual_date.after_or_equal' => 'The move-out date cannot be before the notice.',
            'actual_date.before_or_equal' => 'The move-out date cannot be in the future.',
        ];
    }
}
