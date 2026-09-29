@extends('layouts.app')

@section('title', 'Payments')

@section('content')
    @error('bill') <div class="alert alert-err">{{ $message }}</div> @enderror

    <div class="panel no-print">
        <h2>Bills to collect <span class="muted">Oldest first · full payment only</span></h2>
        @if ($toCollect->isEmpty())
            <p class="muted">Every bill is paid.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Due date</th><th>Room</th><th>Tenant</th><th class="r">Bill</th><th>Status</th><th class="r">Penalty so far</th><th class="r">Due if paid today</th><th></th></tr>
                    @foreach ($toCollect as $row)
                        @php($b = $row['bill'])
                        <tr>
                            <td class="nowrap">{{ $b->due_date->format('j M Y') }}</td>
                            <td><b>{{ $b->lease->room->room_no }}</b></td>
                            <td>{{ $b->lease->tenant->full_name }}</td>
                            <td class="money">@peso((float) $b->total_amount - (float) $b->penalty_amount)</td>
                            <td>
                                @if ($row['today']['days_late'] > 0)
                                    <span class="tag t-red">Overdue {{ $row['today']['days_late'] }} {{ Str::plural('day', $row['today']['days_late']) }}</span>
                                @elseif ($b->due_date->gte(today()))
                                    <span class="tag t-amber">Due {{ $b->due_date->isToday() ? 'today' : 'in '.today()->diffInDays($b->due_date).' '.Str::plural('day', today()->diffInDays($b->due_date)) }}</span>
                                @else
                                    <span class="tag t-amber">In grace period</span>
                                @endif
                            </td>
                            <td class="money">@peso($row['today']['penalty'])</td>
                            <td class="money"><b>@peso($row['today']['total'])</b></td>
                            <td class="actions">
                                @if ($row['can_pay'])
                                    <a href="{{ route('payments.create', ['bill' => $b->bill_id]) }}" class="btn">Record payment</a>
                                @else
                                    <span class="muted small">older bill first</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    <div class="panel printable">
        <h2>
            Collections, {{ $month->format('F Y') }}
            <span class="month-nav no-print">
                <a href="{{ route('payments.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn sec">‹</a>
                <a href="{{ route('payments.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn sec">›</a>
                <button type="button" class="btn sec" onclick="window.print()">Print</button>
            </span>
        </h2>

        <div class="cards">
            <div class="card"><div class="n">@peso($totals['All'])</div><div class="l">Collected in {{ $month->format('F Y') }}</div></div>
            <div class="card"><div class="n">@peso($totals['Cash'])</div><div class="l">Cash</div></div>
            <div class="card"><div class="n">@peso($totals['GCash'])</div><div class="l">GCash</div></div>
            <div class="card"><div class="n">{{ $collections->count() }}</div><div class="l">{{ Str::plural('payment', $collections->count()) }}
                @foreach ($byReceiver as $role => $amount) · {{ $role }} @peso($amount) @endforeach</div></div>
        </div>

        @if ($collections->isEmpty())
            <p class="muted">No payments in {{ $month->format('F Y') }}.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Date</th><th>ACK no.</th><th>Room</th><th>Tenant</th><th>Mode</th><th>Received by</th><th class="r">Amount</th></tr>
                    @foreach ($collections as $p)
                        <tr>
                            <td class="nowrap">{{ $p->payment_date->format('j M') }}</td>
                            <td><a href="{{ route('payments.show', $p) }}">{{ $p->ack_no }}</a></td>
                            <td><b>{{ $p->bill->lease->room->room_no }}</b></td>
                            <td>{{ $p->bill->lease->tenant->full_name }}</td>
                            <td>{{ $p->mode }}@if ($p->gcash_ref_no)<span class="sub nowrap">ref. {{ $p->gcash_ref_no }}</span>@endif</td>
                            <td>{{ $p->user->role }}</td>
                            <td class="money">@peso($p->amount)</td>
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td colspan="6">Total (Cash @peso($totals['Cash']) · GCash @peso($totals['GCash']))</td><td class="money">@peso($totals['All'])</td></tr>
                </table>
            </div>
        @endif
    </div>
@endsection
