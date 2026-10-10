<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Lease;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MOA Lines 7 and 8 Delinquency and Contractual Termination Engine.
 *
 * MOA Line 7:
 *   "Renters are required to pay their monthly obligation promptly and given only 3 days grace period.
 *   Failure to pay on time, corresponds a penalty of 1% of rent per day times the number of days delayed."
 *
 * MOA Line 8:
 *   "Failure to pay the monthly obligation after the security deposit has been consumed,
 *   the contract will be cancelled, and renters must vacate the premises immediately."
 */
class DelinquencyService
{
    public function __construct(
        private PenaltyService $penalties,
    ) {}

    /**
     * Compute total arrears vs security deposit for a given lease.
     *
     * Total Arrears = sum(Unpaid bills' base, utilities, appliance surcharges) + sum(accrued Line 7 penalties today).
     * Total Deposit = lease->depositTotal().
     * Breach condition: Total Arrears >= Total Deposit and Total Arrears > 0.
     *
     * @return array{
     *     lease: Lease,
     *     unpaid_bills: Collection<int, array{bill: Bill, penalty_info: array}>,
     *     base_arrears: float,
     *     penalty_arrears: float,
     *     total_arrears: float,
     *     total_deposit: float,
     *     deposit_buffer: float,
     *     is_breached: bool
     * }
     */
    public function arrearsForLease(Lease $lease, ?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $graceDays = (int) (Setting::current()->grace_days ?? 3);

        $unpaidBills = Bill::where('lease_id', $lease->lease_id)
            ->where('status', '!=', 'Paid')
            ->orderBy('due_date')
            ->get();

        $baseArrears = 0.0;
        $penaltyArrears = 0.0;
        $billsList = collect();

        foreach ($unpaidBills as $bill) {
            $cutoff = $bill->due_date->copy()->startOfDay()->addDays($graceDays);
            $isMatured = ($bill->status === 'Overdue') || $today->greaterThan($cutoff);

            // A bill can ONLY contribute to a breach if its status is officially 'Overdue',
            // OR if today() > due_date + grace_days (default 3 days).
            // Exclude any bill where status === 'Unpaid' and the current date is still on or before due_date + grace_days.
            if (! $isMatured) {
                continue;
            }

            $penaltyInfo = $this->penalties->forBill($bill, $today);
            $billBase = round((float) $bill->total_amount - (float) $bill->penalty_amount, 2);
            $baseArrears += $billBase;
            $penaltyArrears += (float) $penaltyInfo['penalty'];

            $billsList->push([
                'bill' => $bill,
                'penalty_info' => $penaltyInfo,
            ]);
        }

        $baseArrears = round($baseArrears, 2);
        $penaltyArrears = round($penaltyArrears, 2);
        $totalArrears = round($baseArrears + $penaltyArrears, 2);
        $totalDeposit = $lease->depositTotal();
        $depositBuffer = round($totalDeposit - $totalArrears, 2);
        $isBreached = ($totalArrears >= $totalDeposit) && ($totalArrears > 0);

        return [
            'lease' => $lease,
            'unpaid_bills' => $billsList,
            'base_arrears' => $baseArrears,
            'penalty_arrears' => $penaltyArrears,
            'total_arrears' => $totalArrears,
            'total_deposit' => $totalDeposit,
            'deposit_buffer' => $depositBuffer,
            'is_breached' => $isBreached,
        ];
    }

    /**
     * Active leases whose accumulated arrears meet or exceed their security deposit.
     * Returned collection sorted by largest breach (total_arrears - total_deposit descending).
     *
     * @return Collection<int, array{
     *     lease: Lease,
     *     unpaid_bills: Collection,
     *     base_arrears: float,
     *     penalty_arrears: float,
     *     total_arrears: float,
     *     total_deposit: float,
     *     deposit_buffer: float,
     *     is_breached: bool
     * }>
     */
    public function breachedLeases(?Carbon $today = null): Collection
    {
        $today = ($today ?? today())->copy()->startOfDay();

        $activeLeases = Lease::with(['room', 'tenant', 'occupants'])
            ->where('status', 'Active')
            ->get();

        return $activeLeases->map(fn (Lease $l) => $this->arrearsForLease($l, $today))
            ->filter(fn (array $data) => $data['is_breached'])
            ->sortByDesc(fn (array $data) => $data['total_arrears'] - $data['total_deposit'])
            ->values();
    }

