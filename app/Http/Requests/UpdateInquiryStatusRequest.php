<?php

namespace App\Http\Requests;

use App\Models\Inquiry;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates updating an inquiry's status and remarks outcome.
 */
class UpdateInquiryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        $remarks = trim((string) $this->input('remarks'));
        $declinedReason = trim((string) $this->input('declined_reason'));

        if ($status === 'Declined' && $declinedReason !== '') {
            if ($declinedReason !== 'Other (custom note)') {
                if ($remarks === '') {
                    $remarks = $declinedReason;
                } elseif (! str_starts_with($remarks, $declinedReason)) {
                    $remarks = $declinedReason . ' - ' . $remarks;
                }
            }
        }

        $merge = [
            'remarks' => $remarks !== '' ? mb_substr($remarks, 0, 255) : null,
        ];
        if (! $this->has('room_id') && $inquiry = $this->route('inquiry')) {
            $merge['room_id'] = $inquiry->room_id;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Inquiry::STATUSES)],
            'declined_reason' => ['nullable', 'string', 'max:255', Rule::in(Inquiry::DECLINED_REASONS)],
            'remarks' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', Rule::exists('tbl_room', 'room_id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('status') === 'Declined') {
                    if (empty($this->input('remarks')) && empty($this->input('declined_reason'))) {
                        $validator->errors()->add('remarks', 'Please specify a reason when declining an inquiry.');
                    }
                    return;
                }

                if ($this->input('status') !== 'Rented') {
                    return;
                }

                /** @var Inquiry $inquiry */
                $inquiry = $this->route('inquiry');
                $roomId = $this->input('room_id', $inquiry?->room_id);
                $room = $roomId ? Room::find($roomId) : null;

                if (! $room) {
                    return;
                }

                $occupants = (int) ($inquiry?->no_of_occupants ?? 1);
                $problems = app(RoomAvailabilityService::class)
                    ->rentedProblems($room, $occupants, $inquiry);

                foreach ($problems as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required_if' => 'Choose the room that was rented.',
        ];
    }
}
