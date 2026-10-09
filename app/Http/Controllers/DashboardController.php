<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operations Dashboard.
 * Owner/Manager: operational command console with billing alerts, meter readings needed,
 * overdue accounts, facility concerns, turnovers, and pending refunds.
 * Caretaker: strictly scoped to concerns and rooms only (no financial data, billing queues, or tenant secrets).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $user = $request->user() ?? auth()->user();
        $staff = $user?->hasRole('Owner', 'Manager') ?? false;
        $isOwner = $user?->hasRole('Owner') ?? false;

        if ($staff) {
            $data = $dashboard->forStaff();

            $billsDueSoonCount = $data['counts']['due_soon'] ?? 0;
            $unpaidBills = $data['toCollect']->where('bill.status', 'Unpaid');
            $billsDueSoonTotal = (float) $unpaidBills->sum('total');

            $overdueCount = $data['counts']['overdue_tenants'] ?? 0;
            $overdueRows = $data['toCollect']->where('bill.status', 'Overdue');
            $overdueAmount = (float) $overdueRows->sum('total');
            $overdueBills = $overdueRows->map(fn ($r) => $r['bill'])->values();

            $readingsNeeded = $data['readingsNeeded'] ?? collect();
            $readingsNeededCount = $data['counts']['readings_needed'] ?? 0;

            $vacatingRooms = $data['vacatingRooms'] ?? collect();

            $pendingRefundLeases = collect();
            $pendingRefundsTotal = 0.0;
            $pendingRefundsCount = $data['counts']['pending_refunds'] ?? 0;

            if ($isOwner && isset($data['pendingRefunds'])) {
                $pendingRefundLeases = $data['pendingRefunds']->map(function ($row) {
                    if (is_array($row) && isset($row['lease'])) {
                        $lease = $row['lease'];
                        $lease->settlement = (object) [
                            'refund_amount' => (float) ($row['net_refund'] ?? 0.0),
                            'net_refund' => (float) ($row['net_refund'] ?? 0.0),
                        ];

                        return $lease;
                    }

                    return $row;
                });
                $pendingRefundsTotal = (float) $data['pendingRefunds']->sum('net_refund');
            }

            $breachedLeases = $data['breachedLeases'] ?? collect();
            $breachedLeasesCount = $data['counts']['breached_leases'] ?? 0;

            $urgentActionsCount = $breachedLeasesCount
                + $overdueBills->count()
                + $vacatingRooms->count()
                + $readingsNeeded->count()
                + ($isOwner ? $pendingRefundLeases->count() : 0);

            $viewData = array_merge($data, [
                'staff' => true,
                'isOwner' => $isOwner,
                'breachedLeases' => $breachedLeases,
                'breachedLeasesCount' => $breachedLeasesCount,
                'billsDueSoonCount' => $billsDueSoonCount,
                'billsDueSoonTotal' => $billsDueSoonTotal,
                'overdueCount' => $overdueCount,
                'overdueAmount' => $overdueAmount,
                'overdueBills' => $overdueBills,
                'readingsNeeded' => $readingsNeeded,
                'readingsNeededCount' => $readingsNeededCount,
                'vacatingRooms' => $vacatingRooms,
                'pendingRefundLeases' => $pendingRefundLeases,
                'pendingRefundsTotal' => $pendingRefundsTotal,
                'pendingRefundsCount' => $pendingRefundsCount,
                'urgentActionsCount' => $urgentActionsCount,
                'openConcernsCount' => $data['counts']['open_concerns'] ?? 0,
                'urgentConcernsCount' => $data['counts']['urgent_concerns'] ?? 0,
                'vacantRoomsCount' => $data['counts']['vacant_rooms'] ?? 0,
                'occupiedRoomsCount' => $data['counts']['occupied_rooms'] ?? 0,
            ]);
        } else {
            $data = $dashboard->forCaretaker();

            $viewData = array_merge($data, [
                'staff' => false,
                'isOwner' => false,
                'breachedLeases' => collect(),
                'breachedLeasesCount' => 0,
                'billsDueSoonCount' => 0,
                'billsDueSoonTotal' => 0.0,
                'overdueCount' => 0,
                'overdueAmount' => 0.0,
                'overdueBills' => collect(),
                'readingsNeeded' => collect(),
                'readingsNeededCount' => 0,
                'vacatingRooms' => $data['vacatingRooms'] ?? collect(),
                'pendingRefundLeases' => collect(),
                'pendingRefundsTotal' => 0.0,
                'pendingRefundsCount' => 0,
                'urgentActionsCount' => 0,
                'openConcernsCount' => $data['counts']['open_concerns'] ?? 0,
                'urgentConcernsCount' => $data['counts']['urgent_concerns'] ?? 0,
                'vacantRoomsCount' => $data['counts']['vacant_rooms'] ?? 0,
                'occupiedRoomsCount' => $data['counts']['occupied_rooms'] ?? 0,
            ]);
        }

        return view('dashboard', $viewData);
    }
}
