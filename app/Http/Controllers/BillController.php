<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillRequest;
use App\Models\Bill;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Setting;
use App\Services\BillingService;
use App\Services\ConcernService;
use App\Services\PaymentService;
use App\Services\PenaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** Billing (Owner/Manager only; see routes). */
class BillController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function index(Request $request, PaymentService $payments): View
    {
        $payments->refreshOverdue();
        $filter = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:All,Unpaid,Paid,Overdue'],
            'search' => ['nullable', 'string', 'max:50'],
        ]);
        $month = isset($filter['month']) ? Carbon::createFromFormat('Y-m-d', $filter['month'].'-01') : today()->startOfMonth();

        // Leases whose next bill can be generated now (7 days before the due date, or overdue to generate).
        $toBill = Lease::with(['room', 'tenant'])->where('status', 'Active')->get()
            ->filter(fn (Lease $l) => $this->billing->canGenerateNow($l))
            ->map(fn (Lease $l) => [
                'lease' => $l,
                'due_date' => $this->billing->nextDueDate($l),
                'last_reading' => $l->room->is_metered ? $this->billing->lastReading($l->room_id) : null,
            ])
            ->sortBy(fn ($row) => $row['due_date']->toDateString().$row['lease']->room->room_no)
            ->values();

        $billsQuery = Bill::with(['lease.room', 'lease.tenant', 'meterReading'])
            ->whereYear('due_date', $month->year)->whereMonth('due_date', $month->month);

        $allBills = (clone $billsQuery)->get();

        $statusFilter = $filter['status'] ?? 'All';
        if ($statusFilter !== 'All') {
            $billsQuery->where('status', $statusFilter);
        }

        $searchQuery = trim($filter['search'] ?? '');
        if ($searchQuery !== '') {
            $s = '%'.$searchQuery.'%';
            $billsQuery->where(function ($q) use ($s) {
                $q->whereHas('lease.room', fn ($rq) => $rq->where('room_no', 'like', $s))
                    ->orWhereHas('lease.tenant', fn ($tq) => $tq->where('full_name', 'like', $s));
            });
        }

        $bills = $billsQuery->orderBy('due_date')->get();
        $settings = Setting::current();

        $meteredBills = $allBills->filter(fn ($b) => $b->meterReading !== null);
        $totalKwh = $meteredBills->sum(fn ($b) => max(0, (float) $b->meterReading->current_reading - (float) $b->meterReading->previous_reading));

        $stats = [
            'total_amount' => (float) $allBills->sum(fn ($b) => (float) $b->total_amount),
            'total_count' => $allBills->count(),
            'paid_amount' => (float) $allBills->where('status', 'Paid')->sum(fn ($b) => (float) $b->total_amount),
            'paid_count' => $allBills->where('status', 'Paid')->count(),
            'unpaid_amount' => (float) $allBills->where('status', 'Unpaid')->sum(fn ($b) => (float) $b->total_amount),
            'unpaid_count' => $allBills->where('status', 'Unpaid')->count(),
            'overdue_amount' => (float) $allBills->where('status', 'Overdue')->sum(fn ($b) => (float) $b->total_amount),
            'overdue_count' => $allBills->where('status', 'Overdue')->count(),
            'total_rent' => (float) $allBills->sum(fn ($b) => (float) $b->rent_amount),
            'total_water' => (float) $allBills->sum(fn ($b) => (float) $b->water_amount),
            'total_electricity' => (float) $allBills->sum(fn ($b) => (float) $b->electricity_amount),
            'total_appliances' => (float) $allBills->sum(fn ($b) => (float) $b->appliance_amount),
            'total_kwh' => (float) $totalKwh,
            'metered_count' => $meteredBills->count(),
        ];

        return view('bills.index', [
            'month' => $month,
            'toBill' => $toBill,
            'bills' => $bills,
            'totals' => ['count' => $bills->count(), 'amount' => $bills->sum(fn ($b) => (float) $b->total_amount)],
            'stats' => $stats,
            'settings' => $settings,
            'statusFilter' => $statusFilter,
            'searchQuery' => $searchQuery,
            'sources' => MeterReading::SOURCES,
        ]);
    }

    /** Generate form + statement preview. For a metered room the reading is entered, then "Show statement". */
    public function create(Request $request, ConcernService $concerns): View|RedirectResponse
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

        // Chargeable concerns: offered as checkboxes, never ticked automatically (decision 15).
        $chargeable = $concerns->chargeable($lease);
        $ticked = array_map('intval', (array) $request->input('concerns', []));

        return view('bills.create', [
            'lease' => $lease,
            'metered' => $metered,
            'chargeable' => $chargeable,
            'ticked' => $ticked,
            'lastReading' => $last,
            'input' => $input,
            'readingErrors' => $errors,
            'statement' => $this->billing->preview($lease, $reading, $ticked),
            'ready' => ! $metered || $reading !== null,
            'sources' => MeterReading::SOURCES,
        ]);
    }

    public function store(BillRequest $request): RedirectResponse
    {
        $lease = Lease::findOrFail($request->validated('lease_id'));
        $bill = $this->billing->generate($lease, $request->reading(), $request->validated('due_date'), $request->user(),
            null, (array) $request->validated('concerns', []));

        return redirect()->route('bills.show', $bill)
            ->with('status', 'Bill generated: ₱'.number_format((float) $bill->total_amount, 2).' due on '.$bill->due_date->format('j M Y').'.');
    }

    public function show(Bill $bill, PaymentService $payments, PenaltyService $penalties): View
    {
        $payments->refreshOverdue();
        $bill->refresh()->load(['lease.room', 'lease.tenant', 'user', 'payments']);

        return view('bills.show', [
            'bill' => $bill,
            'statement' => $this->billing->statementForBill($bill),
            'payment' => $bill->payments->first(),
            'dueToday' => $bill->status === 'Paid' ? null : $penalties->forBill($bill, today()),
            'canPay' => $payments->canPay($bill),
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
