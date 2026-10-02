<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Restore: pick a backup and type RESTORE (capital letters) to confirm. */
class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the 'role:Owner' route middleware already checks access
    }

    public function rules(): array
    {
        return [
            'backup' => ['required', 'string', 'max:60'],
            'confirm' => ['required', 'in:RESTORE'],
        ];
    }

    public function messages(): array
    {
        return [
            'backup.required' => 'Choose the backup to restore.',
            'confirm.required' => 'Type RESTORE to confirm.',
            'confirm.in' => 'Type RESTORE in capital letters to confirm.',
        ];
    }
}
