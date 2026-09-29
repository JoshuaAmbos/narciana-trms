@extends('layouts.app')

@section('title', 'Record payment')

@section('content')
    @error('bill') <div class="alert alert-err">{{ $message }}</div> @enderror

    <div class="grid2">
        <div class="panel">
            <h2>Room {{ $bill->lease->room->room_no }} <span class="muted">{{ $bill->lease->tenant->full_name }}</span></h2>

            {{-- The penalty depends on the payment date: "Show amount" recomputes it without saving. --}}
            <form method="GET" action="{{ route('payments.create') }}" class="date-check no-print">
                <input type="hidden" name="bill" value="{{ $bill->bill_id }}">
                <input type="hidden" name="mode" value="{{ $input['mode'] }}">
                <div class="f {{ $dateOk ? '' : 'has-err' }}">
                    <label for="check_date">Payment date</label>
                    <input id="check_date" name="payment_date" type="date" max="{{ today()->toDateString() }}" value="{{ $input['payment_date'] }}">
                    @unless ($dateOk)<div class="err">Choose a date that is not in the future.</div>@endunless
                </div>
                <button type="submit" class="btn sec">Show amount</button>
            </form>

            <table class="sum amount-box">
                <tr><td>Bill total (due {{ $bill->due_date->format('j M Y') }})</td><td class="money">@peso((float) $bill->total_amount - (float) $bill->penalty_amount)</td></tr>
                <tr>
                    <td>Penalty
                        @if ($due['days_late'] > 0)
                            <span class="sub red">{{ $due['days_late'] }} {{ Str::plural('day', $due['days_late']) }} late after the grace period</span>
                        @else
                            <span class="sub">paid within the grace period: no penalty</span>
                        @endif
                    </td>
                    <td class="money">@peso($due['penalty'])</td>
                </tr>
                <tr class="tot"><td>Amount to receive</td><td class="money">@peso($due['total'])</td></tr>
            </table>

            <form method="POST" action="{{ route('payments.store') }}" novalidate>
                @csrf
                <input type="hidden" name="bill_id" value="{{ $bill->bill_id }}">
                <input type="hidden" name="payment_date" value="{{ $input['payment_date'] }}">

                <div class="row">
                    <div class="f @error('mode') has-err @enderror">
                        <label for="mode">Mode</label>
                        <select id="mode" name="mode">
                            @foreach ($modes as $mode)
                                <option value="{{ $mode }}" @selected(old('mode', $input['mode']) === $mode)>{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="f @error('gcash_ref_no') has-err @enderror" id="gcash-ref"
                         @if (old('mode', $input['mode']) !== 'GCash') hidden @endif>
                        <label for="gcash_ref_no">GCash reference no. (13 digits)</label>
                        <input id="gcash_ref_no" name="gcash_ref_no" type="text" inputmode="numeric" maxlength="20" value="{{ old('gcash_ref_no', $input['gcash_ref_no']) }}">
                        @error('gcash_ref_no') <div class="err">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="f @error('amount') has-err @enderror">
                    <label for="amount">Amount received (₱)</label>
                    <input id="amount" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" value="{{ old('amount') }}" autocomplete="off">
                    <div class="hint">Type the amount actually received. It must be exactly @peso($due['total']) — no partial payments.</div>
                    @error('amount') <div class="err">{{ $message }}</div> @enderror
                    @error('payment_date') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn" @disabled(! $dateOk)>Save and issue acknowledgment</button>
                    <a href="{{ route('payments.index') }}" class="btn sec">Cancel</a>
                </div>
                <p class="muted">Received by: <b>{{ auth()->user()->role }}</b> (you).</p>
            </form>
        </div>

        <div class="panel">
            <h2>Billing statement</h2>
            @include('bills._statement', ['statement' => $statement, 'lease' => $bill->lease])
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('mode').addEventListener('change', function () {
                document.getElementById('gcash-ref').hidden = this.value !== 'GCash';
            });
        </script>
    @endpush
@endsection
