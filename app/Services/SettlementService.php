<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Lease;
use App\Models\Moveout;
use App\Models\MoveoutItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move-out settlement (decision 16).
 *
 *   refundable deposit = damage + bills + keys   (maintenance is never refunded)
 *   balance            = refundable − unpaid bills (with penalties as of the move-out date) − damage items
 *   balance >= 0       -> refund = balance, excess = 0
 *   balance <  0       -> refund = 0,       excess = −balance (billed to the tenant)
 *   early departure    -> the deposit is forfeited: refund = 0 (an excess is still owed)
 */
class SettlementService
{
    public function __construct(
        private BillingService $billing,
        private PenaltyService $penalties,
    ) {}

    // ---------- Pure ----------

    /**
     * @return array{refundable: float, balance: float, refund: float, excess: float}
     */
    public function calculate(float $damageDeposit, float $billsDeposit, float $keysDeposit, float $unpaid, float $damage, bool $early): array
    {
        $refundable = round($damageDeposit + $billsDeposit + $keysDeposit, 2);
        $balance = round($refundable - $unpaid - $damage, 2);

        return [
            'refundable' => $refundable,
            'balance' => $balance,
            'refund' => ($balance > 0 && ! $early) ? $balance : 0.0,
            'excess' => $balance < 0 ? round(-$balance, 2) : 0.0,
        ];
    }

    /** Leaving before move-in + minimum stay forfeits the deposit. */
    public function isEarly(Lease $lease, Carbon $leaveDate): bool
    {
        return $leaveDate->lt($lease->move_in_date->copy()->addMonthsNoOverflow($lease->min_stay_months));
    }

    /** The MOA asks for one month's notice: warning text, or null when enough notice was given. */
    public function noticeWarning(Carbon $noticeDate, Carbon $plannedDate): ?string
    {
        if ($plannedDate->gte($noticeDate->copy()->addMonthNoOverflow())) {
            return null;
        }
        $days = (int) $noticeDate->diffInDays($plannedDate, false);

        return "Note: the MOA asks for one month's notice; this notice is only {$days} ".($days === 1 ? 'day' : 'days').'.';
    }

    // ---------- Notice ----------

    public function giveNotice(Lease $lease, string $noticeDate, string $plannedDate, User $by): Moveout
    {
        return DB::transaction(function () use ($lease, $noticeDate, $plannedDate, $by) {
            $lease = Lease::whereKey($lease->lease_id)->lockForUpdate()->firstOrFail();
            if ($lease->status !== 'Active' || $lease->moveout()->exists()) {
                throw ValidationException::withMessages(['planned_date' => 'This lease is not active or already has a move-out notice.']);
            }

            $moveout = Moveout::create([
                'lease_id' => $lease->lease_id,
                'notice_date' => $noticeDate,
                'planned_date' => $plannedDate,
                'early_departure' => $this->isEarly($lease, Carbon::parse($plannedDate)),
                'unpaid_balance' => 0, 'damage_total' => 0, 'refund_amount' => 0, 'excess_amount' => 0,
                'status' => 'Pending',
                'user_id' => $by->user_id,
            ]);
            $lease->room()->update(['status' => 'Vacating', 'expected_vacant_date' => $plannedDate]);
            AuditLog::record('Move-out notice', 'tbl_moveout', $moveout->moveout_id, $by->user_id);

            return $moveout;
        });
    }

    public function canCancelNotice(Moveout $moveout): bool
    {
        return $moveout->isPending() && ! $moveout->actual_date && $moveout->items()->doesntExist() && ! $moveout->final_bill_id;
    }

    public function cancelNotice(Moveout $moveout, User $by): void
    {
        DB::transaction(function () use ($moveout, $by) {
            $moveout = Moveout::whereKey($moveout->moveout_id)->lockForUpdate()->firstOrFail();
            if (! $this->canCancelNotice($moveout)) {
                throw ValidationException::withMessages(['moveout' => 'Only a notice without a move-out date, items or final bill can be cancelled.']);
            }
            $id = $moveout->moveout_id;
            $moveout->lease->room()->update(['status' => 'Occupied', 'expected_vacant_date' => null]);
            $moveout->delete();
            AuditLog::record('Move-out notice cancelled', 'tbl_moveout', $id, $by->user_id);
        });
    }

