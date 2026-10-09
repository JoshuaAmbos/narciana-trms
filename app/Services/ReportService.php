<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Concern;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data for the printable reports (Module 9a). Reads only (apart from refreshing the Overdue status);
 * amounts come from saved bills and payments, penalties "so far" from PenaltyService.
 */
class ReportService
{
    public function __construct(
        private PaymentService $payments,
        private PenaltyService $penalties,
        private BillingService $billing,
        private ConcernService $concerns,
    ) {}

    /** The 4 cards on the Reports page: collected this month (all, cash, GCash) and unpaid/overdue today. */
    public function summary(Carbon $month, ?Carbon $today = null): array
    {
        $totals = $this->collections($month)['totals'];

        return $totals + ['unpaid' => round($this->dueAndOverdue($today)['rows']->sum('total'), 2)];
    }

    // ---------- 1. Monthly collection summary ----------

    /**
     * Payments received in the month: totals by mode, per receiver (user × mode), the payment list, and the bills
     * closed "settled from deposit" in that month (not money received, so not counted as collections).
     */
    public function collections(Carbon $month): array
    {
        $payments = Payment::with(['bill.lease.room', 'bill.lease.tenant', 'user'])
            ->whereYear('payment_date', $month->year)->whereMonth('payment_date', $month->month)
            ->orderBy('payment_date')->orderBy('payment_id')->get();

        $receivers = $payments->groupBy('user_id')->map(fn (Collection $items) => [
            'user' => $items->first()->user,
            'count' => $items->count(),
        ] + $this->modeTotals($items))->sortBy(fn (array $r) => $r['user']->full_name)->values();

        $settledIds = AuditLog::where('action', 'Bill settled from deposit')
            ->whereYear('logged_at', $month->year)->whereMonth('logged_at', $month->month)
            ->pluck('logged_at', 'record_id');
        $settled = Bill::with(['lease.room', 'lease.tenant'])->whereIn('bill_id', $settledIds->keys())->orderBy('due_date')->get()
            ->map(fn (Bill $b) => ['bill' => $b, 'date' => $settledIds[$b->bill_id]]);

        return [
            'payments' => $payments,
            'totals' => $this->modeTotals($payments),
            'receivers' => $receivers,
            'settled' => $settled,
            'settled_total' => round($settled->sum(fn (array $r) => (float) $r['bill']->total_amount), 2),
        ];
    }

    private function modeTotals(Collection $payments): array
    {
        $sum = fn (Collection $items) => round($items->sum(fn (Payment $p) => (float) $p->amount), 2);

        return [
            'Cash' => $sum($payments->where('mode', 'Cash')),
            'GCash' => $sum($payments->where('mode', 'GCash')),
            'All' => $sum($payments),
        ];
    }

    // ---------- 2. Due and overdue tenants ----------

    /**
     * Every bill not paid yet with a due date up to today + 7 (the dashboard window), with the penalty so far:
     * rows = ['bill', 'days_late', 'penalty', 'total', 'days_to_due'], oldest first; plus counts and totals.
     */
    public function dueAndOverdue(?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $this->payments->refreshOverdue($today);

        $rows = Bill::with(['lease.room', 'lease.tenant'])->where('status', '!=', 'Paid')
            ->whereDate('due_date', '<=', $today->copy()->addDays(DashboardService::DAYS_AHEAD))
            ->orderBy('due_date')->orderBy('bill_id')->get()
            ->map(fn (Bill $b) => ['bill' => $b, 'days_to_due' => (int) $today->diffInDays($b->due_date, false)]
                + $this->penalties->forBill($b, $today));
        $overdue = $rows->where('bill.status', 'Overdue');

        return [
            'today' => $today,
            'until' => $today->copy()->addDays(DashboardService::DAYS_AHEAD),
            'rows' => $rows,
            'overdue_tenants' => $overdue->pluck('bill.lease_id')->unique()->count(),
            'overdue_total' => round($overdue->sum('total'), 2),
            'due_total' => round($rows->where('bill.status', 'Unpaid')->sum('total'), 2),
            'penalty_total' => round($rows->sum('penalty'), 2),
            'total' => round($rows->sum('total'), 2),
        ];
    }

    // ---------- 3. Statement of account (one lease) ----------

