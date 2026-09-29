<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payments (decision 14): full payment only, oldest bill first, penalty from the payment date,
 * numbered acknowledgment (ACK-YYYY-NNNN, never reused), Owner-only void.
 */
class PaymentService
{
    public function __construct(private PenaltyService $penalties) {}

    /** Unpaid bills past due date + grace become Overdue (and back, if the grace period was lengthened). */
    public function refreshOverdue(?Carbon $today = null): int
    {
        $grace = (int) Setting::current()->grace_days;
        $lastOnTimeDue = ($today ?? today())->copy()->subDays($grace); // due dates before this are late

        $changed = Bill::where('status', 'Unpaid')->whereDate('due_date', '<', $lastOnTimeDue)->update(['status' => 'Overdue']);
        $changed += Bill::where('status', 'Overdue')->whereDate('due_date', '>=', $lastOnTimeDue)->update(['status' => 'Unpaid']);

        return $changed;
    }

    /** The older unpaid bill of the same lease that must be paid first, if any. */
    public function olderUnpaid(Bill $bill): ?Bill
    {
        return Bill::where('lease_id', $bill->lease_id)
            ->where('status', '!=', 'Paid')
            ->where('due_date', '<', $bill->due_date)
            ->orderBy('due_date')->first();
    }

    public function canPay(Bill $bill): bool
    {
        return $bill->status !== 'Paid' && $this->olderUnpaid($bill) === null;
    }

    /** Remove spaces/dashes from a GCash reference number. */
    public static function normalizeRef(?string $ref): ?string
    {
        $digits = preg_replace('/[\s-]+/', '', (string) $ref);

        return $digits === '' ? null : $digits;
    }

    /**
     * Record a full payment. $data = ['payment_date', 'mode', 'gcash_ref_no', 'amount'].
     */
    public function record(Bill $bill, array $data, User $receiver): Payment
    {
        return DB::transaction(function () use ($bill, $data, $receiver) {
            // Settings row lock = one payment at a time, so two payments can never get the same ACK number.
            Setting::query()->lockForUpdate()->firstOrFail();
            $bill = Bill::whereKey($bill->bill_id)->lockForUpdate()->firstOrFail();

            if ($bill->status === 'Paid') {
                throw ValidationException::withMessages(['bill' => 'This bill is already paid.']);
            }
            if ($older = $this->olderUnpaid($bill)) {
                throw ValidationException::withMessages([
                    'bill' => 'Pay the older bill first (due '.$older->due_date->format('j M Y').').',
                ]);
            }

            $date = Carbon::parse($data['payment_date']);
            $due = $this->penalties->forBill($bill, $date);

            if (round((float) $data['amount'], 2) !== $due['total']) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount must be exactly ₱'.number_format($due['total'], 2).' (no partial payments).',
                ]);
            }

            $ref = $data['mode'] === 'GCash' ? self::normalizeRef($data['gcash_ref_no'] ?? null) : null;
            if ($ref && Payment::where('gcash_ref_no', $ref)->exists()) {
                throw ValidationException::withMessages(['gcash_ref_no' => 'This GCash reference number was already used for another payment.']);
            }

            $payment = Payment::create([
                'bill_id' => $bill->bill_id,
                'amount' => $due['total'],
                'payment_date' => $date->toDateString(),
                'mode' => $data['mode'],
                'gcash_ref_no' => $ref,
                'ack_no' => $this->nextAckNo((int) $date->year),
                'user_id' => $receiver->user_id,
            ]);

            $bill->update([
                'penalty_amount' => $due['penalty'],
                'total_amount' => $due['total'],
                'status' => 'Paid',
            ]);

            AuditLog::record('Payment recorded', 'tbl_payment', $payment->payment_id, $receiver->user_id);

            return $payment;
        });
    }

    /**
     * Next ACK-YYYY-NNNN. Counts numbers still in use AND numbers of voided payments (kept in the audit log),
     * so a number is never issued twice. Call inside the locked transaction of record().
     */
    public function nextAckNo(int $year): string
    {
        $prefix = "ACK-{$year}-";
        $numbers = Payment::where('ack_no', 'like', $prefix.'%')->pluck('ack_no')
            ->merge(AuditLog::where('action', 'like', "Payment voided ({$prefix}%")->pluck('action'));

        $max = $numbers->map(fn (string $s) => preg_match('/ACK-\d{4}-(\d+)/', $s, $m) ? (int) $m[1] : 0)->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Only the newest paid bill of a lease can have its payment voided (bills stay in order). */
    public function canVoid(Payment $payment): bool
    {
        $bill = $payment->bill;

        return $bill->status === 'Paid'
            && ! Bill::where('lease_id', $bill->lease_id)->where('status', 'Paid')->where('due_date', '>', $bill->due_date)->exists();
    }

    public function void(Payment $payment, User $owner): void
    {
        DB::transaction(function () use ($payment, $owner) {
            Setting::query()->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->payment_id)->lockForUpdate()->firstOrFail();

            if (! $this->canVoid($payment)) {
                throw ValidationException::withMessages(['payment' => 'Only the payment of the newest paid bill of this lease can be voided.']);
            }

            $bill = $payment->bill;
            $bill->update([
                'total_amount' => round((float) $bill->total_amount - (float) $bill->penalty_amount, 2),
                'penalty_amount' => 0,
                'status' => 'Unpaid',
            ]);

            $paymentId = $payment->payment_id;
            $ack = $payment->ack_no;
            $payment->delete();

            // The ACK number stays in the audit log so it is never issued again.
            AuditLog::record("Payment voided ({$ack})", 'tbl_payment', $paymentId, $owner->user_id);
        });

        $this->refreshOverdue();
    }
}
