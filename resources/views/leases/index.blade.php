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
                    Leases &amp; MOA Directory
                </h2>
            </div>
            <div class="actions">
                <a href="{{ route('tenants.index') }}" class="btn sec">Tenants Directory &rarr;</a>
                @can('manage-leases')
                    <a href="{{ route('leases.create') }}" class="btn">+ Encode New MOA</a>
                @endcan
            </div>
        </div>

        {{-- Search & Filters Bar --}}
        <form method="GET" action="{{ route('leases.index') }}" class="tenant-filter-bar">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="room_type" value="{{ $roomType }}">

            <div class="tenant-filter-row">
                <div class="tenant-filter-groups">
                    <div class="tenant-filter-group">
                        <span class="tenant-filter-label">Status:</span>
                        <div class="tenant-chips-wrap">
                            @foreach (['Active' => 'Active', 'Ended' => 'Ended', 'Cancelled' => 'Cancelled', 'All' => 'All'] as $st => $label)
                                <a href="{{ route('leases.index', ['status' => $st, 'search' => $search, 'room_type' => $roomType]) }}"
                                   class="chip {{ $status === $st ? 'on' : '' }}">{{ $label }} ({{ $counts[$st] ?? 0 }})</a>
                            @endforeach
                        </div>
                    </div>

                    <div class="tenant-filter-group">
                        <span class="tenant-filter-label">Room Type:</span>
                        <div class="tenant-chips-wrap">
                            @foreach (['All', 'Basic', 'Aircon'] as $rt)
                                <a href="{{ route('leases.index', ['status' => $status, 'search' => $search, 'room_type' => $rt]) }}"
                                   class="chip {{ $roomType === $rt ? 'on' : '' }}">{{ $rt }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="tenant-search-form">
                    <div class="tenant-search-input-wrap">
                        <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" name="search" class="search-input" placeholder="Search room no or tenant name..." value="{{ $search }}" autocomplete="off">
                    </div>
                    <button type="submit" class="btn sec tenant-search-btn">Search</button>
                    @if ($search !== '' || $roomType !== 'All' || $status !== 'Active')
                        <a href="{{ route('leases.index') }}" class="btn sec tenant-clear-btn" title="Clear all filters">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($leases->isEmpty())
            <p class="muted">No {{ strtolower($status) }} lease agreements found.
                @if ($status === 'Active')
                    <a href="{{ route('leases.create') }}">+ Encode New MOA &rarr;</a>
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
                        <th class="tenant-actions-header">Actions</th>
                    </tr>
                    @foreach ($leases as $lease)
                        <tr>
                            <td class="nowrap"><b>MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }}</b></td>
                            <td>
                                <b>Room {{ $lease->room->room_no }}</b>
                                <div class="sub nowrap muted">{{ $lease->room->room_type }}</div>
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
                            <td class="tenant-actions-cell nowrap">
                                <div class="tenant-action-btns">
                                    <a href="{{ route('leases.show', $lease) }}" class="btn sec">View MOA</a>
                                    <a href="{{ route('leases.household', $lease) }}" class="btn sec" title="Manage occupants, appliances, and visitors">Household</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
