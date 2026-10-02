@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php($c = $counts)
    <div class="cards">
        @if ($staff)
            <a href="{{ route('payments.index') }}" class="card card-link">
                <div class="n amber">{{ $c['due_soon'] }}</div>
                <div class="l">{{ Str::plural('Bill', $c['due_soon']) }} due in the next 7 days</div>
            </a>
            <a href="{{ route('payments.index') }}" class="card card-link">
                <div class="n red">{{ $c['overdue_tenants'] }}</div>
                <div class="l">Overdue {{ Str::plural('tenant', $c['overdue_tenants']) }}</div>
            </a>
        @endif
        <a href="{{ route('concerns.index') }}" class="card card-link">
            <div class="n blue">{{ $c['open_concerns'] }}</div>
            <div class="l">Open {{ Str::plural('concern', $c['open_concerns']) }}@if ($c['urgent_concerns']) ({{ $c['urgent_concerns'] }} urgent)@endif</div>
        </a>
        <a href="{{ route('rooms.index') }}" class="card card-link">
            <div class="n green">{{ $c['vacant_rooms'] }}</div>
            <div class="l">Vacant {{ Str::plural('room', $c['vacant_rooms']) }}@if ($c['vacating_rooms']) / {{ $c['vacating_rooms'] }} vacating @endif</div>
        </a>
    </div>

    @if ($staff)
        <div class="panel">
            <h2>Due and overdue this week <a href="{{ route('payments.index') }}" class="btn sec">Payments</a></h2>
            @if ($toCollect->isEmpty())
                <p class="muted">No bills due in the next 7 days and nothing overdue.</p>
            @else
                <div class="table-wrap">
                    <table class="fit">
                        <tr><th>Room</th><th>Tenant</th><th>Due date</th><th class="r">Amount today</th><th>Status</th></tr>
                        @foreach ($toCollect as $row)
                            @php($b = $row['bill'])
                            <tr>
                                <td><b>{{ $b->lease->room->room_no }}</b></td>
                                <td>{{ $b->lease->tenant->full_name }}</td>
                                <td class="nowrap">{{ $b->due_date->format('j M Y') }}</td>
                                <td class="money">@peso($row['total'])
                                    @if ($row['penalty'] > 0)<span class="sub">incl. penalty @peso($row['penalty'])</span>@endif</td>
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
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif

            @if ($unbilled->isNotEmpty())
                <h3 class="sub-head">Due soon, no bill yet</h3>
                <div class="table-wrap">
                    <table class="fit">
                        <tr><th>Room</th><th>Tenant</th><th>Bill due</th><th></th></tr>
                        @foreach ($unbilled as $row)
                            <tr>
                                <td><b>{{ $row['lease']->room->room_no }}</b></td>
                                <td>{{ $row['lease']->tenant->full_name }}</td>
                                <td class="nowrap">{{ $row['due_date']->format('j M Y') }}</td>
                                <td class="actions"><a href="{{ route('bills.create', ['lease' => $row['lease']->lease_id]) }}" class="btn">Generate now</a></td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $staff ? 'grid2' : 'grid3' }} stack-tablet">
        <div class="panel">
            <h2>Open concerns <a href="{{ route('concerns.create') }}" class="btn sec">+ Log concern</a></h2>
            @if ($concerns->isEmpty())
                <p class="muted">No open concerns.</p>
            @else
                <div class="table-wrap">
                    <table class="fit">
                        <tr><th>Reported</th><th>Where</th><th>Concern</th><th>Status</th></tr>
                        @foreach ($concerns as $concern)
                            <tr>
                                <td class="nowrap">{{ $concern->reported_at->format('j M, g:i A') }}</td>
                                <td><b>{{ $concern->room?->room_no ?? 'Shared area' }}</b></td>
                                <td><a href="{{ route('concerns.show', $concern) }}">{{ Str::limit($concern->description, 60) }}</a>
                                    <span class="sub">{{ $concern->category }} · {{ $concern->assignedTo->role }}</span></td>
                                <td><x-concern-status :concern="$concern" /></td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>

        @if ($staff)
            <div>
            <div class="panel">
                <h2>Moving out <a href="{{ route('moveouts.index') }}" class="btn sec">Move-outs</a></h2>
                @if ($movingOut->isEmpty())
                    <p class="muted">No tenant is moving out.</p>
                @else
                    <div class="table-wrap">
                        <table class="fit">
                            <tr><th>Room</th><th>Tenant</th><th>Move-out</th><th>Step</th></tr>
                            @foreach ($movingOut as $m)
                                <tr>
                                    <td><b>{{ $m->lease->room->room_no }}</b></td>
                                    <td><a href="{{ route('moveouts.show', $m) }}">{{ $m->lease->tenant->full_name }}</a>
                                        <span class="sub"><x-lease-status :lease="$m->lease" /></span></td>
                                    <td class="nowrap">{{ ($m->actual_date ?? $m->planned_date)->format('j M Y') }}
                                        <span class="sub">{{ $m->actual_date ? 'moved out' : 'planned' }}</span></td>
                                    <td><x-moveout-status :moveout="$m" /></td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
            <div class="panel">
                <h2>Refunds due <a href="{{ route('moveouts.index') }}" class="btn sec">Move-outs</a></h2>
                @if ($refunds->isEmpty())
                    <p class="muted">No approved refunds waiting to be handed over.</p>
                @else
                    <div class="table-wrap">
                        <table class="fit">
                            <tr><th>Room</th><th>Tenant</th><th class="r">Refund</th><th>Due</th></tr>
                            @foreach ($refunds as $m)
                                <tr>
                                    <td><b>{{ $m->lease->room->room_no }}</b></td>
                                    <td><a href="{{ route('moveouts.show', $m) }}">{{ $m->lease->tenant->full_name }}</a></td>
                                    <td class="money">@peso($m->refund_amount)</td>
                                    <td class="nowrap">{{ $m->refund_due_date->format('j M Y') }}
                                        @if ($m->refund_due_date->lt(today()))<span class="tag t-red">Past due</span>@endif</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
            </div>
        @else
            <div class="panel">
                <h2>Rooms <a href="{{ route('rooms.index') }}" class="btn sec">Rooms</a></h2>
                <div class="table-wrap">
                    <table class="fit">
                        <tr><th>Room</th><th>Type</th><th>Status</th></tr>
                        @foreach ($rooms as $room)
                            <tr>
                                <td><b>{{ $room->room_no }}</b></td>
                                <td>{{ $room->room_type }}</td>
                                <td><x-room-status :room="$room" />
                                    @if ($room->status === 'Vacating' && $room->expected_vacant_date)
                                        <span class="sub">vacant {{ $room->expected_vacant_date->format('j M Y') }}</span>
                                    @endif</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
