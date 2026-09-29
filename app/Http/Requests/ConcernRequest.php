<?php

namespace App\Http\Requests;

use App\Models\Concern;
use App\Models\Lease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Log or update a concern (every role; money fields are ignored for the Caretaker by ConcernService). */
class ConcernRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => trim((string) $this->input('description')),
            'charged_to_tenant' => $this->boolean('charged_to_tenant'),
            'remove_photo' => $this->boolean('remove_photo'),
            'cost' => $this->input('cost') === '' ? null : $this->input('cost'),
        ]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(Concern::CATEGORIES)],
            // A room is required except for a shared-area concern (decision 15).
            'room_id' => ['nullable', 'required_unless:category,Shared area', Rule::exists('tbl_room', 'room_id')],
            'lease_id' => ['nullable', Rule::exists('tbl_lease', 'lease_id')],
            'reported_at' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'channel' => ['required', Rule::in(Concern::CHANNELS)],
            'priority' => ['required', Rule::in(Concern::PRIORITIES)],
            'description' => ['required', 'string', 'max:500'],
            'assigned_to' => ['required', Rule::exists('tbl_user', 'user_id')->where('is_active', true)],
            'status' => ['required', Rule::in(Concern::STATUSES)],
            'resolution' => ['nullable', 'required_if:status,Resolved', 'string', 'max:500'],
            'resolved_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'before_or_equal:now', 'after_or_equal:reported_at'],
            'cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'charged_to_tenant' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['boolean'],
        ];
    }

    /** The lease must belong to the room; charging needs a lease and a cost (checked for Owner/Manager only). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $lease = $this->input('lease_id') ? Lease::find($this->input('lease_id')) : null;

                if ($lease && (int) $lease->room_id !== (int) $this->input('room_id')) {
                    $validator->errors()->add('lease_id', 'This tenant does not rent the chosen room.');
                }
                if ($this->user()->hasRole('Owner', 'Manager') && $this->boolean('charged_to_tenant')) {
                    if (! $lease) {
                        $validator->errors()->add('charged_to_tenant', 'Only a concern linked to a tenant (lease) can be charged to the tenant.');
                    }
                    if ((float) $this->input('cost') <= 0) {
                        $validator->errors()->add('cost', 'Enter the cost to charge to the tenant.');
                    }
                }
            },
        ];
    }

    /** Fields saved on tbl_concern (photo is handled separately). */
    public function concernData(): array
    {
        return $this->safe()->except(['photo', 'remove_photo']);
    }

    public function messages(): array
    {
        return [
            'room_id.required_unless' => 'Choose the room (only a shared-area concern can have no room).',
            'resolution.required_if' => 'Describe how the concern was resolved.',
            'reported_at.before_or_equal' => 'The report time cannot be in the future.',
            'resolved_at.after_or_equal' => 'It cannot be resolved before it was reported.',
            'photo.max' => 'The photo must be 5 MB or smaller.',
            'photo.mimes' => 'Use a JPG, PNG or WebP photo.',
        ];
    }

    public function attributes(): array
    {
        return ['room_id' => 'room', 'lease_id' => 'tenant', 'assigned_to' => 'assigned user', 'reported_at' => 'reported on'];
    }
}
