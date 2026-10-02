@extends('layouts.app')

@section('title', 'Move-out: Room '.$lease->room->room_no)

@section('content')
    @foreach (['moveout', 'final'] as $key)
        @error($key) <div class="alert alert-err">{{ $message }}</div> @enderror
    @endforeach

    {{-- The five steps of a move-out (decision 18) --}}
    <ol class="steps no-print" aria-label="Move-out steps">
        @foreach ($steps as $step)
            <li class="{{ $step['state'] }}" @if ($step['state'] === 'current') aria-current="step" @endif>
                <span class="step-n">{{ $step['state'] === 'done' ? '✓' : $step['n'] }}</span>
                <span><b>{{ $step['n'] }}. {{ $step['label'] }}</b><span class="sub">{{ $step['note'] }}</span></span>
            </li>
        @endforeach
    </ol>

    <div class="grid2">
        <div class="no-print">
            {{-- Step 1: Notice --}}
            <div class="panel">
                <h2>
                    <span>1. Notice — Room {{ $lease->room->room_no }}, {{ $lease->tenant->full_name }} <x-moveout-status :moveout="$moveout" /></span>
                    <a href="{{ route('leases.show', $lease) }}" class="btn sec">MOA</a>
                </h2>
                @if ($noticeWarning)<div class="alert alert-warn">{{ $noticeWarning }}</div>@endif
                @if ($moveout->early_departure)
                    <div class="alert alert-err">Early departure: minimum stay until {{ $minStayUntil->format('j M Y') }}. The deposit is forfeited (no refund).</div>
                @endif

                @if ($moveout->isPending())
                    <form method="POST" action="{{ route('moveouts.update', $moveout) }}" novalidate>
                        @csrf @method('PUT')
                        <div class="row">
                            <div class="f @error('notice_date') has-err @enderror">
                                <label for="notice_date">Notice given</label>
                                <input id="notice_date" name="notice_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('notice_date', $moveout->notice_date->toDateString()) }}">
                                @error('notice_date') <div class="err">{{ $message }}</div> @enderror
                            </div>
                            <div class="f @error('planned_date') has-err @enderror">
                                <label for="planned_date">Planned move-out</label>
                                <input id="planned_date" name="planned_date" type="date" value="{{ old('planned_date', $moveout->planned_date->toDateString()) }}">
                                @error('planned_date') <div class="err">{{ $message }}</div> @enderror
                            </div>
                            <div class="f @error('actual_date') has-err @enderror">
                                <label for="actual_date">Actually moved out</label>
                                <input id="actual_date" name="actual_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('actual_date', $moveout->actual_date?->toDateString()) }}"
                                       @if ($moveout->final_bill_id) readonly @endif>
                                @error('actual_date') <div class="err">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn sec">Save dates</button>
                            @if ($canCancel)
                                <button type="submit" form="cancel-notice" class="btn danger">Cancel notice (tenant stays)</button>
                            @endif
                        </div>
                    </form>
                    @if ($canCancel)
                        <form id="cancel-notice" method="POST" action="{{ route('moveouts.destroy', $moveout) }}"
                              onsubmit="return confirm('Cancel this notice? The room becomes Occupied again.')">@csrf @method('DELETE')</form>
                    @endif
                @else
                    <dl class="facts">
                        <div><dt>Notice given</dt><dd>{{ $moveout->notice_date->format('j M Y') }}</dd></div>
                        <div><dt>Planned</dt><dd>{{ $moveout->planned_date->format('j M Y') }}</dd></div>
                        <div><dt>Moved out</dt><dd>{{ $moveout->actual_date?->format('j M Y') }}</dd></div>
                    </dl>
                @endif
            </div>

            {{-- Step 2: Inspection (and the final bill) --}}
            <div class="panel">
                <h2>2. Inspection <span class="muted">compared with the pre-move-in video</span></h2>
                @if (! $moveout->actual_date)
                    <p class="muted">Record the actual move-out date (step 1) first. The room is inspected when the tenant leaves.</p>
                @else
                    <h3 class="sub-head">Final bill <span class="muted">last period's utilities, no rent</span></h3>
                    @if ($moveout->finalBill)
                        <p>Final bill: <a href="{{ route('bills.show', $moveout->finalBill) }}">@peso($moveout->finalBill->total_amount) due {{ $moveout->finalBill->due_date->format('j M Y') }}</a>
                            <x-bill-status :status="$moveout->finalBill->status" /></p>
                        @if ($moveout->isPending())<p class="muted">To change it, delete it from the bill page and generate it again.</p>@endif
                    @elseif (! $regularDone)
                        <p class="amber">First generate the regular bill due {{ $nextRegularDue->format('j M Y') }} (Billing), since it falls on or before the move-out date.</p>
                        <a href="{{ route('bills.create', ['lease' => $lease->lease_id]) }}" class="btn sec">Generate that bill</a>
                    @elseif (! $finalNeeded)
                        <p class="muted">No final bill needed: the last bill already covers the utilities until the move-out date.</p>
                    @elseif ($moveout->isPending())
                        <a href="{{ route('moveouts.final-bill.create', $moveout) }}" class="btn">Generate final bill</a>
                    @endif

                    <h3 class="sub-head">Damaged or missing items</h3>
                    @if ($moveout->items->isEmpty())
                        <p class="muted">No damaged or missing items recorded.</p>
                    @else
                        <table>
                            @foreach ($moveout->items as $item)
                                <tr>
                                    <td>{{ $item->description }}</td>
                                    <td class="money">@peso($item->cost)</td>
                                    <td class="actions">
                                        @if ($moveout->isPending())
                                            <form method="POST" action="{{ route('moveout-items.destroy', $item) }}" class="inline">@csrf @method('DELETE')
                                                <button type="submit" class="btn sec">Remove</button></form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                    @if ($moveout->isPending())
                        <form method="POST" action="{{ route('moveouts.items.store', $moveout) }}" class="add-row" novalidate>
                            @csrf
                            <div class="row">
                                <div class="f @error('description') has-err @enderror" style="flex: 3 1 220px">
                                    <label for="description">Damaged or missing item</label>
                                    <input id="description" name="description" type="text" maxlength="255" value="{{ old('description') }}" placeholder="e.g. Wall paint scratches near the bed, missing key">
                                    @error('description') <div class="err">{{ $message }}</div> @enderror
                                </div>
                                <div class="f @error('cost') has-err @enderror">
                                    <label for="cost">Cost (₱)</label>
                                    <input id="cost" name="cost" type="number" min="0.01" step="0.01" inputmode="decimal" value="{{ old('cost') }}">
                                    @error('cost') <div class="err">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <button type="submit" class="btn sec">+ Add item</button>
                        </form>
                    @endif

                    <div class="danger-zone">
                        @if ($moveout->inspected_at)
                            <span class="paid-note">✓ Inspection recorded {{ $moveout->inspected_at->format('j M Y, g:i A') }}</span>
                        @elseif ($moveout->isPending())
                            <form method="POST" action="{{ route('moveouts.inspect', $moveout) }}" class="inline"
                                  onsubmit="return confirm('Inspection done? ({{ $moveout->items->count() }} damaged or missing item(s) listed.) Items can still be added until the Owner approves.')">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn">Inspection done</button>
                            </form>
                            <span class="muted">Press this even when nothing is damaged.</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Step 3: Computation, 4: Owner approval, 5: Refund released --}}
        <div class="panel printable">
            <h2 class="no-print">
                3. Computation
                <button type="button" class="btn sec" onclick="window.print()">Print</button>
            </h2>
            @include('moveouts._statement')

            <div class="no-print settle-actions">
                @if ($moveout->isPending())
                    @if (! $moveout->actual_date)
                        <p class="muted">Enter the actual move-out date to settle. Until then the figures are an estimate.</p>
                    @elseif (! $moveout->inspected_at)
                        <p class="muted">Record the inspection (step 2) before computing. Until then the figures are an estimate.</p>
                    @elseif (! $finalReady)
                        <p class="muted">Generate the final bill (step 2) before computing.</p>
                    @else
                        <form method="POST" action="{{ route('moveouts.compute', $moveout) }}" class="inline">@csrf
                            <button type="submit" class="btn sec">Compute settlement</button></form>
                    @endif

                    <h3 class="sub-head">4. Owner approval</h3>
                    @if ($canApprove)
                        <form method="POST" action="{{ route('moveouts.approve', $moveout) }}" class="inline"
                              onsubmit="return confirm('Approve this settlement? Unpaid bills are closed from the deposit and the figures can no longer change.')">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn">Approve settlement (Owner)</button>
                        </form>
                    @elseif ($moveout->actual_date && $moveout->inspected_at && $finalReady)
                        <p class="muted">Waiting for the Owner's approval.</p>
                    @else
                        <p class="muted">After the inspection and the computation.</p>
                    @endif
                @else
                    <h3 class="sub-head">4. Owner approval <span class="tag t-green">Approved</span></h3>
                    @if ($moveout->status === 'Refunded')
                        <h3 class="sub-head">5. Refund released</h3>
                        <p>Refund of <b>@peso($moveout->refund_amount)</b> due by <b>{{ $moveout->refund_due_date->format('j M Y') }}</b>.</p>
                        @if ($canRelease)
                            <form method="POST" action="{{ route('moveouts.release', $moveout) }}"
                                  onsubmit="return confirm('Confirm the refund of ₱{{ number_format((float) $moveout->refund_amount, 2) }} was handed over? The lease ends and the room becomes Vacant.')">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn">Refund handed over: settle</button>
                            </form>
                        @endif
                    @elseif ($moveout->status === 'Excess billed')
                        <h3 class="sub-head">5. Excess paid</h3>
                        <p><span class="tag t-red">Excess billed</span> The tenant owes @peso($moveout->excess_amount).
                            Paying <a href="{{ route('bills.show', $moveout->excessBill) }}">the excess bill</a> (Payments) settles this move-out.</p>
                    @else
                        <h3 class="sub-head">5. {{ $moveout->excessBill ? 'Excess paid' : 'Refund released' }} <span class="tag t-grey">Settled</span></h3>
                        <p>Lease ended (moved out); the room is Vacant.
                            @if ($moveout->excessBill) Excess paid (<a href="{{ route('bills.show', $moveout->excessBill) }}">bill</a>). @endif</p>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
