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

    public function index(Request $request, PaymentService $payments, PenaltyService $penalties, ConcernService $concerns): View
    {
        $payments->refreshOverdue();
        $filter = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:All,Unpaid,Paid,Overdue'],
            'search' => ['nullable', 'string', 'max:50'],
        ]);
        $month = isset($filter['month']) ? Carbon::createFromFormat('Y-m-d', $filter['month'].'-01')->startOfMonth() : today()->startOfMonth();
        $settings = Setting::current();

        // Leases whose next bill can be generated now (7 days before the due date, or overdue to generate).
        $toBill = Lease::with(['room', 'tenant', 'occupants', 'appliances'])->where('status', 'Active')->get()
            ->filter(fn (Lease $l) => $this->billing->canGenerateNow($l))
            ->map(function (Lease $l) use ($settings, $concerns) {
                $dueDate = $this->billing->nextDueDate($l);
                [$start, $end] = $this->billing->periodFor($l->move_in_date, $l->due_day, $dueDate);
                $isMetered = (bool) ($l->room?->is_metered ?? false);
                $lastReading = $isMetered ? $this->billing->lastReading($l->room_id) : null;
                $occupants = $this->billing->occupantCount($l, $start, $end);
                $appliances = $isMetered ? 0 : $this->billing->applianceCount($l, $start, $end);
                $chargeable = $concerns->chargeable($l);

                return [
                    'lease' => $l,
                    'due_date' => $dueDate,
                    'last_reading' => $lastReading,
                    'period_start' => $start,
                    'period_end' => $end,
                    'occupants' => $occupants,
                    'appliances' => $appliances,
                    'rent_amount' => (float) $l->monthly_rent,
                    'water_amount' => round($occupants * (float) $settings->water_rate, 2),
                    'appliance_amount' => round($appliances * (float) $settings->appliance_fee, 2),
                    'chargeable_concerns' => $chargeable,
                ];
            })
            ->sortBy(fn ($row) => $row['due_date']->toDateString().($row['lease']->room?->room_no ?? ''))
            ->values();

        $billsQuery = Bill::with(['lease.room', 'lease.tenant', 'meterReading', 'concerns', 'payments.user', 'user'])
            ->where('is_initial', false)
            ->whereYear('due_date', $month->year)
            ->whereMonth('due_date', $month->month);

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

        foreach ($bills as $bill) {
            $bill->due_today = $bill->status === 'Paid' ? null : $penalties->forBill($bill, today());
            $bill->can_pay = $payments->canPay($bill);
        }

        $meteredBills = $allBills->filter(fn ($b) => $b->meterReading !== null);
        $totalKwh = $meteredBills->sum(fn ($b) => max(0, (float) $b->meterReading->current_reading - (float) $b->meterReading->previous_reading));

        $stats = [
            'total_amount' => (float) $allBills->sum(fn ($b) => (float) $b->total_amount),
            'total_count' => $allBills->count(),
            'paid_amount' => (float) $allBills->where('status', 'Paid')->sum(fn ($b) => (float) $b->total_amount),
            'paid_count' => $allBills->where('status', 'Paid')->count(),
            'unpaid_amount' => (float) $allBills->whereIn('status', ['Unpaid', 'Overdue'])->sum(fn ($b) => (float) $b->total_amount),
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

        $toBillMap = [];
        foreach ($toBill as $row) {
            $l = $row['lease'];
            $toBillMap[$l->lease_id] = [
                'lease_id' => $l->lease_id,
                'room_no' => $l->room->room_no,
                'room_type' => $l->room->room_type,
                'is_metered' => (bool) $l->room->is_metered,
                'tenant' => $l->tenant->full_name,
                'due_date' => $row['due_date']->toDateString(),
                'due_date_fmt' => $row['due_date']->format('j M Y'),
                'period_fmt' => $row['period_start']->format('j M').' – '.$row['period_end']->format('j M Y'),
                'rent' => (float) $row['rent_amount'],
                'occupants' => (int) $row['occupants'],
                'water' => (float) $row['water_amount'],
                'appliances' => (int) $row['appliances'],
                'appliance_amount' => (float) $row['appliance_amount'],
                'prev_reading' => (float) ($row['last_reading']?->current_reading ?? 0),
                'default_date' => min(today(), $row['due_date']->copy()->subDay())->toDateString(),
                'rate_per_kwh' => (float) ($settings->kwh_rate ?? 0),
                'concerns' => $row['chargeable_concerns']->map(fn ($c) => [
                    'id' => $c->concern_id,
                    'desc' => $c->description,
                    'category' => $c->category,
                    'cost' => (float) $c->cost,
                    'resolved' => $c->resolved_at ? $c->resolved_at->format('j M Y') : '',
                ])->values()->all(),
            ];
        }

        $billsMap = [];
        foreach ($bills as $b) {
            $r = $b->meterReading;
            $firstPayment = $b->payments->first();
            $rentPeriodEnd = $b->lease
                ? $this->billing->dueDateIn($b->lease->due_day, $b->due_date->copy()->startOfMonth()->addMonthNoOverflow())->subDay()
                : $b->due_date->copy()->addMonth()->subDay();

            $billsMap[$b->bill_id] = [
                'bill_id' => $b->bill_id,
                'lease_id' => $b->lease_id,
                'room_no' => $b->lease?->room?->room_no ?? '',
                'room_type' => $b->lease?->room?->room_type ?? '',
                'is_metered' => (bool) ($b->lease?->room?->is_metered ?? false),
                'tenant' => $b->lease?->tenant?->full_name ?? '',
                'due_date_fmt' => $b->due_date->format('j M Y'),
                'due_date' => $b->due_date->toDateString(),
                'period_fmt' => $b->period_start->format('j M').' – '.$b->due_date->copy()->subDay()->format('j M Y'),
                'rent_period_fmt' => $b->due_date->format('j M').' – '.$rentPeriodEnd->format('j M Y'),
                'status' => $b->status,
                'rent' => (float) $b->rent_amount,
                'water' => (float) $b->water_amount,
                'electricity' => (float) $b->electricity_amount,
                'appliances' => (float) $b->appliance_amount,
                'penalty' => (float) $b->penalty_amount,
                'other_charges' => (float) $b->other_charges,
                'total' => (float) $b->total_amount,
                'can_pay' => (bool) ($b->can_pay ?? true),
                'due_today' => $b->due_today,
                'reading' => $r ? [
                    'prev' => (float) $r->previous_reading,
                    'curr' => (float) $r->current_reading,
                    'kwh' => max(0, (float) $r->current_reading - (float) $r->previous_reading),
                    'rate' => (float) $r->rate_per_kwh,
                    'date' => $r->reading_date->format('j M Y'),
                    'source' => $r->source,
                ] : null,
                'payment' => $firstPayment ? [
                    'ack_no' => $firstPayment->ack_no,
                    'mode' => $firstPayment->mode,
                    'date' => $firstPayment->payment_date->format('j M Y'),
                    'amount' => (float) $firstPayment->amount,
                    'show_url' => route('payments.show', $firstPayment),
                ] : null,
                'concerns' => $b->concerns->map(fn ($c) => [
                    'desc' => $c->description,
                    'category' => $c->category,
                    'cost' => (float) $c->cost,
                ])->values()->all(),
            ];
        }

        return view('bills.index', [
            'month' => $month,
            'toBill' => $toBill,
            'bills' => $bills,
            'totals' => [
                'count' => $bills->count(),
                'rent' => $bills->sum(fn ($b) => (float) $b->rent_amount),
                'water' => $bills->sum(fn ($b) => (float) $b->water_amount),
                'utility' => $bills->sum(fn ($b) => (float) $b->electricity_amount + (float) $b->appliance_amount),
                'amount' => $bills->sum(fn ($b) => (float) $b->total_amount),
            ],
            'stats' => $stats,
            'settings' => $settings,
            'statusFilter' => $statusFilter,
            'searchQuery' => $searchQuery,
            'sources' => MeterReading::SOURCES,
            'toBillMap' => $toBillMap,
            'billsMap' => $billsMap,
        ]);
    }

    /** Generate form + statement preview. For a metered room the reading is entered, then "Show statement". */
    public function create(Request $request, ConcernService $concerns): View|RedirectResponse
    {
        $leaseId = $request->integer('lease');
        if (! $leaseId) {
            return redirect()->route('bills.index')->withErrors(['lease' => 'Please select a lease to generate a bill.']);
        }

        $lease = Lease::with(['room', 'tenant'])->find($leaseId);
        if (! $lease) {
            return redirect()->route('bills.index')->withErrors(['lease' => 'The selected lease could not be found.']);
        }

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
            if ($validator->fails()) {
                $errors = $validator->errors();
            } else {
                $reading = $validator->validated();
            }
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

        return redirect()->route('bills.index', ['month' => $bill->due_date->format('Y-m'), 'statement' => $bill->bill_id])
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
