<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillRequest;
use App\Http\Requests\FinalBillRequest;
use App\Models\MeterReading;
use App\Models\Moveout;
use App\Services\BillingService;
use App\Services\ConcernService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** Final bill at move-out: the last period's utilities, rent ₱0.00 (decision 16). Owner/Manager. */
class FinalBillController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function create(Request $request, Moveout $moveout, ConcernService $concerns): View|RedirectResponse
    {
        $moveout->load(['lease.room', 'lease.tenant']);
        $lease = $moveout->lease;

        if (! $moveout->isPending() || ! $moveout->actual_date || $moveout->final_bill_id
            || ! $this->billing->regularBillsDone($lease, $moveout->actual_date)
            || ! $this->billing->finalPeriod($lease, $moveout->actual_date)) {
            return redirect()->route('moveouts.show', $moveout)->withErrors(['final' => 'The final bill cannot be generated now (see the steps on this page).']);
        }

        $metered = (bool) $lease->room->is_metered;
        $last = $metered ? $this->billing->lastReading($lease->room_id) : null;
        $input = [
            'previous_reading' => $request->input('previous_reading', $last?->current_reading),
            'current_reading' => $request->input('current_reading'),
            'reading_date' => $request->input('reading_date', $moveout->actual_date->toDateString()),
            'source' => $request->input('source', 'Meter'),
        ];

        $reading = null;
        $errors = null;
        if ($metered && $request->has('current_reading')) {
            $validator = Validator::make($input, BillRequest::readingRules(), BillRequest::readingMessages());
            $validator->fails() ? $errors = $validator->errors() : $reading = $validator->validated();
        }
        $ticked = array_map('intval', (array) $request->input('concerns', []));

        return view('moveouts.final-bill', [
            'moveout' => $moveout,
            'lease' => $lease,
            'metered' => $metered,
            'lastReading' => $last,
            'input' => $input,
            'readingErrors' => $errors,
            'chargeable' => $concerns->chargeable($lease),
            'ticked' => $ticked,
            'statement' => $this->billing->finalPreview($lease, $moveout->actual_date, $reading, $ticked),
            'ready' => ! $metered || $reading !== null,
            'sources' => MeterReading::SOURCES,
        ]);
    }

    public function store(FinalBillRequest $request, Moveout $moveout): RedirectResponse
    {
        $bill = $this->billing->generateFinal($moveout, $request->reading(), (array) $request->validated('concerns', []), $request->user());

        return redirect()->route('moveouts.show', $moveout)
            ->with('status', 'Final bill generated: ₱'.number_format((float) $bill->total_amount, 2).'. It is included in the unpaid balance.');
    }
}
