<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Concern;
use App\Models\Lease;
use App\Models\Moveout;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Numbers and lists for the Dashboard (Module 9a). Reads only; the one write is refreshing the Overdue status.
 *
 * - Bills due in the next 7 days: generated, not yet Overdue bills with a due date up to today + 7
 *   (a bill still inside its grace period counts here; it is not late yet).
 * - Overdue tenants: leases with at least one Overdue bill (a tenant with 2 overdue bills counts once).
 * - Leases due soon without a bill: their next bill could be generated now (BillingService::canGenerateNow).
 */
class DashboardService
{
    public const DAYS_AHEAD = 7;

    public function __construct(
        private PaymentService $payments,
        private PenaltyService $penalties,
        private BillingService $billing,
    ) {}

    /** Everything the Owner and Manager see. */
    public function forStaff(?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        if (Setting::query()->exists()) { // no settings row = fresh install with no bills yet
            $this->payments->refreshOverdue($today);
        }

        $toCollect = $this->dueAndOverdue($today);
        $concerns = $this->openConcerns();

        return [
            'counts' => [
                'due_soon' => $toCollect->where('bill.status', 'Unpaid')->count(),
                'overdue_tenants' => $toCollect->where('bill.status', 'Overdue')->pluck('bill.lease_id')->unique()->count(),
            ] + $this->concernCounts($concerns) + $this->roomCounts(),
            'toCollect' => $toCollect,
            'unbilled' => $this->unbilledDueSoon($today),
            'concerns' => $concerns,
            'refunds' => $this->refundsDue(),
        ];
    }

    /** The Caretaker sees only concerns and rooms: no bills, amounts or refunds are even loaded. */
    public function forCaretaker(): array
    {
        $concerns = $this->openConcerns();

        return [
            'counts' => $this->concernCounts($concerns) + $this->roomCounts(),
            'concerns' => $concerns,
            'rooms' => Room::orderBy('room_no')->get(['room_id', 'room_no', 'room_type', 'status', 'expected_vacant_date']),
        ];
    }

    /**
     * Overdue bills (any age) and Unpaid bills due up to today + 7, oldest due date first, each with what it
     * would cost if paid today: ['bill', 'days_late', 'penalty', 'total', 'days_to_due'].
     */
    public function dueAndOverdue(Carbon $today): Collection
    {
        return Bill::with(['lease.room', 'lease.tenant'])
            ->where(fn ($q) => $q->where('status', 'Overdue')
                ->orWhere(fn ($q) => $q->where('status', 'Unpaid')->whereDate('due_date', '<=', $today->copy()->addDays(self::DAYS_AHEAD))))
            ->orderBy('due_date')->orderBy('bill_id')->get()
            ->map(fn (Bill $b) => ['bill' => $b, 'days_to_due' => (int) $today->diffInDays($b->due_date, false)]
                + $this->penalties->forBill($b, $today));
    }

    /** Active leases whose next bill can be generated now but has not been: ['lease', 'due_date']. */
    public function unbilledDueSoon(Carbon $today): Collection
    {
        return Lease::with(['room', 'tenant', 'moveout'])->where('status', 'Active')->get()
            ->filter(fn (Lease $l) => $this->billing->canGenerateNow($l, $today))
            ->map(fn (Lease $l) => ['lease' => $l, 'due_date' => $this->billing->nextDueDate($l)])
            ->sortBy(fn (array $row) => $row['due_date']->toDateString().' '.$row['lease']->room->room_no)
            ->values();
    }

    /** Open and in-progress concerns: urgent first, then oldest. */
    public function openConcerns(): Collection
    {
        return Concern::with(['room', 'assignedTo'])->where('status', '!=', 'Resolved')
            ->orderByRaw("priority = 'Urgent' desc")->orderBy('reported_at')->get();
    }

    /** Approved refunds not handed over yet (status Refunded), earliest refund due date first. */
    public function refundsDue(): Collection
    {
        return Moveout::with(['lease.room', 'lease.tenant'])->where('status', 'Refunded')
            ->orderBy('refund_due_date')->get();
    }

    private function concernCounts(Collection $open): array
    {
        return ['open_concerns' => $open->count(), 'urgent_concerns' => $open->where('priority', 'Urgent')->count()];
    }

    private function roomCounts(): array
    {
        $byStatus = Room::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return ['vacant_rooms' => (int) ($byStatus['Vacant'] ?? 0), 'vacating_rooms' => (int) ($byStatus['Vacating'] ?? 0)];
    }
}
