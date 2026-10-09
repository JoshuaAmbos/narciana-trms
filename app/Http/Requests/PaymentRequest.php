<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Record a payment (Owner/Manager; route middleware checks the role). */
class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount') ?? $this->input('amount_paid');
        $this->merge([
            'gcash_ref_no' => PaymentService::normalizeRef($this->input('gcash_ref_no')),
            'amount' => $amount,
        ]);
    }

    public function rules(): array
    {
        return ['bill_id' => ['required', Rule::exists('tbl_bill', 'bill_id')]] + self::paymentRules();
    }

    /** Also used for the "Show amount" preview. */
    public static function paymentRules(): array
    {
        return [
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'mode' => ['required', Rule::in(Payment::MODES)],
            'gcash_ref_no' => ['nullable', 'required_if:mode,GCash', 'regex:/^\d{13}$/', Rule::unique('tbl_payment', 'gcash_ref_no')],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
        ];
    }

    public static function paymentMessages(): array
    {
        return [
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
            'gcash_ref_no.required_if' => 'Enter the GCash reference number.',
            'gcash_ref_no.regex' => 'The GCash reference number has exactly 13 digits.',
            'gcash_ref_no.unique' => 'This GCash reference number was already used for another payment.',
        ];
    }

    public function messages(): array
    {
        return self::paymentMessages();
    }
}
