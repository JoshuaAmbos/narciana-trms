<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveoutRequest;
use App\Models\Lease;
use App\Models\Moveout;
use App\Services\BillingService;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Move-out notice, inspection, settlement (Owner/Manager); approval and refund release (Owner). */
class MoveoutController extends Controller
{
    public function __construct(private SettlementService $settlements) {}

    public function index(): View
    {
        $all = Moveout::with(['lease.room', 'lease.tenant'])->orderByDesc('notice_date')->get();

        return view('moveouts.index', [
            'pending' => $all->where('status', 'Pending')->sortBy(fn ($m) => ($m->actual_date ?? $m->planned_date)->toDateString()),
            'refunds' => $all->where('status', 'Refunded')->sortBy(fn ($m) => $m->refund_due_date->toDateString()),
            'excess' => $all->where('status', 'Excess billed'),
            'settled' => $all->where('status', 'Settled')->take(30),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $lease = Lease::with(['room', 'tenant', 'moveout'])->findOrFail($request->integer('lease'));
        if ($lease->status !== 'Active' || $lease->moveout) {
            return redirect()->route('leases.show', $lease)->withErrors(['lease' => 'This lease is not active or already has a move-out notice.']);
        }

        return view('moveouts.create', [
            'lease' => $lease,
            'noticeDate' => today(),
            'plannedDate' => today()->addMonthNoOverflow(),
            'minStayUntil' => $lease->move_in_date->copy()->addMonthsNoOverflow($lease->min_stay_months),
        ]);
    }

    public function store(MoveoutRequest $request): RedirectResponse
    {
        $lease = Lease::findOrFail($request->validated('lease_id'));
        $moveout = $this->settlements->giveNotice($lease, $request->validated('notice_date'), $request->validated('planned_date'), $request->user());

        return redirect()->route('moveouts.show', $moveout)
            ->with('status', "Notice recorded. Room {$lease->room->room_no} is now Vacating (expected vacant {$moveout->planned_date->format('j M Y')}).")
            ->with('warning', $this->settlements->noticeWarning($moveout->notice_date, $moveout->planned_date));
    }

    public function show(Moveout $moveout, BillingService $billing): View
    {
        $moveout->load(['lease.room', 'lease.tenant', 'items', 'finalBill', 'excessBill', 'user']);
        $lease = $moveout->lease;
        $actual = $moveout->actual_date;

        return view('moveouts.show', [
            'moveout' => $moveout,
            'lease' => $lease,
            'figures' => $this->settlements->figures($moveout),
            'finalNeeded' => $actual ? $billing->finalPeriod($lease, $actual) !== null : null,
            'regularDone' => $actual ? $billing->regularBillsDone($lease, $actual) : null,
            'nextRegularDue' => $billing->nextDueDate($lease),
            'finalReady' => $this->settlements->finalBillReady($moveout),
            'canCancel' => $this->settlements->canCancelNotice($moveout),
            'canApprove' => Gate::allows('approve-settlements') && $moveout->isPending() && $actual && $moveout->inspected_at
                && $this->settlements->finalBillReady($moveout),
            'steps' => $this->settlements->steps($moveout),
            'canRelease' => Gate::allows('approve-settlements') && $moveout->status === 'Refunded',
            'noticeWarning' => $this->settlements->noticeWarning($moveout->notice_date, $moveout->planned_date),
            'minStayUntil' => $lease->move_in_date->copy()->addMonthsNoOverflow($lease->min_stay_months),
        ]);
    }

    /** Correct the notice/planned dates or record the actual move-out date. */
    public function update(MoveoutRequest $request, Moveout $moveout): RedirectResponse
    {
        $this->settlements->updateDates($moveout, $request->validated(), $request->user());

        return redirect()->route('moveouts.show', $moveout)->with('status', 'Move-out dates saved.')
            ->with('warning', $this->settlements->noticeWarning(Carbon::parse($request->validated('notice_date')), Carbon::parse($request->validated('planned_date'))));
    }

    /** Cancel a notice (tenant stays). */
    public function destroy(Moveout $moveout, Request $request): RedirectResponse
    {
        $lease = $moveout->lease;
        $this->settlements->cancelNotice($moveout, $request->user());

        return redirect()->route('leases.show', $lease)->with('status', "The move-out notice was cancelled. Room {$lease->room->room_no} is Occupied again.");
    }

    /** Step 2: the room was inspected (damage items, if any, are already listed). */
    public function inspect(Moveout $moveout, Request $request): RedirectResponse
    {
        $this->settlements->markInspected($moveout, $request->user());

        return redirect()->route('moveouts.show', $moveout)->with('status', 'Inspection recorded. The settlement can now be computed.');
    }

    public function compute(Moveout $moveout, Request $request): RedirectResponse
    {
        $this->settlements->compute($moveout, $request->user());

        return redirect()->route('moveouts.show', $moveout)->with('status', 'Settlement computed. The Owner can now approve it.');
    }

    public function approve(Moveout $moveout, Request $request): RedirectResponse
    {
        Gate::authorize('approve-settlements');
        $moveout = $this->settlements->approve($moveout, $request->user());

        $message = match ($moveout->status) {
            'Refunded' => 'Settlement approved. Refund of ₱'.number_format((float) $moveout->refund_amount, 2).' due by '.$moveout->refund_due_date->format('j M Y').'.',
            'Excess billed' => 'Settlement approved. An excess bill of ₱'.number_format((float) $moveout->excess_amount, 2).' was created; paying it settles the move-out.',
            default => 'Settlement approved and settled. The lease is ended and the room is Vacant.',
        };

        return redirect()->route('moveouts.show', $moveout)->with('status', $message);
    }

    /** Owner confirms the refund was handed over: settled. */
    public function release(Moveout $moveout, Request $request): RedirectResponse
    {
        Gate::authorize('approve-settlements');
        $this->settlements->markRefundReleased($moveout, $request->user());

        return redirect()->route('moveouts.show', $moveout)->with('status', 'Refund released. Settled: the lease is ended and the room is Vacant.');
    }
}
