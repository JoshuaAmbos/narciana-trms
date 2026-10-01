@extends('layouts.app')

@section('title', 'Move-out settlements')

@section('content')
    <form method="GET" action="{{ route('reports.moveouts') }}" class="panel no-print report-filters" novalidate>
        @include('reports._range')
        @include('reports._actions')
    </form>

    <div class="panel printable">
        @include('reports._head', ['title' => 'Move-out settlements', 'period' => $from->format('j M Y').' to '.$to->format('j M Y')])

        <div class="cards">
            <div class="card"><div class="n">{{ $rows->count() }}</div><div class="l">{{ Str::plural('Move-out', $rows->count()) }}</div></div>
            <div class="card"><div class="n">@peso($unpaid + $damage)</div><div class="l">Deducted (bills + damage)</div></div>
            <div class="card"><div class="n green">@peso($refund)</div><div class="l">Refunds</div></div>
            <div class="card"><div class="n red">@peso($excess)</div><div class="l">Excess owed by tenants</div></div>
        </div>

        @if ($rows->isEmpty())
            <p class="muted">No move-outs in this period.</p>
        @else
            <div class="table-wrap">
                <table class="fit">
                    <tr><th>Room / tenant</th><th>Moved out</th><th class="r">Refundable deposit</th><th class="r">Bills + damage</th><th class="r">Refund / excess</th><th>Status</th></tr>
                    @foreach ($rows as $row)
                        @php([$m, $f] = [$row['moveout'], $row['figures']])
                        <tr>
                            <td class="col-who"><b>{{ $m->lease->room->room_no }}</b> <a href="{{ route('moveouts.show', $m) }}">{{ $m->lease->tenant->full_name }}</a></td>
                            <td class="nowrap">{{ $m->actual_date?->format('j M Y') ?? 'planned '.$m->planned_date->format('j M Y') }}</td>
                            <td class="money">@peso($f['refundable'])</td>
                            <td class="money">@peso($f['unpaid_total'] + $f['damage_total'])
                                <span class="sub">bills @peso($f['unpaid_total']) · damage @peso($f['damage_total'])</span></td>
                            <td class="money">
                                @if ($f['excess'] > 0)<span class="red">owes @peso($f['excess'])</span>@else @peso($f['refund']) @endif
                                @if ($m->refund_due_date && $f['refund'] > 0)<span class="sub">due {{ $m->refund_due_date->format('j M Y') }}</span>@endif
                            </td>
                            <td><x-moveout-status :moveout="$m" />
                                @if ($f['early'])<span class="sub red">Early departure: deposit forfeited</span>@endif</td>
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td colspan="2">Total</td><td class="money">@peso($refundable)</td><td class="money">@peso($unpaid + $damage)</td>
                        <td class="money">@peso($refund)@if ($excess > 0)<span class="sub red">owed @peso($excess)</span>@endif</td><td></td></tr>
                </table>
            </div>
            <p class="muted">Pending move-outs show the figures computed so far; approved ones show the approved figures. Open a tenant for the itemized statement.</p>
        @endif
    </div>
@endsection
