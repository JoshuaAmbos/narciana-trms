@extends('layouts.app')

@section('title', 'Statement of account')

@section('content')
    <form method="GET" action="{{ route('reports.statement') }}" class="panel no-print report-filters" novalidate>
        <div class="f f-wide @error('lease') has-err @enderror">
            <label for="lease">Tenant</label>
            <select id="lease" name="lease">
                <option value="">Choose a tenant…</option>
                @foreach ($leases as $l)
                    <option value="{{ $l->lease_id }}" @selected((int) request('lease') === $l->lease_id)>
                        Room {{ $l->room->room_no }} — {{ $l->tenant->full_name }}{{ $l->status === 'Ended' ? ' (ended)' : '' }}
                    </option>
                @endforeach
            </select>
            @error('lease') <div class="err">{{ $message }}</div> @enderror
        </div>
        @include('reports._range')
        @include('reports._actions')
    </form>

    @if (! $report)
        <div class="panel"><p class="muted">Choose a tenant to see every bill and payment with the running balance. Leave the dates empty for the whole stay.</p></div>
    @else
        @php($lease = $report['lease'])
        <div class="panel printable">
            @include('reports._head', [
                'title' => 'Statement of account',
                'period' => ($from ? $from->format('j M Y') : 'Move-in').' to '.($to ? $to->format('j M Y') : 'today'),
            ])

            <dl class="facts">
                <div><dt>Tenant</dt><dd>{{ $lease->tenant->full_name }}</dd></div>
                <div><dt>Room</dt><dd>{{ $lease->room->room_no }} ({{ $lease->room->room_type }})</dd></div>
                <div><dt>Moved in</dt><dd>{{ $lease->move_in_date->format('j M Y') }}@if ($lease->status === 'Ended') · ended {{ $lease->updated_at?->format('j M Y') }}@endif</dd></div>
                <div><dt>Monthly rent / due day</dt><dd>@peso($lease->monthly_rent) · every {{ $lease->dueDayLabel() }}</dd></div>
            </dl>

            <div class="table-wrap">
                <table class="fit ledger">
                    <tr><th>Date</th><th>Details</th><th class="r">Charge</th><th class="r">Paid</th><th class="r">Balance</th></tr>
                    @if ($from)
                        <tr><td class="nowrap">{{ $from->format('j M Y') }}</td><td>Balance brought forward</td><td></td><td></td><td class="money">@peso($report['opening'])</td></tr>
                    @endif
                    @forelse ($report['entries'] as $e)
                        @php($b = $e['bill'])
                        <tr>
                            <td class="nowrap">{{ $e['date']->format('j M Y') }}</td>
                            <td>
                                @switch($e['type'])
                                    @case('bill')
                                        @php($s = $e['statement'])
                                        <a href="{{ route('bills.show', $b) }}">{{ $s['excess'] ? 'Excess after move-out' : ($s['final'] ? 'Final bill' : 'Bill') }}</a>
                                        <span class="muted">due {{ $b->due_date->format('j M Y') }}</span>
                                        @if (! $s['excess'])<span class="sub">Utilities {{ $s['period_start']->format('j M') }} – {{ $s['period_end']->format('j M Y') }}</span>@endif
                                        <span class="sub">
                                            @if ($s['amounts']['rent_amount'] > 0)Rent @peso($s['amounts']['rent_amount']) · @endif
                                            @if ($s['amounts']['water_amount'] > 0)Water @peso($s['amounts']['water_amount']) ({{ $s['occupants'] }}) · @endif
                                            @if ($s['metered'])Electricity @peso($s['amounts']['electricity_amount']) ({{ number_format($s['kwh_used'], 2) }} kWh) · @endif
                                            @if ($s['amounts']['appliance_amount'] > 0)Appliances @peso($s['amounts']['appliance_amount']) ({{ $s['appliances'] }}) · @endif
                                            @if ($s['amounts']['other_charges'] > 0){{ $s['excess'] ? 'Owed after deposit' : 'Other charges' }} @peso($s['amounts']['other_charges']) · @endif
                                            <x-bill-status :status="$b->status" />
                                        </span>
                                        @break
                                    @case('penalty')
                                        Late penalty <span class="sub">bill due {{ $b->due_date->format('j M Y') }}, {{ $e['days_late'] }} {{ Str::plural('day', $e['days_late']) }} late</span>
                                        @break
                                    @case('payment')
                                        Payment, {{ $e['payment']->mode }}
                                        <span class="sub">{{ $e['payment']->ack_no }}@if ($e['payment']->gcash_ref_no) · ref. {{ $e['payment']->gcash_ref_no }}@endif · received by {{ $e['payment']->user->role }}</span>
                                        @break
                                    @case('deposit')
                                        Settled from deposit <span class="sub">bill due {{ $b->due_date->format('j M Y') }}, at move-out</span>
                                        @break
                                @endswitch
                            </td>
                            <td class="money">@if ($e['charge'] > 0)@peso($e['charge'])@endif</td>
                            <td class="money">@if ($e['credit'] > 0)@peso($e['credit'])@endif</td>
                            <td class="money">@peso($e['balance'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No bills or payments in this period.</td></tr>
                    @endforelse
                    <tr class="tot-row"><td colspan="2">Totals</td><td class="money">@peso($report['charges'])</td><td class="money">@peso($report['credits'])</td><td class="money">@peso($report['closing'])</td></tr>
                </table>
            </div>

            <table class="sum statement-close">
                <tr><td>Balance{{ $to ? ' as of '.$to->format('j M Y') : '' }}</td><td class="money">@peso($report['closing'])</td></tr>
                @if ($report['penalty_so_far'] > 0)
                    <tr><td>Penalty so far on unpaid bills <span class="sub">if paid today, {{ $report['today']->format('j M Y') }}; not saved yet</span></td><td class="money">@peso($report['penalty_so_far'])</td></tr>
                @endif
                <tr class="tot"><td>{{ $report['penalty_so_far'] > 0 ? 'Amount due if paid today' : 'Amount due' }}</td><td class="money">@peso($report['due_today'])</td></tr>
            </table>
        </div>
    @endif
@endsection
