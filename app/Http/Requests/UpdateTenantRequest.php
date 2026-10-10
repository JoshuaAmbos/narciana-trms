<?php

namespace App\Http\Requests;

class UpdateTenantRequest extends StoreTenantRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['nullable', 'in:Active,Inactive,Blacklisted'],
            'return_to' => ['nullable', 'string'],
        ]);
    }
}
