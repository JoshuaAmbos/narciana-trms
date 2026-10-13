<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Owner edits a user account (password optional: blank keeps the current one). */
class UpdateUserRequest extends StoreUserRequest
{
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $editingSelf = $this->user()->user_id === $target->user_id;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('tbl_user', 'username')->ignore($target->user_id, 'user_id')],
            // The Owner cannot change their own role (decision 9); the form does not send it for them.
            'role' => $editingSelf ? ['prohibited'] : ['required', Rule::in(User::ROLES)],
            'contact_no' => ['required', 'string', 'max:15', 'regex:/^[0-9+\- ]+$/'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'role.prohibited' => 'You cannot change your own role.',
        ];
    }
}
