<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Restore: pick a backup and enter account password to confirm. */
class RestoreBackupRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('backup_file') && $this->has('backup')) {
            $this->merge(['backup_file' => $this->input('backup')]);
        }
        if (! $this->has('backup') && $this->has('backup_file')) {
            $this->merge(['backup' => $this->input('backup_file')]);
        }
    }

    public function authorize(): bool
    {
        return true; // the 'role:Owner' route middleware already checks access
    }

    public function rules(): array
    {
        return [
            'backup_file' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'backup_file.required' => 'Choose the backup to restore.',
            'password.required' => 'Enter your account password to authorize database restoration.',
            'password.current_password' => 'The provided password does not match your account password.',
        ];
    }
}
