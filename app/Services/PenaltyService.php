<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Late payment penalty (decisions 1 and 14).
 *
 *   days late = payment date − (due date + grace days), never below 0
 *   "Per day of rent":  rent × rate% × days late
 *   "Flat % of total":  bill total (before penalty) × rate%, once the payment is late at all
 */
class PenaltyService
{
    // ---------- Pure ----------

    public function daysLate(Carbon $dueDate, Carbon $paymentDate, int $graceDays): int
    {
        $lastOnTimeDay = $dueDate->copy()->startOfDay()->addDays($graceDays);

        return max(0, (int) $lastOnTimeDay->diffInDays($paymentDate->copy()->startOfDay(), false));
    }

    public function amount(string $basis, float $ratePercent, float $rent, float $billTotal, int $daysLate): float
    {
        if ($daysLate <= 0) {
            return 0.0;
        }

        return $basis === 'Flat % of total'
            ? round($billTotal * $ratePercent / 100, 2)
            : round($rent * $ratePercent / 100 * $daysLate, 2);
    }

    // ---------- For a bill, with the current Settings ----------

    /**
     * Penalty if the bill were paid on $date: ['days_late', 'penalty', 'total'] where total = bill total without any
     * earlier penalty + this penalty.
     */
    public function forBill(Bill $bill, Carbon $date): array
    {
        $settings = Setting::current();
        $base = round((float) $bill->total_amount - (float) $bill->penalty_amount, 2);
        $days = $this->daysLate($bill->due_date, $date, (int) $settings->grace_days);
        $penalty = $this->amount($settings->penalty_basis, (float) $settings->penalty_rate, (float) $bill->rent_amount, $base, $days);

        return ['days_late' => $days, 'penalty' => $penalty, 'total' => round($base + $penalty, 2)];
    }

    public function isOverdue(Bill $bill, ?Carbon $today = null): bool
    {
        return $bill->status !== 'Paid'
            && $this->daysLate($bill->due_date, $today ?? today(), (int) Setting::current()->grace_days) > 0;
    }
}
