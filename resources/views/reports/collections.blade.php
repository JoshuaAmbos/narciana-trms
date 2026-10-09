@extends('layouts.app')

@section('title', 'Monthly Collection Summary')

@section('content')
    <form method="GET" action="{{ route('reports.collections') }}" class="panel no-print report-filters" novalidate>
        <div class="f @error('month') has-err @enderror">
            <label for="month">Billing Month</label>
            <input id="month" name="month" type="month" value="{{ $month->format('Y-m') }}">
            @error('month') <div class="err">{{ $message }}</div> @enderror
        </div>
        @include('reports._actions')
    </form>

    <div class="panel printable reports-container">
        @include('reports._head', ['title' => 'Monthly Collection Summary', 'period' => $month->format('F Y')])

        {{-- Reconciliation KPI Cards --}}
        <div class="cards">
            <div class="card">
                <div class="n">@peso($totals['All'])</div>
                <div class="l">Total Collected ({{ $payments->count() }} {{ Str::plural('Payment', $payments->count()) }})</div>
            </div>
            <div class="card">
                <div class="n">@peso($totals['Cash'])</div>
                <div class="l">Cash Collected</div>
            </div>
            <div class="card">
                <div class="n">@peso($totals['GCash'])</div>
                <div class="l">GCash Collected</div>
            </div>
            <div class="card">
                <div class="n">{{ $receivers->count() }}</div>
                <div class="l">Active {{ Str::plural('Collector', $receivers->count()) }}</div>
            </div>
        </div>

        <h3 class="sub-head">Collections Per Receiver</h3>
        @if ($receivers->isEmpty())
            <p class="muted">No payments recorded in {{ $month->format('F Y') }}.</p>
        @else
            {{-- Receiver Table (Landscape & Desktop) --}}
            <div class="table-wrap report-table-wrap">
                <table class="fit">
                    <thead>
                        <tr>
                            <th>Received By</th>
                            <th class="r">Payments</th>
                            <th class="r">Cash Amount</th>
                            <th class="r">GCash Amount</th>
                            <th class="r">Total Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receivers as $r)
                            <tr>
                                <td><b>{{ $r['user']->full_name }}</b> ({{ $r['user']->role }})</td>
                                <td class="r">{{ $r['count'] }}</td>
                                <td class="money">@peso($r['Cash'])</td>
                                <td class="money">@peso($r['GCash'])</td>
                                <td class="money"><b>@peso($r['All'])</b></td>
                            </tr>
                        @endforeach
                        <tr class="tot-row">
                            <td>Total Collections</td>
                            <td class="r">{{ $payments->count() }}</td>
                            <td class="money">@peso($totals['Cash'])</td>
                            <td class="money">@peso($totals['GCash'])</td>
                            <td class="money">@peso($totals['All'])</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Receiver Cards (Portrait & Mobile) --}}
            <div class="report-cards">
                @foreach ($receivers as $r)
                    <div class="report-card">
                        <div class="report-card-head">
                            <div class="report-card-title">{{ $r['user']->full_name }} ({{ $r['user']->role }})</div>
                            <span class="tag t-sub">{{ $r['count'] }} {{ Str::plural('Payment', $r['count']) }}</span>
                        </div>
                        <div class="report-card-grid">
                            <div class="report-card-metric">
                                <span class="lbl">Cash:</span>
                                <span class="val">@peso($r['Cash'])</span>
                            </div>
                            <div class="report-card-metric">
                                <span class="lbl">GCash:</span>
                                <span class="val">@peso($r['GCash'])</span>
                            </div>
                        </div>
                        <div class="report-card-foot">
                            <span>Total Collected:</span>
                            <b>@peso($r['All'])</b>
                        </div>
                    </div>
                @endforeach
            </div>

            <h3 class="sub-head" style="margin-top: 24px;">Payment Transactions Ledger</h3>
            {{-- Payments Table (Landscape & Desktop) --}}
            <div class="table-wrap report-table-wrap">
                <table class="fit">
                    <thead>
                        <tr>
                            <th>Payment Date</th>
                            <th>Acknowledgment No.</th>
                            <th>Room &amp; Tenant</th>
                            <th>Payment Mode</th>
                            <th>Received By</th>
                            <th class="r">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $p)
                            <tr class="report-row-clickable"
                                onclick="window.location='{{ route('payments.show', $p) }}'"
                                tabindex="0"
                                role="button"
                                aria-label="View acknowledgment voucher for {{ $p->ack_no }}">
                                <td class="nowrap">{{ $p->payment_date->format('j M Y') }}</td>
                                <td class="nowrap">
                                    <a href="{{ route('payments.show', $p) }}" style="font-weight: 700;">{{ $p->ack_no }}</a>
                                </td>
                                <td>
                                    <b>Room {{ $p->bill->lease->room->room_no }}</b>
                                    <span class="sub">{{ $p->bill->lease->tenant->full_name }}</span>
                                </td>
                                <td>
                                    @if ($p->mode === 'GCash')
                                        <span class="tag t-blue" style="font-size: 11px;">GCash</span>
                                        @if ($p->gcash_ref_no)
                                            <span class="sub nowrap">Ref: {{ $p->gcash_ref_no }}</span>
                                        @endif
                                    @else
                                        <span class="tag t-green" style="font-size: 11px;">Cash</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $p->user->full_name }}
                                    <span class="sub">{{ $p->user->role }}</span>
                                </td>
                                <td class="money"><b>@peso($p->amount)</b></td>
                            </tr>
                        @endforeach
                        <tr class="tot-row">
                            <td colspan="5">Total ({{ $payments->count() }} Payments)</td>
                            <td class="money">@peso($totals['All'])</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Payments Cards (Portrait & Mobile) --}}
            <div class="report-cards">
                @foreach ($payments as $p)
                    <div class="report-card"
                         onclick="window.location='{{ route('payments.show', $p) }}'"
                         tabindex="0"
                         role="button"
                         aria-label="View payment {{ $p->ack_no }}">
                        <div class="report-card-head">
                            <div>
                                <div class="report-card-title">
                                    Room {{ $p->bill->lease->room->room_no }} — {{ $p->bill->lease->tenant->full_name }}
                                </div>
                                <div class="report-card-sub">
                                    {{ $p->payment_date->format('j M Y') }} · ACK: <b>{{ $p->ack_no }}</b>
                                </div>
                            </div>
                            <div class="report-card-badge">
                                @if ($p->mode === 'GCash')
                                    <span class="tag t-blue">GCash</span>
                                @else
                                    <span class="tag t-green">Cash</span>
                                @endif
                                <span class="report-chevron"><x-icon name="chevron-right" size="18" /></span>
                            </div>
                        </div>
                        @if ($p->mode === 'GCash' && $p->gcash_ref_no)
                            <div style="font-size: 12px; color: var(--navy); background: #f0f7ff; padding: 4px 8px; border-radius: 4px;">
                                GCash Reference: <b>{{ $p->gcash_ref_no }}</b>
                            </div>
                        @endif
                        <div class="report-card-foot">
                            <span>Received by {{ $p->user->full_name }} ({{ $p->user->role }})</span>
                            <span style="font-size: 15px; font-weight: 700; color: var(--navy);">@peso($p->amount)</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($settled->isNotEmpty())
            <h3 class="sub-head" style="margin-top: 24px;">
                Bills Settled From Security Deposit
                <span class="muted" style="font-size: 13px; font-weight: normal;">(Deducted at move-out; excluded from cash collections above)</span>
            </h3>
            {{-- Settled Table (Landscape & Desktop) --}}
            <div class="table-wrap report-table-wrap">
                <table class="fit">
                    <thead>
                        <tr>
                            <th>Settlement Date</th>
                            <th>Room &amp; Tenant</th>
                            <th>Bill Due Date</th>
                            <th class="r">Settled Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($settled as $row)
                            <tr class="report-row-clickable"
                                onclick="window.location='{{ route('bills.show', $row['bill']) }}'"
                                tabindex="0"
                                role="button"
                                aria-label="View bill due {{ $row['bill']->due_date->format('j M Y') }}">
                                <td class="nowrap">{{ $row['date']->format('j M Y') }}</td>
                                <td>
                                    <b>Room {{ $row['bill']->lease->room->room_no }}</b>
                                    <span class="sub">{{ $row['bill']->lease->tenant->full_name }}</span>
                                </td>
                                <td class="nowrap">{{ $row['bill']->due_date->format('j M Y') }}</td>
                                <td class="money">@peso($row['bill']->total_amount)</td>
                            </tr>
                        @endforeach
                        <tr class="tot-row">
                            <td colspan="3">Total Settled From Deposits</td>
                            <td class="money">@peso($settled_total)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Settled Cards (Portrait & Mobile) --}}
            <div class="report-cards">
                @foreach ($settled as $row)
                    <div class="report-card"
                         onclick="window.location='{{ route('bills.show', $row['bill']) }}'"
                         tabindex="0"
                         role="button"
                         aria-label="View settled bill">
                        <div class="report-card-head">
                            <div>
                                <div class="report-card-title">
                                    Room {{ $row['bill']->lease->room->room_no }} — {{ $row['bill']->lease->tenant->full_name }}
                                </div>
                                <div class="report-card-sub">
                                    Settled: {{ $row['date']->format('j M Y') }} · Due: {{ $row['bill']->due_date->format('j M Y') }}
                                </div>
                            </div>
                            <span class="tag t-amber">Deposit Settled</span>
                        </div>
                        <div class="report-card-foot">
                            <span>Total Bill Settled</span>
                            <b>@peso($row['bill']->total_amount)</b>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