    /** Update the notice/planned dates and record (or correct) the actual move-out date while Pending. */
    public function updateDates(Moveout $moveout, array $data, User $by): Moveout
    {
        return DB::transaction(function () use ($moveout, $data, $by) {
            $moveout = Moveout::whereKey($moveout->moveout_id)->lockForUpdate()->firstOrFail();
            $this->assertPending($moveout);

            $actual = $data['actual_date'] ?? null;
            if ($moveout->final_bill_id && $actual !== $moveout->actual_date?->toDateString()) {
                throw ValidationException::withMessages(['actual_date' => 'Delete the final bill before changing the move-out date.']);
            }

            $moveout->fill([
                'notice_date' => $data['notice_date'],
                'planned_date' => $data['planned_date'],
                'actual_date' => $actual,
            ]);
            $lease = $moveout->lease;
            $moveout->early_departure = $this->isEarly($lease, Carbon::parse($actual ?? $data['planned_date']));
            $moveout->refund_due_date = $actual ? Carbon::parse($actual)->addDays((int) Setting::current()->refund_days) : null;
            $moveout->save();

            $lease->room()->update(['expected_vacant_date' => $data['planned_date']]);
            AuditLog::record($actual ? 'Move-out recorded' : 'Move-out notice', 'tbl_moveout', $moveout->moveout_id, $by->user_id);

            return $moveout;
        });
    }

    // ---------- Inspection items ----------

    public function addItem(Moveout $moveout, string $description, float $cost): MoveoutItem
    {
        $this->assertPending($moveout);

        return MoveoutItem::create(['moveout_id' => $moveout->moveout_id, 'description' => $description, 'cost' => $cost]);
    }

    public function removeItem(MoveoutItem $item): void
    {
        $this->assertPending($item->moveout);
        $item->delete();
    }

    // ---------- Compute ----------

    /** Is the final bill done (or not needed)? */
    public function finalBillReady(Moveout $moveout): bool
    {
        return $moveout->final_bill_id !== null
            || ($moveout->actual_date && ! $this->billing->finalPeriod($moveout->lease, $moveout->actual_date)
                && $this->billing->regularBillsDone($moveout->lease, $moveout->actual_date));
    }

    /** Unpaid and overdue bills of the lease (not the excess bill), each with its penalty as of the move-out date. */
    public function unpaidBills(Moveout $moveout): Collection
    {
        $date = $moveout->actual_date ?? today();

        return $moveout->lease->bills()
            ->where('status', '!=', 'Paid')
            ->when($moveout->excess_bill_id, fn ($q) => $q->where('bill_id', '!=', $moveout->excess_bill_id))
            ->orderBy('due_date')->get()
            ->map(fn (Bill $b) => ['bill' => $b] + $this->penalties->forBill($b, $date));
    }

    /**
     * Figures for the statement. While Pending: computed live (and saved by compute()).
     * After approval: the approved, saved figures, with the bills that were settled from the deposit
     * (live figures would no longer see them, because approval marked them Paid).
     */
    public function figures(Moveout $moveout): array
    {
        return $moveout->isPending() ? $this->liveFigures($moveout) : $this->approvedFigures($moveout);
    }

    private function approvedFigures(Moveout $moveout): array
    {
        $lease = $moveout->lease;
        $settled = $lease->bills()
            ->where('status', 'Paid')
            ->whereDoesntHave('payments')                       // closed at approval, not paid with money
            ->when($moveout->excess_bill_id, fn ($q) => $q->where('bill_id', '!=', $moveout->excess_bill_id))
            ->orderBy('due_date')->get()
            ->map(fn (Bill $b) => [
                'bill' => $b,
                'penalty' => (float) $b->penalty_amount,
                'total' => (float) $b->total_amount,
                'days_late' => $this->penalties->daysLate($b->due_date, $moveout->actual_date, (int) Setting::current()->grace_days),
            ]);
        $refundable = round((float) $lease->deposit_damage + (float) $lease->deposit_bills + (float) $lease->deposit_keys, 2);

        return [
            'unpaid' => $settled,
            'unpaid_total' => (float) $moveout->unpaid_balance,
            'damage_total' => (float) $moveout->damage_total,
            'early' => (bool) $moveout->early_departure,
            'deposit_total' => $lease->depositTotal(),
            'refundable' => $refundable,
            'balance' => round($refundable - (float) $moveout->unpaid_balance - (float) $moveout->damage_total, 2),
            'refund' => (float) $moveout->refund_amount,
            'excess' => (float) $moveout->excess_amount,
        ];
    }

    private function liveFigures(Moveout $moveout): array
    {
        $lease = $moveout->lease;
        $unpaid = $this->unpaidBills($moveout);
        $unpaidTotal = round($unpaid->sum('total'), 2);
        $damage = round((float) $moveout->items()->sum('cost'), 2);
        $early = $this->isEarly($lease, $moveout->actual_date ?? $moveout->planned_date);

        return [
            'unpaid' => $unpaid,
            'unpaid_total' => $unpaidTotal,
            'damage_total' => $damage,
            'early' => $early,
            'deposit_total' => $lease->depositTotal(),
        ] + $this->calculate((float) $lease->deposit_damage, (float) $lease->deposit_bills, (float) $lease->deposit_keys,
            $unpaidTotal, $damage, $early);
    }

