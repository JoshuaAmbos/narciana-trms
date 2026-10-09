@extends('layouts.app')

@section('title', 'Billing Statement Per Tenant')

@section('content')
    <form method="GET" action="{{ route('reports.statement') }}" class="panel no-print report-filters" novalidate>
        <div class="f f-wide @error('lease') has-err @enderror">
            <label for="lease">Select Tenant &amp; Room</label>
            <select id="lease" name="lease">
                <option value="">Choose a tenant…</option>
                @foreach ($leases as $l)
                    <option value="{{ $l->lease_id }}" @selected((int) request('lease') === $l->lease_id)>
                        Room {{ $l->room->room_no }} — {{ $l->tenant->full_name }}{{ $l->status === 'Ended' ? ' (Ended)' : '' }}
                    </option>
                @endforeach
            </select>
            @error('lease') <div class="err">{{ $message }}</div> @enderror
        </div>
        @include('reports._range')
        @include('reports._actions')
    </form>

    @if (! $report)
        <div class="panel reports-container">
            <p class="muted">Select a tenant to view complete historical billing statements, itemized charges, payments, and running balance. Leave dates blank for the entire stay.</p>
        </div>
    @else
        @php($lease = $report['lease'])
        <div class="panel printable reports-container">
            @include('reports._head', [
                'title' => 'Statement of account',
                'period' => ($from ? $from->format('j M Y') : 'Move-In').' to '.($to ? $to->format('j M Y') : 'Today'),
            ])

            <dl class="facts">
                <div>
                    <dt>Tenant Name</dt>
                    <dd><b>{{ $lease->tenant->full_name }}</b></dd>
                </div>
                <div>
                    <dt>Room Number &amp; Setup</dt>
                    <dd>Room {{ $lease->room->room_no }} ({{ $lease->room->room_type }})</dd>
                </div>
                <div>
                    <dt>Move-In Date</dt>
                    <dd>{{ $lease->move_in_date->format('j M Y') }}@if ($lease->status === 'Ended') · Ended {{ $lease->updated_at?->format('j M Y') }}@endif</dd>
                </div>
                <div>
                    <dt>Monthly Rent &amp; Due Day</dt>
                    <dd>@peso($lease->monthly_rent) · Every {{ $lease->dueDayLabel() }}</dd>
                </div>
            </dl>

            {{-- Landscape Tablet & Desktop Ledger Table --}}
            <div class="table-wrap report-table-wrap">
                <table class="fit ledger">
                    <thead>
                        <tr>
                            <th>Transaction Date</th>
                            <th>Description &amp; Itemized Details</th>
                            <th class="r">Charge (Debit)</th>
                            <th class="r">Credit (Paid)</th>
                            <th class="r">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($from)
                            <tr>
                                <td class="nowrap">{{ $from->format('j M Y') }}</td>
                                <td><b>Balance Brought Forward</b></td>
                                <td></td>
                                <td></td>
                                <td class="money"><b>@peso($report['opening'])</b></td>
                            </tr>
                        @endif
                        @forelse ($report['entries'] as $e)
                            @php($b = $e['bill'])
                            @php($clickUrl = $e['type'] === 'payment' ? route('payments.show', $e['payment']) : route('bills.show', $b))
                            <tr class="report-row-clickable"
                                onclick="window.location='{{ $clickUrl }}'"
                                tabindex="0"
                                role="button"
                                aria-label="View {{ $e['type'] }} details">
                                <td class="nowrap">{{ $e['date']->format('j M Y') }}</td>
                                <td>
                                    @switch($e['type'])
                                        @case('bill')
                                            @php($s = $e['statement'])
                                            <a href="{{ route('bills.show', $b) }}" style="font-weight: 700;">
                                                {{ $s['excess'] ? 'Excess After Move-Out' : ($s['final'] ? 'Final Billing Statement' : 'Monthly Bill') }}
                                            </a>
                                            <span class="muted">Due {{ $b->due_date->format('j M Y') }}</span>
                                            @if (! $s['excess'])
                                                <span class="sub">Period: {{ $s['period_start']->format('j M') }} – {{ $s['period_end']->format('j M Y') }}</span>
                                            @endif
                                            <span class="sub" style="margin-top: 3px;">
                                                @if ($s['amounts']['rent_amount'] > 0) Rent @peso($s['amounts']['rent_amount']) · @endif
                                                @if ($s['amounts']['water_amount'] > 0) Water @peso($s['amounts']['water_amount']) ({{ $s['occupants'] }} {{ Str::plural('head', $s['occupants']) }}) · @endif
                                                @if ($s['metered']) Electricity @peso($s['amounts']['electricity_amount']) ({{ number_format($s['kwh_used'], 2) }} kWh) · @endif
                                                @if ($s['amounts']['appliance_amount'] > 0) Appliances @peso($s['amounts']['appliance_amount']) ({{ $s['appliances'] }}) · @endif
                                                @if ($s['amounts']['other_charges'] > 0) {{ $s['excess'] ? 'Owed After Deposit' : 'Other Charges' }} @peso($s['amounts']['other_charges']) · @endif
                                                <x-bill-status :status="$b->status" />
                                            </span>
                                            @break
                                        @case('penalty')
                                            <b>Late penalty</b>
                                            <span class="sub">Bill Due {{ $b->due_date->format('j M Y') }} · {{ $e['days_late'] }} {{ Str::plural('Day', $e['days_late']) }} Late</span>
                                            @break
                                        @case('payment')
                                            <b>Payment Received ({{ $e['payment']->mode }})</b>
                                            <span class="sub">ACK: <a href="{{ route('payments.show', $e['payment']) }}">{{ $e['payment']->ack_no }}</a>@if ($e['payment']->gcash_ref_no) · Ref: {{ $e['payment']->gcash_ref_no }}@endif · By {{ $e['payment']->user->role }} ({{ $e['payment']->user->full_name }})</span>
                                            @break
                                        @case('deposit')
                                            <b>Settled From Security Deposit</b>
                                            <span class="sub">Bill Due {{ $b->due_date->format('j M Y') }} · Deducted at Move-Out</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="money">
                                    @if ($e['charge'] > 0)
                                        @peso($e['charge'])
                                    @endif
                                </td>
                                <td class="money">
                                    @if ($e['credit'] > 0)
                                        <span style="color: var(--green); font-weight: 600;">@peso($e['credit'])</span>
                                    @endif
                                </td>
                                <td class="money">
                                    <b>@peso($e['balance'])</b>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="muted">No transactions recorded in this period.</td>
                            </tr>
                        @endforelse
                        <tr class="tot-row">
                            <td colspan="2">Period Totals</td>
                            <td class="money">@peso($report['charges'])</td>
                            <td class="money" style="color: var(--green);">@peso($report['credits'])</td>
                            <td class="money"><b>@peso($report['closing'])</b></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Portrait Tablet & Mobile Stacked Cards --}}
            <div class="report-cards">
                @if ($from)
                    <div class="report-card">
                        <div class="report-card-head">
                            <div class="report-card-title">Balance Brought Forward</div>
                            <span class="tag t-sub">{{ $from->format('j M Y') }}</span>
                        </div>
                        <div class="report-card-foot">
                            <span>Opening Balance</span>
                            <b>@peso($report['opening'])</b>
                        </div>
                    </div>
                @endif
                @foreach ($report['entries'] as $e)
                    @php($b = $e['bill'])
                    @php($clickUrl = $e['type'] === 'payment' ? route('payments.show', $e['payment']) : route('bills.show', $b))
                    <div class="report-card"
                         onclick="window.location='{{ $clickUrl }}'"
                         tabindex="0"
                         role="button"
                         aria-label="View transaction details">
                        <div class="report-card-head">
                            <div>
                                <div class="report-card-title">
                                    @if ($e['type'] === 'bill')
                                        Bill Due {{ $b->due_date->format('j M Y') }}
                                    @elseif ($e['type'] === 'payment')
                                        Payment ({{ $e['payment']->mode }}) · {{ $e['payment']->ack_no }}
                                    @elseif ($e['type'] === 'penalty')
                                        Late Penalty
                                    @else
                                        Deposit Settlement
                                    @endif
                                </div>
                                <div class="report-card-sub">{{ $e['date']->format('j M Y') }}</div>
                            </div>
                            <div class="report-card-badge">
                                @if ($e['type'] === 'bill')
                                    <x-bill-status :status="$b->status" />
                                @elseif ($e['type'] === 'payment')
                                    <span class="tag t-green">Credit</span>
                                @elseif ($e['type'] === 'penalty')
                                    <span class="tag t-red">Penalty</span>
                                @else
                                    <span class="tag t-amber">Deposit</span>
                                @endif
                                <span class="report-chevron"><x-icon name="chevron-right" size="18" /></span>
                            </div>
                        </div>
                        <div class="report-card-grid">
                            @if ($e['charge'] > 0)
                                <div class="report-card-metric">
                                    <span class="lbl">Charge (Debit):</span>
                                    <span class="val">@peso($e['charge'])</span>
                                </div>
                            @endif
                            @if ($e['credit'] > 0)
                                <div class="report-card-metric">
                                    <span class="lbl">Paid (Credit):</span>
                                    <span class="val" style="color: var(--green);">@peso($e['credit'])</span>
                                </div>
                            @endif
                        </div>
                        <div class="report-card-foot">
                            <span>Running Balance:</span>
                            <b>@peso($e['balance'])</b>
                        </div>
                    </div>
                @endforeach
            </div>

            <table class="sum statement-close" style="margin-top: 18px;">
                <tr>
                    <td>Closing Balance{{ $to ? ' As of '.$to->format('j M Y') : '' }}</td>
                    <td class="money"><b>@peso($report['closing'])</b></td>
                </tr>
                @if ($report['penalty_so_far'] > 0)
                    <tr>
                        <td>
                            Accrued Penalties on Unpaid Bills
                            <span class="sub">Calculated as of today ({{ $report['today']->format('j M Y') }}); committed upon payment</span>
                        </td>
                        <td class="money" style="color: var(--red);">@peso($report['penalty_so_far'])</td>
                    </tr>
                @endif
                <tr class="tot">
                    <td>{{ $report['penalty_so_far'] > 0 ? 'Amount due if paid today' : 'Amount due' }}</td>
                    <td class="money"><b>@peso($report['due_today'])</b></td>
                </tr>
            </table>
        </div>
    @endif
@endsection
