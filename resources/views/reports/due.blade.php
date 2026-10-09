@extends('layouts.app')

@section('title', 'Due And Overdue Tenants')

@section('content')
    <div class="panel no-print report-filters">
        <p class="muted" style="margin: 0;">
            Unpaid bills with due dates up to <b>{{ $until->format('j M Y') }}</b> (within 7 days),
            including computed late penalties if settled today. Penalties are committed to the bill upon payment recording.
        </p>
        @include('reports._actions', ['show' => false])
    </div>

    <div class="panel printable reports-container">
        @include('reports._head', ['title' => 'Due And Overdue Tenants', 'period' => 'As of '.$today->format('j F Y')])

        {{-- Delinquency KPI Cards --}}
        <div class="cards">
            <div class="card">
                <div class="n red">{{ $overdue_tenants }}</div>
                <div class="l">Overdue {{ Str::plural('Tenant', $overdue_tenants) }}</div>
            </div>
            <div class="card">
                <div class="n red">@peso($overdue_total)</div>
                <div class="l">Overdue Balance (With Penalties)</div>
            </div>
            <div class="card">
                <div class="n amber">@peso($due_total)</div>
                <div class="l">Due Within 7 Days (On Time)</div>
            </div>
            <div class="card">
                <div class="n">@peso($total)</div>
                <div class="l">Total Collectible Today</div>
            </div>
        </div>

        @if ($rows->isEmpty())
            <p class="muted">No outstanding due or overdue accounts found.</p>
        @else
            {{-- Landscape Tablet & Desktop Ledger Table --}}
            <div class="table-wrap report-table-wrap">
                <table class="fit">
                    <thead>
                        <tr>
                            <th>Room &amp; Tenant</th>
                            <th>Due Date</th>
                            <th>Account Status</th>
                            <th class="r">Base Bill</th>
                            <th class="r">Late Penalty</th>
                            <th class="r">Total Due Today</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($b = $row['bill'])
                            <tr class="report-row-clickable"
                                onclick="window.location='{{ route('bills.show', $b) }}'"
                                tabindex="0"
                                role="button"
                                aria-label="View bill due {{ $b->due_date->format('j M Y') }} for Room {{ $b->lease->room->room_no }}">
                                <td>
                                    <b>Room {{ $b->lease->room->room_no }}</b> — {{ $b->lease->tenant->full_name }}
                                    <span class="sub nowrap">{{ $b->lease->tenant->contact_no }}</span>
                                </td>
                                <td class="nowrap">{{ $b->due_date->format('j M Y') }}</td>
                                <td>
                                    @if ($row['days_late'] > 0)
                                        <span class="tag t-red">Overdue {{ $row['days_late'] }} {{ Str::plural('day', $row['days_late']) }}</span>
                                    @elseif ($row['days_to_due'] > 0)
                                        <span class="tag t-amber">Due in {{ $row['days_to_due'] }} {{ Str::plural('day', $row['days_to_due']) }}</span>
                                    @elseif ($row['days_to_due'] === 0)
                                        <span class="tag t-amber">Due Today</span>
                                    @else
                                        <span class="tag t-amber">In Grace Period</span>
                                    @endif
                                </td>
                                <td class="money">@peso((float) $b->total_amount - (float) $b->penalty_amount)</td>
                                <td class="money">@peso($row['penalty'])</td>
                                <td class="money"><b>@peso($row['total'])</b></td>
                            </tr>
                        @endforeach
                        <tr class="tot-row">
                            <td colspan="3">Total Outstanding</td>
                            <td class="money">@peso($rows->sum(fn ($r) => (float) $r['bill']->total_amount - (float) $r['bill']->penalty_amount))</td>
                            <td class="money">@peso($penalty_total)</td>
                            <td class="money">@peso($total)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Portrait Tablet (~712px - 800px) & Mobile Stacked Cards --}}
            <div class="report-cards">
                @foreach ($rows as $row)
                    @php($b = $row['bill'])
                    <div class="report-card"
                         onclick="window.location='{{ route('bills.show', $b) }}'"
                         tabindex="0"
                         role="button"
                         aria-label="View bill due {{ $b->due_date->format('j M Y') }}">
                        <div class="report-card-head">
                            <div>
                                <div class="report-card-title">
                                    Room {{ $b->lease->room->room_no }} — {{ $b->lease->tenant->full_name }}
                                </div>
                                <div class="report-card-sub">
                                    Due: <b>{{ $b->due_date->format('j M Y') }}</b> · Contact: {{ $b->lease->tenant->contact_no }}
                                </div>
                            </div>
                            <div class="report-card-badge">
                                @if ($row['days_late'] > 0)
                                    <span class="tag t-red">Overdue {{ $row['days_late'] }}d</span>
                                @elseif ($row['days_to_due'] > 0)
                                    <span class="tag t-amber">In {{ $row['days_to_due'] }}d</span>
                                @elseif ($row['days_to_due'] === 0)
                                    <span class="tag t-amber">Today</span>
                                @else
                                    <span class="tag t-amber">Grace</span>
                                @endif
                                <span class="report-chevron"><x-icon name="chevron-right" size="18" /></span>
                            </div>
                        </div>
                        <div class="report-card-grid">
                            <div class="report-card-metric">
                                <span class="lbl">Base Bill:</span>
                                <span class="val">@peso((float) $b->total_amount - (float) $b->penalty_amount)</span>
                            </div>
                            <div class="report-card-metric">
                                <span class="lbl">Late Penalty:</span>
                                <span class="val" style="color: var(--red);">@peso($row['penalty'])</span>
                            </div>
                        </div>
                        <div class="report-card-foot">
                            <span>Total Due If Paid Today:</span>
                            <span style="font-size: 15px; font-weight: 700; color: var(--navy);">@peso($row['total'])</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
