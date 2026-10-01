<?php

namespace App\Http\Requests;

use App\Models\Room;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Filters of the report pages (all GET): month, date range, lease, room. Access is checked by the route's role middleware. */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'lease' => ['nullable', 'integer', Rule::exists('tbl_lease', 'lease_id')->whereIn('status', ['Active', 'Ended'])],
            'room' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                if ($value !== 'shared' && ! (ctype_digit((string) $value) && Room::whereKey($value)->exists())) {
                    $fail('Choose a room from the list.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'month.date_format' => 'Choose a month.',
            'from.date_format' => 'Enter a valid date.',
            'to.date_format' => 'Enter a valid date.',
            'to.after_or_equal' => 'The end date cannot be before the start date.',
            'lease.exists' => 'Choose a tenant from the list.',
        ];
    }

    /** A GET form: on an error go back to the same report without the bad filters (no redirect loop). */
    protected function getRedirectUrl(): string
    {
        return url()->current();
    }

    /** First day of the chosen month (default: this month). */
    public function month(): Carbon
    {
        return $this->filled('month') ? Carbon::createFromFormat('Y-m-d', $this->validated('month').'-01')->startOfDay() : today()->startOfMonth();
    }

    public function from(?Carbon $default = null): ?Carbon
    {
        return $this->filled('from') ? Carbon::parse($this->validated('from'))->startOfDay() : $default;
    }

    public function to(?Carbon $default = null): ?Carbon
    {
        return $this->filled('to') ? Carbon::parse($this->validated('to'))->startOfDay() : $default;
    }

    /** Room ID, 'shared' (shared-area concerns) or null (all rooms). */
    public function room(): int|string|null
    {
        $room = $this->validated('room');

        return $room === null ? null : ($room === 'shared' ? 'shared' : (int) $room);
    }
}
