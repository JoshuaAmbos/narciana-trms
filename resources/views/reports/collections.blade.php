@extends('layouts.app')

@section('title', 'Collection summary')

@section('content')
    <form method="GET" action="{{ route('reports.collections') }}" class="panel no-print report-filters" novalidate>
        <div class="f @error('month') has-err @enderror">
            <label for="month">Month</label>
            <input id="month" name="month" type="month" value="{{ $month->format('Y-m') }}">
            @error('month') <div class="err">{{ $message }}</div> @enderror
        </div>
        @include('reports._actions')
    </form>

    <div class="panel printable">
        @include('reports._head', ['title' => 'Monthly collection summary', 'period' => $month->format('F Y')])

        <div class="cards">
            <div class="card"><div class="n">@peso($totals['All'])</div><div class="l">Collected ({{ $payments->count() }} {{ Str::plural('payment', $payments->count()) }})</div></div>
            <div class="card"><div class="n">@peso($totals['Cash'])</div><div class="l">Cash</div></div>
            <div class="card"><div class="n">@peso($totals['GCash'])</div><div class="l">GCash</div></div>
            <div class="card"><div class="n">{{ $receivers->count() }}</div><div class="l">{{ Str::plural('Receiver', $receivers->count()) }}</div></div>
        </div>

        <h3 class="sub-head">Per receiver</h3>
        @if ($receivers->isEmpty())
            <p class="muted">No payments in {{ $month->format('F Y') }}.</p>
        @else
            <div class="table-wrap">
                <table class="fit">
                    <tr><th>Received by</th><th class="r">Payments</th><th class="r">Cash</th><th class="r">GCash</th><th class="r">Total</th></tr>
                    @foreach ($receivers as $r)
                        <tr>
                            <td>{{ $r['user']->full_name }} ({{ $r['user']->role }})</td>
                            <td class="r">{{ $r['count'] }}</td>
                            <td class="money">@peso($r['Cash'])</td>
                            <td class="money">@peso($r['GCash'])</td>
                            <td class="money"><b>@peso($r['All'])</b></td>
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td>Total</td><td class="r">{{ $payments->count() }}</td>
                        <td class="money">@peso($totals['Cash'])</td><td class="money">@peso($totals['GCash'])</td><td class="money">@peso($totals['All'])</td></tr>
                </table>
            </div>

            <h3 class="sub-head">Payments</h3>
            <div class="table-wrap">
                <table class="fit">
                    <tr><th>Date</th><th>ACK no.</th><th>Room / tenant</th><th>Mode</th><th>Received by</th><th class="r">Amount</th></tr>
                    @foreach ($payments as $p)
                        <tr>
                            <td class="nowrap">{{ $p->payment_date->format('j M') }}</td>
                            <td class="nowrap"><a href="{{ route('payments.show', $p) }}">{{ $p->ack_no }}</a></td>
                            <td><b>{{ $p->bill->lease->room->room_no }}</b> {{ $p->bill->lease->tenant->full_name }}</td>
                            <td>{{ $p->mode }}@if ($p->gcash_ref_no)<span class="sub nowrap">ref. {{ $p->gcash_ref_no }}</span>@endif</td>
                            <td>{{ $p->user->full_name }} <span class="sub">{{ $p->user->role }}</span></td>
                            <td class="money">@peso($p->amount)</td>
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td colspan="5">Total</td><td class="money">@peso($totals['All'])</td></tr>
                </table>
            </div>
        @endif

        @if ($settled->isNotEmpty())
            <h3 class="sub-head">Bills settled from deposit <span class="muted">not money received; not counted above</span></h3>
            <div class="table-wrap">
                <table class="fit">
                    <tr><th>Settled</th><th>Room / tenant</th><th>Bill due</th><th class="r">Amount</th></tr>
                    @foreach ($settled as $row)
                        <tr>
                            <td class="nowrap">{{ $row['date']->format('j M') }}</td>
                            <td><b>{{ $row['bill']->lease->room->room_no }}</b> {{ $row['bill']->lease->tenant->full_name }}</td>
                            <td class="nowrap">{{ $row['bill']->due_date->format('j M Y') }}</td>
                            <td class="money">@peso($row['bill']->total_amount)</td>
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td colspan="3">Total settled from deposits</td><td class="money">@peso($settled_total)</td></tr>
                </table>
            </div>
        @endif
    </div>
@endsection
