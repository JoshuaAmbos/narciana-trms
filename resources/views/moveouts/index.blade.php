@extends('layouts.app')

@section('title', 'Move-out Settlement')

@section('content')
    <div class="panel">
        <h2>In progress <span class="muted">Give a notice from the tenant's MOA page (Tenants &amp; MOA)</span></h2>
        @if ($pending->isEmpty())
            <p class="muted">No move-outs in progress.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Room</th><th>Tenant</th><th>Notice</th><th>Planned</th><th>Moved out</th><th>Status</th><th></th></tr>
                    @foreach ($pending as $m)
                        <tr>
                            <td><b>{{ $m->lease->room->room_no }}</b></td>
                            <td>{{ $m->lease->tenant->full_name }}</td>
                            <td class="nowrap">{{ $m->notice_date->format('j M Y') }}</td>
                            <td class="nowrap">{{ $m->planned_date->format('j M Y') }}</td>
                            <td class="nowrap">{{ $m->actual_date?->format('j M Y') ?? '–' }}</td>
                            <td><x-moveout-status :moveout="$m" /> @if ($m->early_departure)<span class="tag t-red">early</span>@endif</td>
                            <td class="actions"><a href="{{ route('moveouts.show', $m) }}" class="btn sec">Open</a></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    <div class="grid2">
        <div class="panel">
            <h2>Refunds due</h2>
            @if ($refunds->isEmpty())
                <p class="muted">No refunds waiting.</p>
            @else
                <table>
                    @foreach ($refunds as $m)
                        <tr>
                            <td><b>{{ $m->lease->room->room_no }}</b> <span class="sub">{{ $m->lease->tenant->full_name }}</span></td>
                            <td class="nowrap {{ $m->refund_due_date->lt(today()) ? 'red' : '' }}">Due {{ $m->refund_due_date->format('j M Y') }}</td>
                            <td class="money">@peso($m->refund_amount)</td>
                            <td class="actions"><a href="{{ route('moveouts.show', $m) }}" class="btn sec">Open</a></td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
        <div class="panel">
            <h2>Excess to collect</h2>
            @if ($excess->isEmpty())
                <p class="muted">Nothing owed by former tenants.</p>
            @else
                <table>
                    @foreach ($excess as $m)
                        <tr>
                            <td><b>{{ $m->lease->room->room_no }}</b> <span class="sub">{{ $m->lease->tenant->full_name }}</span></td>
                            <td class="money red">@peso($m->excess_amount)</td>
                            <td class="actions"><a href="{{ route('moveouts.show', $m) }}" class="btn sec">Open</a></td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Settled</h2>
        @if ($settled->isEmpty())
            <p class="muted">No settled move-outs yet.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Room</th><th>Tenant</th><th>Moved out</th><th class="r">Refund</th><th class="r">Excess paid</th><th></th></tr>
                    @foreach ($settled as $m)
                        <tr>
                            <td><b>{{ $m->lease->room->room_no }}</b></td>
                            <td>{{ $m->lease->tenant->full_name }}</td>
                            <td class="nowrap">{{ $m->actual_date?->format('j M Y') }}</td>
                            <td class="money">@peso($m->refund_amount)</td>
                            <td class="money">@peso($m->excess_amount)</td>
                            <td class="actions"><a href="{{ route('moveouts.show', $m) }}" class="btn sec">Statement</a></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
