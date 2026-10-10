<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\BillingService;
use App\Services\DelinquencyService;
use App\Services\PaymentService;
use App\Services\PenaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** Payments (Owner/Manager only; see routes). */
class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private PenaltyService $penalties,
        private DelinquencyService $delinquency,
    ) {}

    public function index(Request $request): View
    {
        $this->payments->refreshOverdue();

        $filter = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($filter['month']) ? Carbon::createFromFormat('Y-m-d', $filter['month'].'-01') : today()->startOfMonth();

        $breachedLeases = $this->delinquency->breachedLeases();
        $breachedLeaseIds = $breachedLeases->pluck('lease.lease_id')->all();

        // Bills still to collect, oldest first, with what they would cost if paid today.
        $toCollect = Bill::with(['lease.room', 'lease.tenant'])->where('status', '!=', 'Paid')->orderBy('due_date')->get()
            ->map(fn (Bill $b) => [
                'bill' => $b,
                'today' => $this->penalties->forBill($b, today()),
                'can_pay' => $this->payments->canPay($b),
                'is_breached' => in_array($b->lease_id, $breachedLeaseIds, true),
            ]);

        $collections = Payment::with(['bill.lease.room', 'bill.lease.tenant', 'user'])
            ->whereYear('payment_date', $month->year)->whereMonth('payment_date', $month->month)
            ->orderBy('payment_date')->orderBy('payment_id')->get();

        $sum = fn ($items) => round($items->sum(fn (Payment $p) => (float) $p->amount), 2);

        $breachedLeasesMap = $breachedLeases->mapWithKeys(function ($breach) {
            $l = $breach['lease'];
            $deficit = max(0, (float) $breach['total_arrears'] - (float) $breach['total_deposit']);

            return [$l->lease_id => [
                'lease_id' => $l->lease_id,
                'room_no' => $l->room->room_no,
                'tenant_name' => $l->tenant->full_name,
                'unpaid_bills_sum' => (float) $breach['base_arrears'],
                'penalties_sum' => (float) $breach['penalty_arrears'],
                'total_arrears' => (float) $breach['total_arrears'],
                'security_deposit' => (float) $breach['total_deposit'],
                'net_deficit' => (float) $deficit,
                'lease_url' => route('leases.show', $l),
                'terminate_url' => route('leases.terminate-breach', $l),
            ]];
        })->all();

        return view('payments.index', [
            'month' => $month,
            'breachedLeases' => $breachedLeases,
            'breachedLeasesMap' => $breachedLeasesMap,
            'toCollect' => $toCollect,
            'collections' => $collections,
            'totals' => [
                'Cash' => $sum($collections->where('mode', 'Cash')),
                'GCash' => $sum($collections->where('mode', 'GCash')),
                'All' => $sum($collections),
            ],
            'byReceiver' => $collections->groupBy(fn (Payment $p) => $p->user->role)->map($sum),
        ]);
    }

    /** Record-payment form. The penalty depends on the payment date, so "Show amount" recomputes it (GET). */
    public function create(Request $request, BillingService $billing): View|RedirectResponse
    {
        $bill = Bill::with(['lease.room', 'lease.tenant'])->findOrFail($request->integer('bill'));

        if ($bill->status === 'Paid') {
            return redirect()->route('bills.show', $bill)->withErrors(['bill' => 'This bill is already paid.']);
        }
        if ($older = $this->payments->olderUnpaid($bill)) {
            return redirect()->route('payments.index')->withErrors([
                'bill' => "Room {$bill->lease->room->room_no}: pay the older bill first (due {$older->due_date->format('j M Y')}).",
            ]);
        }

        $input = [
            'payment_date' => $request->input('payment_date', today()->toDateString()),
            'mode' => $request->input('mode', 'Cash'),
            'gcash_ref_no' => $request->input('gcash_ref_no'),
        ];
        $dateOk = Validator::make($input, ['payment_date' => PaymentRequest::paymentRules()['payment_date']])->passes();

        return view('payments.create', [
            'bill' => $bill,
            'statement' => $billing->statementForBill($bill),
            'input' => $input,
            'dateOk' => $dateOk,
            'due' => $this->penalties->forBill($bill, $dateOk ? Carbon::parse($input['payment_date']) : today()),
            'modes' => Payment::MODES,
        ]);
    }

    public function store(PaymentRequest $request): RedirectResponse
    {
        $bill = Bill::findOrFail($request->validated('bill_id'));
        $payment = $this->payments->record($bill, $request->validated(), $request->user());

        return redirect()->route('payments.show', $payment)
            ->with('status', "Payment recorded. Acknowledgment {$payment->ack_no}. The bill is now Paid.");
    }

    /** Printable acknowledgment. */
    public function show(Payment $payment, BillingService $billing): View
    {
        $payment->load(['bill.lease.room', 'bill.lease.tenant', 'user']);

        return view('payments.show', [
            'payment' => $payment,
            'statement' => $billing->statementForBill($payment->bill),
            'daysLate' => $this->penalties->daysLate($payment->bill->due_date, $payment->payment_date,
                (int) Setting::current()->grace_days),
            'canVoid' => Gate::allows('void', $payment),
        ]);
    }

    /** Void a payment recorded by mistake (Owner only). */
    public function destroy(Payment $payment, Request $request): RedirectResponse
    {
        Gate::authorize('void', $payment);
        $bill = $payment->bill;
        $ack = $payment->ack_no;
        $this->payments->void($payment, $request->user());

        return redirect()->route('bills.show', $bill)->with('status', "Payment {$ack} was voided. The bill is unpaid again; the number {$ack} will not be used again.");
    }
}
