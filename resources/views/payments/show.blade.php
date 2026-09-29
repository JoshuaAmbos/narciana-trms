@extends('layouts.app')

@section('title', 'Acknowledgment '.$payment->ack_no)

@section('content')
    @error('payment') <div class="alert alert-err">{{ $message }}</div> @enderror
    @php($bill = $payment->bill)
    @php($a = $statement['amounts'])

    <div class="panel form-panel narrow-print printable">
        <h2 class="no-print">
            <span>Acknowledgment</span>
            <span>
                <button type="button" class="btn" onclick="window.print()">Print / Save as PDF</button>
                <a href="{{ route('payments.index', ['month' => $payment->payment_date->format('Y-m')]) }}" class="btn sec">Back</a>
            </span>
        </h2>

        <div class="statement ack">
            <div class="statement-head">
                <div><b>Narciana House Space Rental</b><br><span class="muted">Km. 6 Carnation St., Buhangin, Davao City</span></div>
                <div class="r"><b>Acknowledgment No.</b><br><span class="ack-no">{{ $payment->ack_no }}</span></div>
            </div>
            <table class="sum">
                <tr><td>Date received</td><td class="r">{{ $payment->payment_date->format('j F Y') }}</td></tr>
                <tr><td>Received from</td><td class="r">{{ $bill->lease->tenant->full_name }}, Room {{ $bill->lease->room->room_no }}</td></tr>
                <tr><td>For the bill due</td><td class="r">{{ $bill->due_date->format('j M Y') }}
                    <span class="sub">rent {{ $statement['rent_from']->format('j M') }} – {{ $statement['rent_to']->format('j M Y') }};
                        utilities {{ $statement['period_start']->format('j M') }} – {{ $statement['period_end']->format('j M Y') }}</span></td></tr>
                <tr><td>Rent</td><td class="money">@peso($a['rent_amount'])</td></tr>
                <tr><td>Water</td><td class="money">@peso($a['water_amount'])</td></tr>
                @if ($statement['metered'])
                    <tr><td>Electricity ({{ number_format($statement['kwh_used'], 2) }} kWh)</td><td class="money">@peso($a['electricity_amount'])</td></tr>
                @else
                    <tr><td>Appliance surcharges</td><td class="money">@peso($a['appliance_amount'])</td></tr>
                @endif
                @if ((float) $a['other_charges'] > 0)
                    <tr><td>Other charges</td><td class="money">@peso($a['other_charges'])</td></tr>
                @endif
                <tr><td>Penalty @if ((float) $a['penalty_amount'] > 0)<span class="sub">{{ $daysLate }} {{ Str::plural('day', $daysLate) }} late</span>@endif</td>
                    <td class="money">@peso($a['penalty_amount'])</td></tr>
                <tr><td>Mode</td><td class="r">{{ $payment->mode }}@if ($payment->gcash_ref_no) (ref. {{ $payment->gcash_ref_no }})@endif</td></tr>
                <tr><td>Received by</td><td class="r">{{ $payment->user->role }}</td></tr>
                <tr class="tot"><td>Amount received</td><td class="money">@peso($payment->amount)</td></tr>
            </table>
        </div>
        <p class="muted print-note">Full payment of the bill above. Keep this acknowledgment as proof of payment.</p>

        @if ($canVoid)
            <form method="POST" action="{{ route('payments.destroy', $payment) }}" class="danger-zone no-print"
                  onsubmit="return confirm('Void {{ $payment->ack_no }}? The bill becomes unpaid again and the penalty is removed. The number {{ $payment->ack_no }} will never be used again.')">
                @csrf @method('DELETE')
                <button type="submit" class="btn danger">Void this payment</button>
                <span class="muted">Owner only: for a payment recorded by mistake. Audited.</span>
            </form>
        @endif
    </div>
@endsection
