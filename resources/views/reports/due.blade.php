@extends('layouts.app')

@section('title', 'Due and overdue tenants')

@section('content')
    <div class="panel no-print report-filters">
        <p class="muted">Bills not paid yet with a due date up to {{ $until->format('j M Y') }},
            with the penalty if paid today. The penalty is saved on the bill only when it is paid.</p>
        @include('reports._actions', ['show' => false])
    </div>

    <div class="panel printable">
        @include('reports._head', ['title' => 'Due and overdue tenants', 'period' => 'As of '.$today->format('j F Y')])

        <div class="cards">
            <div class="card"><div class="n red">{{ $overdue_tenants }}</div><div class="l">Overdue {{ Str::plural('tenant', $overdue_tenants) }}</div></div>
            <div class="card"><div class="n red">@peso($overdue_total)</div><div class="l">Overdue, incl. penalties</div></div>
            <div class="card"><div class="n amber">@peso($due_total)</div><div class="l">Due within 7 days (not late)</div></div>
            <div class="card"><div class="n">@peso($total)</div><div class="l">Total to collect today</div></div>
        </div>

        @if ($rows->isEmpty())
            <p class="muted">Nothing due or overdue.</p>
        @else
            <div class="table-wrap">
                <table class="fit">
                    <tr><th>Room / tenant</th><th>Due date</th><th>Status</th><th class="r">Bill</th><th class="r">Penalty</th><th class="r">Due today</th></tr>
                    @foreach ($rows as $row)
                        @php($b = $row['bill'])
                        <tr>
                            <td><b>{{ $b->lease->room->room_no }}</b> {{ $b->lease->tenant->full_name }}
                                <span class="sub nowrap">{{ $b->lease->tenant->contact_no }}</span></td>
                            <td class="nowrap">{{ $b->due_date->format('j M Y') }}</td>
                            <td>
                                @if ($row['days_late'] > 0)
                                    <span class="tag t-red">Overdue {{ $row['days_late'] }} {{ Str::plural('day', $row['days_late']) }}</span>
                                @elseif ($row['days_to_due'] > 0)
                                    <span class="tag t-amber">Due in {{ $row['days_to_due'] }} {{ Str::plural('day', $row['days_to_due']) }}</span>
                                @elseif ($row['days_to_due'] === 0)
                                    <span class="tag t-amber">Due today</span>
                                @else
                                    <span class="tag t-amber">In grace period</span>
                                @endif
                            </td>
                            <td class="money">@peso((float) $b->total_amount - (float) $b->penalty_amount)</td>
                            <td class="money">@peso($row['penalty'])</td>
                            <td class="money"><b>@peso($row['total'])</b></td>
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td colspan="4">Total</td><td class="money">@peso($penalty_total)</td><td class="money">@peso($total)</td></tr>
                </table>
            </div>
        @endif
    </div>
@endsection
