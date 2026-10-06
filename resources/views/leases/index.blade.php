@extends('layouts.app')

@section('title', 'Leases (MOA)')

@section('content')
    {{-- Metric Summary Cards --}}
    <div class="cards">
        <div class="card">
            <div class="n green">{{ $counts['Active'] }}</div>
            <div class="l">Active Leases (Occupied)</div>
        </div>
        <div class="card">
            <div class="n blue">{{ $vacantRoomsCount }}</div>
            <div class="l">Vacant Rooms Available</div>
        </div>
        <div class="card">
            <div class="n amber">{{ $counts['Ended'] }}</div>
            <div class="l">Ended / Settled Contracts</div>
        </div>
        @if ($counts['Cancelled'] > 0)
            <div class="card">
                <div class="n muted">{{ $counts['Cancelled'] }}</div>
                <div class="l">Cancelled Agreements</div>
            </div>
        @endif
    </div>

    {{-- Main Leases Management Panel --}}
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
            <div>
                <h2 style="margin: 0;">
                    Leases & MOA Management
                    <span class="muted" style="font-size: 13px; font-weight: normal; margin-left: 8px;">Contractual tenancy agreements & room allocations</span>
                </h2>
            </div>
            <div class="actions">
                <a href="{{ route('leases.create') }}" class="btn">+ Execute New Lease (MOA)</a>
                <a href="{{ route('tenants.index') }}" class="btn sec">Tenants Master Directory &rarr;</a>
            </div>
        </div>

        {{-- Lifecycle Tabs Navigation --}}
        <div class="filters" style="margin-bottom: 14px;">
            <span class="muted" style="font-size: 13px;">Status:</span>
            @foreach (['Active' => 'Active Leases ('.$counts['Active'].')', 'Ended' => 'Ended Contracts ('.$counts['Ended'].')', 'Cancelled' => 'Cancelled ('.$counts['Cancelled'].')', 'All' => 'All ('.$counts['All'].')'] as $st => $label)
                <a href="{{ route('leases.index', ['status' => $st, 'search' => $search, 'room_type' => $roomType]) }}"
                   class="chip {{ $status === $st ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
        </div>

        {{-- Filter & Search Controls --}}
        <form method="GET" action="{{ route('leases.index') }}" class="billing-filter-bar" style="margin-bottom: 16px;">
            <input type="hidden" name="status" value="{{ $status }}">

            <div class="billing-filter-group">
                <span class="muted" style="font-size: 13px;">Room Type:</span>
                @foreach (['All', 'Basic', 'Aircon'] as $rt)
                    <a href="{{ route('leases.index', ['status' => $status, 'room_type' => $rt, 'search' => $search]) }}"
                       class="chip {{ $roomType === $rt ? 'on' : '' }}">{{ $rt }}</a>
                @endforeach
            </div>

            <div class="billing-filter-group">
                <input type="text" name="search" class="search-input" placeholder="Search room no or tenant name..." value="{{ $search }}">
                <button type="submit" class="btn sec" style="padding: 6px 12px; font-size: 13px;">Search</button>
                @if ($search !== '' || $roomType !== 'All' || $status !== 'Active')
                    <a href="{{ route('leases.index') }}" class="btn sec" style="padding: 6px 10px; font-size: 13px;">Clear</a>
                @endif
            </div>
        </form>

        @if ($leases->isEmpty())
            <p class="muted">No {{ strtolower($status) }} lease agreements found.
                @if ($status === 'Active')
                    <a href="{{ route('leases.create') }}">Execute a new lease (MOA) &rarr;</a>
                @endif
            </p>
        @else
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Contract Ref</th>
                        <th>Room</th>
                        <th>Tenant</th>
                        <th>Move-in Date</th>
                        <th>Due Day</th>
                        <th class="r">Monthly Rent</th>
                        <th>Occupants</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    @foreach ($leases as $lease)
                        <tr>
                            <td class="nowrap"><b>MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }}</b></td>
                            <td>
                                <b>{{ $lease->room->room_no }}</b>
                                <div class="sub nowrap">{{ $lease->room->room_type }}</div>
                            </td>
                            <td>
                                <a href="{{ route('tenants.show', $lease->tenant) }}" style="font-weight: 600; text-decoration: none; color: inherit;">
                                    {{ $lease->tenant->full_name }}
                                </a>
                                <div class="sub nowrap muted">{{ $lease->tenant->contact_no }}</div>
                            </td>
                            <td class="nowrap">{{ $lease->move_in_date->format('j M Y') }}</td>
                            <td>
                                <span class="tag t-amber" style="font-size: 11px;">Every {{ $lease->dueDayLabel() }}</span>
                            </td>
                            <td class="money">@peso($lease->monthly_rent)</td>
                            <td>
                                <b>{{ $lease->active_occupants_count }}</b> <span class="muted">/ {{ min($lease->room->max_occupants, \App\Models\Room::MAX_OCCUPANTS) }}</span>
                            </td>
                            <td class="nowrap">
                                <x-lease-status :lease="$lease" />
                                @if ($lease->status === 'Active' && $lease->moveout)
                                    <div class="sub">{{ $lease->moveout->actual_date ? 'left '.$lease->moveout->actual_date->format('j M') : 'leaving '.$lease->moveout->planned_date->format('j M') }}</div>
                                @endif
                            </td>
                            <td class="actions nowrap">
                                <a href="{{ route('leases.show', $lease) }}" class="btn sec">View MOA</a>
                                <a href="{{ route('leases.household', $lease) }}" class="btn sec" title="Manage occupants, appliances, and visitors">Household</a>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
