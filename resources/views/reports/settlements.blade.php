@extends('layouts.app')

@section('title', 'Move-Out Settlements')

@section('content')
    <form method="GET" action="{{ route('reports.settlements') }}" class="panel no-print report-filters" novalidate>
        @include('reports._range')
        @include('reports._actions')
    </form>

    <div class="panel printable reports-container">
        @include('reports._head', [
            'title' => 'Move-Out Settlements',
            'period' => ($from || $to) ? (($from?->format('j M Y') ?? 'Start') . ' to ' . ($to?->format('j M Y') ?? 'Today')) : 'All Departed Tenancies',
        ])

        <div class="cards">
            <div class="card">
                <div class="n">{{ $count }}</div>
                <div class="l">Departed {{ Str::plural('Tenancy', $count) }}</div>
            </div>
            <div class="card">
                <div class="n">@peso($total_deposits)</div>
                <div class="l">Security Deposits Held</div>
            </div>
            <div class="card">
                <div class="n amber">@peso($total_deductions)</div>
                <div class="l">Total Deductions</div>
            </div>
            <div class="card">
                <div class="n green">@peso($total_refunds)</div>
                <div class="l">Net Refunds Issued</div>
            </div>
        </div>

        @if ($rows->isEmpty())
            <p class="muted">No departed lease settlements found in this period.</p>
        @else
            {{-- Landscape Tablet & Desktop Ledger Table --}}
            <div class="table-wrap report-table-wrap">
                <table class="fit">
                    <thead>
                        <tr>
                            <th>Move-Out Date</th>
                            <th>Room &amp; Tenant</th>
                            <th class="r">Security Deposit</th>
                            <th class="r">Deductions</th>
                            <th class="r">Net Settlement</th>
                            <th class="actions text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr class="report-row">
                                <td class="nowrap">
                                    <b>{{ $r['move_out_date'] ? $r['move_out_date']->format('j M Y') : 'Ended' }}</b>
                                    <span class="sub">Moved In {{ $r['move_in_date']->format('j M Y') }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('reports.statement', ['lease' => $r['lease']->lease_id]) }}" class="font-semibold text-blue-600 hover:underline">Room {{ $r['room_no'] }}</a> ({{ $r['room_type'] }})
                                    <span class="sub">{{ $r['tenant_name'] }} · {{ $r['tenant_contact'] }}</span>
                                </td>
                                <td class="money">
                                    <b>@peso($r['total_deposit'])</b>
                                    <span class="sub nowrap">
                                        Maint: @peso($r['deposit_maintenance']) · Dmg: @peso($r['deposit_damage'])
                                    </span>
                                    <span class="sub nowrap">
                                        Bills: @peso($r['deposit_bills']) · Keys: @peso($r['deposit_keys'])
                                    </span>
                                </td>
                                <td class="money">
                                    <b>@peso($r['deductions']['total'])</b>
                                    <span class="sub nowrap">
                                        Fee: @peso($r['deductions']['maintenance'])
                                        @if ($r['deductions']['damages'] > 0) · Dmg: @peso($r['deductions']['damages']) @endif
                                        @if ($r['deductions']['bills'] > 0) · Util: @peso($r['deductions']['bills']) @endif
                                    </span>
                                </td>
                                <td class="money">
                                    @if ($r['status'] === 'Refund')
                                        <span class="tag t-green" style="font-size: 13px; font-weight: 700;">
                                            Refund: @peso($r['net_refund'])
                                        </span>
                                    @elseif ($r['status'] === 'Excess')
                                        <span class="tag t-red" style="font-size: 13px; font-weight: 700;">
                                            Excess Due: @peso(abs($r['net_refund']))
                                        </span>
                                    @else
                                        <span class="tag t-sub" style="font-size: 13px; font-weight: 700;">
                                            Fully Settled
                                        </span>
                                    @endif
                                </td>
                                <td class="actions">
                                    <div class="table-action-group">
                                        <a href="{{ route('reports.statement', ['lease' => $r['lease']->lease_id]) }}" class="btn sec" title="View billing statement">Statement</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="tot-row">
                            <td colspan="2">Total ({{ $count }} {{ Str::plural('settlement', $count) }})</td>
                            <td class="money">@peso($total_deposits)</td>
                            <td class="money">@peso($total_deductions)</td>
                            <td class="money">
                                <span class="tag t-green">Refunds: @peso($total_refunds)</span>
                                @if ($total_excess > 0)
                                    <span class="tag t-red" style="margin-top: 4px;">Excess: @peso($total_excess)</span>
                                @endif
                            </td>
                            <td class="actions"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Portrait Tablet (~712px - 800px) & Mobile Stacked Cards --}}
            <div class="report-cards">
                @foreach ($rows as $r)
                    <div class="report-card">
                        <div class="report-card-head">
                            <div>
                                <div class="report-card-title">
                                    <a href="{{ route('reports.statement', ['lease' => $r['lease']->lease_id]) }}" class="font-semibold text-blue-600 hover:underline">Room {{ $r['room_no'] }} — {{ $r['tenant_name'] }}</a>
                                </div>
                                <div class="report-card-sub">
                                    Out: {{ $r['move_out_date'] ? $r['move_out_date']->format('j M Y') : 'Ended' }}
                                    · In: {{ $r['move_in_date']->format('j M Y') }}
                                </div>
                            </div>
                            <div class="report-card-badge">
                                @if ($r['status'] === 'Refund')
                                    <span class="tag t-green">Refund: @peso($r['net_refund'])</span>
                                @elseif ($r['status'] === 'Excess')
                                    <span class="tag t-red">Excess: @peso(abs($r['net_refund']))</span>
                                @else
                                    <span class="tag t-sub">Settled</span>
                                @endif
                            </div>
                        </div>
                        <div class="report-card-grid">
                            <div class="report-card-metric">
                                <span class="lbl">Deposit Held:</span>
                                <span class="val">@peso($r['total_deposit'])</span>
                            </div>
                            <div class="report-card-metric">
                                <span class="lbl">Maintenance:</span>
                                <span class="val">@peso($r['deductions']['maintenance'])</span>
                            </div>
                            <div class="report-card-metric">
                                <span class="lbl">Damages / Repairs:</span>
                                <span class="val">@peso($r['deductions']['damages'])</span>
                            </div>
                            <div class="report-card-metric">
                                <span class="lbl">Unpaid Utilities:</span>
                                <span class="val">@peso($r['deductions']['bills'])</span>
                            </div>
                        </div>
                        <div class="report-card-foot">
                            <span>Total Deductions: <b>@peso($r['deductions']['total'])</b></span>
                        </div>
                        <div class="card-actions report-card-actions">
                            <a href="{{ route('reports.statement', ['lease' => $r['lease']->lease_id]) }}" class="btn sec" title="View billing statement">View Statement</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