    /** Leases that can have a statement (Active and Ended; cancelled leases never had bills). */
    public function statementLeases(): Collection
    {
        return Lease::with(['room', 'tenant'])->whereIn('status', ['Active', 'Ended'])->get()
            ->sortBy(fn (Lease $l) => [$l->status === 'Active' ? 0 : 1, $l->room->room_no, -$l->lease_id])->values();
    }

    /**
     * Every bill and payment of the lease as a ledger with a running balance.
     * Entries: a bill is a charge on its due date, or on the payment date if paid earlier (without penalty); a late penalty is a charge on the day it was
     * paid; a payment, or a bill settled from the deposit at move-out, is a credit. Entries before $from make up
     * the opening balance. Unpaid bills' penalties so far are shown in the closing, not saved.
     */
    public function statement(Lease $lease, ?Carbon $from = null, ?Carbon $to = null, ?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $this->payments->refreshOverdue($today);
        $lease->loadMissing(['room', 'tenant']);
        $bills = $lease->bills()->with(['payments.user', 'meterReading', 'concerns'])
            ->orderBy('due_date')->orderBy('bill_id')->get();
        $settledAt = AuditLog::where('action', 'Bill settled from deposit')->whereIn('record_id', $bills->pluck('bill_id'))
            ->pluck('logged_at', 'record_id');

        $entries = collect();
        foreach ($bills as $bill) {
            $base = round((float) $bill->total_amount - (float) $bill->penalty_amount, 2);
            $payment = $bill->payments->first();
            // A bill exists before its due date (generated up to 7 days ahead): paid early, it is listed on the payment day.
            $entries->push(['date' => $payment ? $bill->due_date->min($payment->payment_date) : $bill->due_date, 'order' => 1,
                'type' => 'bill', 'bill' => $bill, 'statement' => $this->billing->statementForBill($bill), 'charge' => $base, 'credit' => 0.0]);

            $closedOn = $payment?->payment_date
                ?? ($settledAt->has($bill->bill_id) ? $settledAt[$bill->bill_id]->copy()->startOfDay() : null);
            if ($bill->status === 'Paid' && (float) $bill->penalty_amount > 0) {
                $entries->push(['date' => $closedOn ?? $bill->due_date, 'order' => 2, 'type' => 'penalty', 'bill' => $bill,
                    'days_late' => $this->penalties->daysLate($bill->due_date, $closedOn ?? $bill->due_date, $this->graceDays()),
                    'charge' => (float) $bill->penalty_amount, 'credit' => 0.0]);
            }
            if ($payment) {
                $entries->push(['date' => $payment->payment_date, 'order' => 3, 'type' => 'payment', 'bill' => $bill,
                    'payment' => $payment, 'charge' => 0.0, 'credit' => (float) $payment->amount]);
            } elseif ($bill->status === 'Paid') {
                $entries->push(['date' => $closedOn ?? $bill->due_date, 'order' => 3, 'type' => 'deposit', 'bill' => $bill,
                    'charge' => 0.0, 'credit' => (float) $bill->total_amount]);
            }
        }

        $entries = $entries->sortBy(fn (array $e) => [$e['date']->toDateString(), $e['order'], $e['bill']->bill_id])->values();

        $opening = 0.0;
        $balance = 0.0;
        $shown = collect();
        foreach ($entries as $e) {
            $balance = round($balance + $e['charge'] - $e['credit'], 2);
            if ($from && $e['date']->lt($from)) {
                $opening = $balance;

                continue;
            }
            if ($to && $e['date']->gt($to)) {
                continue;
            }
            $shown->push($e + ['balance' => $balance]);
        }
        $closing = $shown->isEmpty() ? $opening : $shown->last()['balance'];

        // Penalty so far on bills not paid yet (counted only when the period runs to today or later).
        $unpaid = $bills->where('status', '!=', 'Paid')
            ->filter(fn (Bill $b) => (! $from || $b->due_date->gte($from)) && (! $to || $b->due_date->lte($to)))
            ->map(fn (Bill $b) => ['bill' => $b] + $this->penalties->forBill($b, $today))->values();
        $penaltySoFar = $to && $to->lt($today) ? 0.0 : round($unpaid->sum('penalty'), 2);

        return [
            'lease' => $lease,
            'from' => $from,
            'to' => $to,
            'today' => $today,
            'opening' => $opening,
            'entries' => $shown,
            'charges' => round($shown->sum('charge'), 2),
            'credits' => round($shown->sum('credit'), 2),
            'closing' => $closing,
            'unpaid' => $unpaid,
            'penalty_so_far' => $penaltySoFar,
            'due_today' => round($closing + $penaltySoFar, 2),
        ];
    }