    /**
     * Check if a lease is eligible for immediate termination under MOA Clause 8.
     */
    public function canTerminateClause8(Lease $lease, ?Carbon $today = null): bool
    {
        if ($lease->status !== 'Active') {
            return false;
        }

        $arrears = $this->arrearsForLease($lease, $today);

        return $arrears['is_breached'];
    }

    /**
     * Terminate the lease under MOA Clause 8 in an atomic database transaction.
     *
     * Offsets the entire security deposit against unpaid bills and accrued penalties.
     * Flips tbl_lease.status to 'Cancelled'.
     * Changes tbl_tenant.status to 'Inactive'.
     * Transitions tbl_room.status to 'Vacant' with expected_vacant_date = null.
     * Removes remaining occupants.
     * Records corresponding audit logs in tbl_audit_log.
     *
     * @throws ValidationException
     */
    public function terminateClause8(Lease $lease, User $by, ?string $notes = null, ?Carbon $today = null): void
    {
        $today = ($today ?? today())->copy()->startOfDay();

        if (! $this->canTerminateClause8($lease, $today)) {
            $arrears = $this->arrearsForLease($lease, $today);
            throw ValidationException::withMessages([
                'lease' => 'Tenant arrears (₱'.number_format($arrears['total_arrears'], 2).') have not exhausted their security deposit (₱'.number_format($arrears['total_deposit'], 2).'). Termination under MOA Clause 8 is not permitted.',
            ]);
        }

        DB::transaction(function () use ($lease, $by, $notes, $today) {
            $lease = Lease::whereKey($lease->lease_id)->lockForUpdate()->firstOrFail();
            $room = Room::whereKey($lease->room_id)->lockForUpdate()->firstOrFail();
            $tenant = Tenant::whereKey($lease->tenant_id)->lockForUpdate()->firstOrFail();

            $unpaidBills = Bill::where('lease_id', $lease->lease_id)
                ->where('status', '!=', 'Paid')
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            $totalOffset = 0.0;
            foreach ($unpaidBills as $b) {
                $penaltyInfo = $this->penalties->forBill($b, $today);
                $b->penalty_amount = $penaltyInfo['penalty'];
                $b->total_amount = $penaltyInfo['total'];
                $b->status = 'Paid';
                $b->save();

                $totalOffset += (float) $b->total_amount;

                AuditLog::record(
                    'Bill settled from deposit',
                    'tbl_bill',
                    $b->bill_id,
                    $by->user_id,
                    "Bill #{$b->bill_id} settled from security deposit under MOA Clause 8 termination. Total amount: ₱".number_format((float) $b->total_amount, 2)
                );
            }

            // Flip lease to Cancelled per MOA Line 8: "the contract will be cancelled"
            $lease->update(['status' => 'Cancelled']);

            // Deactivate tenant
            $tenant->update(['status' => 'Inactive']);

            // Repossess room and mark vacant
            $room->update([
                'status' => 'Vacant',
                'expected_vacant_date' => null,
            ]);

            // Remove any staying occupants
            $lease->occupants()->whereNull('date_removed')->update([
                'date_removed' => $today,
            ]);

            // Log lease termination, room repossession, and tenant deactivation
            $depositTotal = $lease->depositTotal();
            $details = 'Contract cancelled under MOA Clause 8 (Security deposit exhausted). Total arrears: ₱'
                .number_format($totalOffset, 2).' offset against deposit: ₱'
                .number_format($depositTotal, 2)
                .($notes ? " | Notes: {$notes}" : '');

            AuditLog::record('Lease terminated under MOA Clause 8', 'tbl_lease', $lease->lease_id, $by->user_id, $details);
            AuditLog::record('Room repossessed under Clause 8', 'tbl_room', $room->room_id, $by->user_id, "Room {$room->room_no} repossessed immediately following Clause 8 lease termination.");
            AuditLog::record('Tenant deactivated', 'tbl_tenant', $tenant->tenant_id, $by->user_id, "Tenant {$tenant->full_name} deactivated due to MOA Clause 8 contract cancellation.");
        });
    }
}
