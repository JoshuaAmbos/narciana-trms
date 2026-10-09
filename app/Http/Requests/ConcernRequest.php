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
        // The Room list's "No room (shared area)" choice sends room_id=shared.
        $shared = $this->input('room_id') === 'shared';

        $this->merge([
            'shared' => $shared,
            'room_id' => $shared ? null : $this->input('room_id'),
            'description' => trim((string) $this->input('description')),
            'resolution' => $this->input('resolution') !== null ? trim((string) $this->input('resolution')) : null,
            'resolved_at' => $this->input('resolved_at') === '' ? null : $this->input('resolved_at'),
            'charged_to_tenant' => $this->boolean('charged_to_tenant'),
            'remove_photo' => $this->boolean('remove_photo'),
            'remove_resolution_photo' => $this->boolean('remove_resolution_photo'),
            'cost' => $this->input('cost') === '' ? null : $this->input('cost'),
        ]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(Concern::CATEGORIES))],
            // A room, or "No room (shared area)" for any category (decision 18).
            'shared' => ['boolean'],
            'room_id' => ['nullable', 'required_unless:shared,true', Rule::exists('tbl_room', 'room_id')],
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
            // Proof of the problem / proof it was fixed: JPG or PNG, max 5 MB (decision 18).
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:5120'],
            'remove_photo' => ['boolean'],
            'resolution_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:5120'],
            'remove_resolution_photo' => ['boolean'],
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

                if ($this->input('category') === Concern::SHARED_CATEGORY && $this->input('room_id')) {
                    $validator->errors()->add('room_id', 'Cleanliness is for shared areas: choose "No room (shared area)" and say where in the description.');
                }
                if ($this->hasFile('resolution_photo') && $this->input('status') !== 'Resolved') {
                    $validator->errors()->add('resolution_photo', 'Add the "fixed" photo when you mark the concern Resolved.');
                }
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

    /** Fields saved on tbl_concern (the photos are handled separately). */
    public function concernData(): array
    {
        return $this->safe()->except(['shared', 'photo', 'remove_photo', 'resolution_photo', 'remove_resolution_photo']);
    }

    public function messages(): array
    {
        return [
            'room_id.required_unless' => 'Choose the room, or "No room (shared area)".',
            'resolution.required_if' => 'Describe how the concern was resolved.',
            'reported_at.before_or_equal' => 'The report time cannot be in the future.',
            'resolved_at.after_or_equal' => 'It cannot be resolved before it was reported.',
            'photo.max' => 'The photo must be 5 MB or smaller.',
            'resolution_photo.max' => 'The photo must be 5 MB or smaller.',
            'photo.mimes' => 'Use a JPG or PNG photo.',
            'photo.mimetypes' => 'Use a JPG or PNG photo.',
            'resolution_photo.mimes' => 'Use a JPG or PNG photo.',
            'resolution_photo.mimetypes' => 'Use a JPG or PNG photo.',
        ];
    }

    public function attributes(): array
    {
        return ['room_id' => 'room', 'lease_id' => 'tenant', 'assigned_to' => 'assigned user', 'reported_at' => 'reported on'];
    }
}
