<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillRequest;
use App\Models\Bill;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** Billing (Owner/Manager only; see routes). */
class BillController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function index(Request $request): View
    {
        $filter = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($filter['month']) ? Carbon::createFromFormat('Y-m-d', $filter['month'].'-01') : today()->startOfMonth();

        // Leases whose next bill can be generated now (7 days before the due date, or overdue to generate).
        $toBill = Lease::with(['room', 'tenant'])->where('status', 'Active')->get()
            ->filter(fn (Lease $l) => $this->billing->canGenerateNow($l))
            ->map(fn (Lease $l) => ['lease' => $l, 'due_date' => $this->billing->nextDueDate($l)])
            ->sortBy(fn ($row) => $row['due_date']->toDateString().$row['lease']->room->room_no)
            ->values();

        $bills = Bill::with(['lease.room', 'lease.tenant'])
            ->whereYear('due_date', $month->year)->whereMonth('due_date', $month->month)
            ->orderBy('due_date')->get();

        return view('bills.index', [
            'month' => $month,
            'toBill' => $toBill,
            'bills' => $bills,
            'totals' => ['count' => $bills->count(), 'amount' => $bills->sum(fn ($b) => (float) $b->total_amount)],
        ]);
    }

    /** Generate form + statement preview. For a metered room the reading is entered, then "Show statement". */
    public function create(Request $request): View|RedirectResponse
    {
        $lease = Lease::with(['room', 'tenant'])->findOrFail($request->integer('lease'));

        if (! $this->billing->canGenerateNow($lease)) {
            return redirect()->route('bills.index')->withErrors(['lease' => "Room {$lease->room->room_no} has no bill to generate right now."]);
        }

        $metered = (bool) $lease->room->is_metered;
        $last = $metered ? $this->billing->lastReading($lease->room_id) : null;
        $input = [
            'previous_reading' => $request->input('previous_reading', $last?->current_reading),
            'current_reading' => $request->input('current_reading'),
            'reading_date' => $request->input('reading_date', min(today(), $this->billing->nextDueDate($lease)->subDay())->toDateString()),
            'source' => $request->input('source', 'Meter'),
        ];

        // A preview with the reading only when the reading entered so far is valid.
        $reading = null;
        $errors = null;
        if ($metered && $request->has('current_reading')) {
            $validator = Validator::make($input, BillRequest::readingRules(), BillRequest::readingMessages());
            $validator->fails() ? $errors = $validator->errors() : $reading = $validator->validated();
        }

        return view('bills.create', [
            'lease' => $lease,
            'metered' => $metered,
            'lastReading' => $last,
            'input' => $input,
            'readingErrors' => $errors,
            'statement' => $this->billing->preview($lease, $reading),
            'ready' => ! $metered || $reading !== null,
            'sources' => MeterReading::SOURCES,
        ]);
    }

    public function store(BillRequest $request): RedirectResponse
    {
        $lease = Lease::findOrFail($request->validated('lease_id'));
        $bill = $this->billing->generate($lease, $request->reading(), $request->validated('due_date'), $request->user());

        return redirect()->route('bills.show', $bill)
            ->with('status', 'Bill generated: ₱'.number_format((float) $bill->total_amount, 2).' due on '.$bill->due_date->format('j M Y').'.');
    }

    public function show(Bill $bill): View
    {
        $bill->load(['lease.room', 'lease.tenant', 'user', 'payments']);

        return view('bills.show', [
            'bill' => $bill,
            'statement' => $this->billing->statementForBill($bill),
            'canDelete' => $this->billing->canDelete($bill)
                && ! $bill->lease->bills()->where('due_date', '>', $bill->due_date)->exists(),
        ]);
    }

    public function destroy(Bill $bill, Request $request): RedirectResponse
    {
        $month = $bill->due_date->format('Y-m');
        $this->billing->delete($bill, $request->user());

        return redirect()->route('bills.index', ['month' => $month])->with('status', 'The bill was deleted. You can generate it again.');
    }
}