    public function compute(Moveout $moveout, User $by): Moveout
    {
        return DB::transaction(function () use ($moveout, $by) {
            $moveout = Moveout::whereKey($moveout->moveout_id)->lockForUpdate()->firstOrFail();
            $this->assertPending($moveout);
            if (! $moveout->actual_date) {
                throw ValidationException::withMessages(['moveout' => 'Record the actual move-out date first.']);
            }
            if (! $this->finalBillReady($moveout)) {
                throw ValidationException::withMessages(['moveout' => 'Generate the final bill (last month\'s utilities) first.']);
            }

            $f = $this->liveFigures($moveout);
            $moveout->update([
                'early_departure' => $f['early'],
                'unpaid_balance' => $f['unpaid_total'],
                'damage_total' => $f['damage_total'],
                'refund_amount' => $f['refund'],
                'excess_amount' => $f['excess'],
                'refund_due_date' => $moveout->actual_date->copy()->addDays((int) Setting::current()->refund_days),
            ]);
            AuditLog::record('Settlement computed', 'tbl_moveout', $moveout->moveout_id, $by->user_id);

            return $moveout;
        });
    }

    // ---------- Owner approval and settling ----------

    /**
     * Owner approves: recompute, close the unpaid bills as "settled from deposit", then
     * refund > 0 -> Refunded; excess > 0 -> excess bill + Excess billed; both 0 -> Settled.
     */
    public function approve(Moveout $moveout, User $owner): Moveout
    {
        return DB::transaction(function () use ($moveout, $owner) {
            $moveout = $this->compute($moveout, $owner); // same figures as shown, recomputed inside the lock
            $date = $moveout->actual_date;

            foreach ($this->unpaidBills($moveout) as $row) {
                $row['bill']->update(['penalty_amount' => $row['penalty'], 'total_amount' => $row['total'], 'status' => 'Paid']);
                AuditLog::record('Bill settled from deposit', 'tbl_bill', $row['bill']->bill_id, $owner->user_id);
            }

            if ((float) $moveout->excess_amount > 0) {
                $due = $moveout->refund_due_date->max(today());
                $bill = Bill::create([
                    'lease_id' => $moveout->lease_id,
                    'reading_id' => null,
                    'period_start' => $date->copy()->addDay()->toDateString(),
                    'due_date' => $due->toDateString(),
                    'rent_amount' => 0, 'water_amount' => 0, 'electricity_amount' => 0, 'appliance_amount' => 0,
                    'penalty_amount' => 0,
                    'other_charges' => $moveout->excess_amount,
                    'total_amount' => $moveout->excess_amount,
                    'status' => 'Unpaid',
                    'user_id' => $owner->user_id,
                ]);
                AuditLog::record('Bill generated', 'tbl_bill', $bill->bill_id, $owner->user_id);
                $moveout->update(['excess_bill_id' => $bill->bill_id, 'status' => 'Excess billed']);
            } elseif ((float) $moveout->refund_amount > 0) {
                $moveout->update(['status' => 'Refunded']);
            } else {
                $this->complete($moveout, $owner);
            }

            AuditLog::record('Settlement approved', 'tbl_moveout', $moveout->moveout_id, $owner->user_id);

            return $moveout->refresh();
        });
    }

    /** Owner confirms the refund was handed over. */
    public function markRefundReleased(Moveout $moveout, User $owner): Moveout
    {
        return DB::transaction(function () use ($moveout, $owner) {
            $moveout = Moveout::whereKey($moveout->moveout_id)->lockForUpdate()->firstOrFail();
            if ($moveout->status !== 'Refunded') {
                throw ValidationException::withMessages(['moveout' => 'Only an approved refund can be marked as released.']);
            }
            AuditLog::record('Refund released', 'tbl_moveout', $moveout->moveout_id, $owner->user_id);
            $this->complete($moveout, $owner);

            return $moveout->refresh();
        });
    }

    /** Called by PaymentService when a bill is paid: paying the excess bill settles the move-out. */
    public function settleIfExcessPaid(Bill $bill, User $by): void
    {
        $moveout = Moveout::where('excess_bill_id', $bill->bill_id)->where('status', 'Excess billed')->lockForUpdate()->first();
        if ($moveout) {
            $this->complete($moveout, $by);
        }
    }

    /** Settled: lease Ended, room Vacant, expected vacant date cleared. */
    private function complete(Moveout $moveout, User $by): void
    {
        $moveout->update(['status' => 'Settled']);
        $moveout->lease->update(['status' => 'Ended']);
        $moveout->lease->room()->update(['status' => 'Vacant', 'expected_vacant_date' => null]);
        AuditLog::record('Settlement settled', 'tbl_moveout', $moveout->moveout_id, $by->user_id);
    }

    private function assertPending(Moveout $moveout): void
    {
        if (! $moveout->isPending()) {
            throw ValidationException::withMessages(['moveout' => 'This settlement is already approved and can no longer be changed.']);
        }
    }
}