    // ---------- 4. Concern history per room ----------

    /**
     * Concerns reported in the date range, grouped by location ("Room 204" / "Shared area").
     * $room: a room ID, 'shared' for shared-area concerns, or null for all.
     * Groups: ['label', 'concerns', 'repeated' (category => count), 'cost', 'charged'].
     */
    public function concernHistory(int|string|null $room, ?Carbon $from, ?Carbon $to): array
    {
        $concerns = Concern::with(['room', 'lease.tenant', 'assignedTo', 'bill'])
            ->when($room === 'shared', fn ($q) => $q->whereNull('room_id'))
            ->when(is_int($room), fn ($q) => $q->where('room_id', $room))
            ->when($from, fn ($q) => $q->whereDate('reported_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('reported_at', '<=', $to))
            ->orderBy('reported_at')->get();

        $groups = $concerns
            ->sortBy(fn (Concern $c) => $c->room?->room_no ?? 'zzz') // rooms in order, shared area last
            ->groupBy(fn (Concern $c) => $c->location())
            ->map(fn (Collection $items, string $label) => [
                'label' => $label,
                'concerns' => $items->sortBy('reported_at')->values(),
                'repeated' => $this->concerns->repeatedCategories($items),
                'cost' => round($items->sum(fn (Concern $c) => (float) $c->cost), 2),
                'charged' => round($items->where('charged_to_tenant', true)->sum(fn (Concern $c) => (float) $c->cost), 2),
            ])->values();

        return [
            'groups' => $groups,
            'count' => $concerns->count(),
            'open' => $concerns->where('status', '!=', 'Resolved')->count(),
            'cost' => round($groups->sum('cost'), 2),
            'charged' => round($groups->sum('charged'), 2),
        ];
    }

    // ---------- 5. Inquiries and room demand ----------

    /**
     * Inquiries made in the date range: total, type × status table, channels, types ranked by how often they were
     * asked for; plus today's picture: prospects still waiting per type next to the vacant / occupied rooms.
     */
    public function inquiryDemand(Carbon $from, Carbon $to): array
    {
        $inquiries = Inquiry::whereDate('inquiry_date', '>=', $from)->whereDate('inquiry_date', '<=', $to)->get();

        $byType = collect(Inquiry::TYPES)->mapWithKeys(fn (string $type) => [$type => [
            'total' => $inquiries->where('preferred_type', $type)->count(),
            'statuses' => collect(Inquiry::STATUSES)->mapWithKeys(fn (string $s) => [
                $s => $inquiries->where('preferred_type', $type)->where('status', $s)->count(),
            ])->all(),
        ]]);

        $rooms = Room::all(['room_id', 'room_type', 'status']);
        $waitingNow = Inquiry::where('status', 'Waiting')->get(['preferred_type']);
        $now = collect(Inquiry::TYPES)->mapWithKeys(fn (string $type) => [$type => [
            'waiting' => $waitingNow->where('preferred_type', $type)->count(),
            'rooms' => $type === 'Any' ? $rooms->count() : $rooms->where('room_type', $type)->count(),
            'vacant' => ($type === 'Any' ? $rooms : $rooms->where('room_type', $type))->where('status', 'Vacant')->count(),
            'occupied' => ($type === 'Any' ? $rooms : $rooms->where('room_type', $type))->where('status', 'Occupied')->count(),
        ]]);

        return [
            'from' => $from,
            'to' => $to,
            'total' => $inquiries->count(),
            'byType' => $byType,
            'statusTotals' => collect(Inquiry::STATUSES)->mapWithKeys(fn (string $s) => [$s => $inquiries->where('status', $s)->count()]),
            'ranked' => $byType->map(fn (array $r) => $r['total'])->filter()->sortDesc(),
            'channels' => collect(Inquiry::CHANNELS)->mapWithKeys(fn (string $c) => [$c => $inquiries->where('channel', $c)->count()]),
            'via_representative' => $inquiries->where('via_representative', true)->count(),
            'referred' => $inquiries->where('status', 'Referred')->count(),
            'now' => $now,
        ];
    }

    // ---------- 6. Move-out settlements ----------

    /**
     * Departed leases summary: security deposit breakdown, deductions
     * (maintenance fee, damages, utility bills), and net refunds/excess.
     */
    public function moveOutSettlements(?Carbon $from = null, ?Carbon $to = null): array
    {
        $leases = Lease::with(['room', 'tenant', 'bills'])
            ->where('status', 'Ended')
            ->get();

        $endLogs = AuditLog::where('action', 'Lease ended and room marked vacant')
            ->whereIn('record_id', $leases->pluck('lease_id'))
            ->get()
            ->groupBy('record_id');

        $settledBillIds = AuditLog::where('action', 'Bill settled from deposit')
            ->pluck('record_id');

        $rows = collect();
        foreach ($leases as $lease) {
            $log = $endLogs->get($lease->lease_id)?->sortByDesc('logged_at')->first();
            $moveOutDate = null;
            if ($log) {
                if (preg_match('/Actual move-out date:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/', (string) $log->details, $m)) {
                    $moveOutDate = Carbon::parse($m[1])->startOfDay();
                } else {
                    $moveOutDate = $log->logged_at->copy()->startOfDay();
                }
            } else {
                $moveOutDate = $lease->move_in_date->copy()->addMonths($lease->min_stay_months ?: 6)->startOfDay();
            }

            if ($from && $moveOutDate->lt($from)) {
                continue;
            }
            if ($to && $moveOutDate->gt($to)) {
                continue;
            }

            $totalDeposit = $lease->depositTotal();
            $maintenanceDeduction = (float) $lease->deposit_maintenance;

            // Damage deduction from concerns charged to tenant
            $damageDeduction = (float) Concern::where('lease_id', $lease->lease_id)
                ->where('charged_to_tenant', true)
                ->sum('cost');

            // Utilities / unpaid bills
            $unpaidBills = (float) Bill::where('lease_id', $lease->lease_id)
                ->where('status', '!=', 'Paid')
                ->sum('total_amount');
            $depositSettledBills = (float) Bill::where('lease_id', $lease->lease_id)
                ->whereIn('bill_id', $settledBillIds)
                ->sum('total_amount');
            $billsDeduction = round($unpaidBills + $depositSettledBills, 2);

            $totalDeductions = round($maintenanceDeduction + $damageDeduction + $billsDeduction, 2);
            $netRefund = round($totalDeposit - $totalDeductions, 2);

            $rows->push([
                'lease' => $lease,
                'room_no' => $lease->room->room_no,
                'room_type' => $lease->room->room_type,
                'tenant_name' => $lease->tenant->full_name,
                'tenant_contact' => $lease->tenant->contact_no,
                'move_in_date' => $lease->move_in_date,
                'move_out_date' => $moveOutDate,
                'deposit_maintenance' => $maintenanceDeduction,
                'deposit_damage' => (float) $lease->deposit_damage,
                'deposit_bills' => (float) $lease->deposit_bills,
                'deposit_keys' => (float) $lease->deposit_keys,
                'total_deposit' => $totalDeposit,
                'deductions' => [
                    'maintenance' => $maintenanceDeduction,
                    'damages' => $damageDeduction,
                    'bills' => $billsDeduction,
                    'total' => $totalDeductions,
                ],
                'net_refund' => $netRefund,
                'status' => $netRefund > 0 ? 'Refund' : ($netRefund < 0 ? 'Excess' : 'Settled'),
            ]);
        }

        $rows = $rows->sortByDesc(fn ($r) => $r['move_out_date']?->timestamp ?? 0)->values();

        $totalDeposits = round($rows->sum('total_deposit'), 2);
        $totalDeductionsSum = round($rows->sum('deductions.total'), 2);
        $totalRefunds = round($rows->filter(fn ($r) => $r['net_refund'] > 0)->sum('net_refund'), 2);
        $totalExcess = round(abs($rows->filter(fn ($r) => $r['net_refund'] < 0)->sum('net_refund')), 2);

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'count' => $rows->count(),
            'total_deposits' => $totalDeposits,
            'total_deductions' => $totalDeductionsSum,
            'total_refunds' => $totalRefunds,
            'total_excess' => $totalExcess,
        ];
    }

    private function graceDays(): int
    {
        return (int) Setting::current()->grace_days;
    }
}
