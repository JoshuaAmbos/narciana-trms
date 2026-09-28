<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Owner adds a user account. */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the 'role:Owner' route middleware already checks access
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'full_name' => trim((string) $this->input('full_name')),
            'contact_no' => trim((string) $this->input('contact_no')),
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('tbl_user', 'username')],
            'role' => ['required', Rule::in(User::ROLES)],
            'contact_no' => ['required', 'string', 'max:15', 'regex:/^[0-9+\- ]+$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Use only letters, numbers, dots, dashes or underscores (no spaces).',
            'username.unique' => 'This username is already taken.',
            'contact_no.regex' => 'Use only numbers, spaces, + or -.',
        ];
    }
}
