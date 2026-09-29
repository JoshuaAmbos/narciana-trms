<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** One inspection item (damaged or missing item) with its cost. */
class MoveoutItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['description' => trim((string) $this->input('description'))]);
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
        ];
    }
}
