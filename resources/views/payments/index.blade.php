@extends('layouts.app')

@section('title', 'Payments')

@section('content')
    @error('bill') <div class="alert alert-err">{{ $message }}</div> @enderror

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

    <div class="panel no-print">
        <h2>Bills to collect <span class="muted">Oldest first · full payment only</span></h2>
        @if ($breachedLeases->isNotEmpty())
            <div style="margin-bottom: 20px; border: 1px solid #fda4af; background: #fff1f2; border-radius: 8px; overflow: hidden;">
                <div style="background: #ffe4e6; padding: 10px 16px; font-weight: 700; font-size: 13px; color: #9f1239; display: flex; align-items: center; justify-content: space-between;">
                    <span>MOA Clause 8 Breaches (Deposit Exhausted)</span>
                    <span style="background: #e11d48; color: #fff; padding: 2px 8px; border-radius: 9999px; font-size: 11px;">
                        {{ $breachedLeases->count() }} {{ Str::plural('Action Required', $breachedLeases->count()) }}
                    </span>
                </div>
                <div style="display: flex; flex-direction: column;">
                    @foreach ($breachedLeases as $breach)
                        @php($bLease = $breach['lease'])
                        <div style="padding: 12px 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #fecdd3;">
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <span class="tag t-red" style="font-weight: 700; background: #e11d48; color: #fff;">
                                        [!] Breach: Deposit Exhausted (Eligible for Termination under MOA Clause 8)
                                    </span>
                                    <b>Room {{ $bLease->room->room_no }}</b> · {{ $bLease->occupantsDisplay() }}
                                </div>
                                <div style="font-size: 12px; color: #9f1239; font-weight: 500;">
                                    Total Arrears: @peso($breach['total_arrears']) &ge; Total Security Deposit: @peso($breach['total_deposit'])
                                    (Unpaid bills: {{ $breach['unpaid_bills']->count() }})
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                @can('manage-leases')
                                    <form method="POST" action="{{ route('leases.terminate-clause8', $bLease) }}"
                                          onsubmit="return confirm('Terminate lease for Room {{ $bLease->room->room_no }} under MOA Clause 8?\n\nThis will offset the security deposit against unpaid bills, mark the lease cancelled, deactivate the tenant, and vacate the room immediately.')">
                                        @csrf
                                        <button type="submit" class="btn danger" style="min-height: 44px; display: inline-flex; align-items: center; font-weight: 600;">
                                            Terminate Lease (Clause 8)
                                        </button>
                                    </form>
                                @endcan
                                <a href="{{ route('leases.show', $bLease) }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center;">View MOA</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($toCollect->isEmpty())
            <p class="muted">Every bill is paid.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Due date</th><th>Room</th><th>Tenant</th><th class="r">Bill</th><th>Status</th><th class="r">Penalty so far</th><th class="r">Due if paid today</th><th></th></tr>
                    @foreach ($toCollect as $row)
                        @php($b = $row['bill'])
                        <tr @if (!empty($row['is_breached'])) style="background: #fff1f2;" @endif>
                            <td class="nowrap">{{ $b->due_date->format('j M Y') }}</td>
                            <td><b>{{ $b->lease->room->room_no }}</b></td>
                            <td>
                                {{ $b->lease->tenant->full_name }}
                                @if (!empty($row['is_breached']))
                                    <div class="sub red font-semibold">[!] Breach: Deposit Exhausted (Eligible for Termination under MOA Clause 8)</div>
                                @endif
                            </td>
                            <td class="money">@peso((float) $b->total_amount - (float) $b->penalty_amount)</td>
                            <td>
                                @if ($row['today']['days_late'] > 0)
                                    <span class="tag t-red">Overdue {{ $row['today']['days_late'] }} {{ Str::plural('day', $row['today']['days_late']) }}</span>
                                @elseif ($b->due_date->gt(today()))
                                    <span class="tag t-amber">Due in {{ today()->diffInDays($b->due_date) }} {{ Str::plural('day', today()->diffInDays($b->due_date)) }}</span>
                                @elseif ($b->due_date->isToday())
                                    <span class="tag t-amber">Due today</span>
                                @elseif (!empty($row['today']['in_grace']))
                                    <span class="tag t-amber">Grace Period (Day {{ $row['today']['grace_day'] }} of {{ $row['today']['grace_days'] }})</span>
                                @else
                                    <span class="tag t-amber">In grace period</span>
                                @endif

                                @if (!empty($row['is_breached']))
                                    <div style="margin-top: 4px;">
                                        <span class="tag t-red" style="font-weight: 700;">[!] Breach: Deposit Exhausted (Eligible for Termination under MOA Clause 8)</span>
                                    </div>
                                @endif
                            </td>
                            <td class="money">@peso($row['today']['penalty'])</td>
                            <td class="money"><b>@peso($row['today']['total'])</b></td>
                            <td class="actions">
                                @if ($row['can_pay'])
                                    <a href="{{ route('payments.create', ['bill' => $b->bill_id]) }}" class="btn" style="min-height: 44px; display: inline-flex; align-items: center;">Record payment</a>
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
@endsection
