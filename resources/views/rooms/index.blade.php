@extends('layouts.app')

@section('title', 'Rooms')

@section('content')
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
            <h2 style="margin: 0;">
                Room availability
            </h2>
            @can('manage-rooms')
                <div class="actions">
                    <a href="{{ route('rooms.create') }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center;">+ Add room</a>
                </div>
            @endcan
        </div>

        {{-- Search & Filters Bar --}}
        <form method="GET" action="{{ route('rooms.index') }}" class="room-filter-bar" id="room-filter-form">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="room-filter-row">
                <div class="room-filter-groups">
                    {{-- Status Filter --}}
                    <div class="room-filter-group">
                        <span class="room-filter-label">Status:</span>
                        <div class="room-chips-wrap">
                            @foreach ($statuses as $st)
                                <a href="{{ route('rooms.index', array_filter(['status' => $st, 'type' => $type !== 'All' ? $type : null, 'search' => $search !== '' ? $search : null])) }}"
                                   class="chip {{ $status === $st ? 'on' : '' }}">
                                    {{ $st }}
                                    @if (isset($statusCounts[$st]))
                                        <span class="chip-count">({{ $statusCounts[$st] }})</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Room Type Filter --}}
                    <div class="room-filter-group">
                        <span class="room-filter-label">Type:</span>
                        <div class="room-chips-wrap">
                            @foreach ($types as $tp)
                                <a href="{{ route('rooms.index', array_filter(['type' => $tp, 'status' => $status !== 'All' ? $status : null, 'search' => $search !== '' ? $search : null])) }}"
                                   class="chip {{ $type === $tp ? 'on' : '' }}">{{ $tp }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Search Bar --}}
                <div class="room-search-form">
                    <div class="room-search-input-wrap">
                        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text"
                               id="room-search-input"
                               name="search"
                               class="room-search-input"
                               placeholder="Search room no..."
                               value="{{ $search }}"
                               autocomplete="off"
                               aria-label="Search room number">
                        @if ($search !== '')
                            <a href="{{ route('rooms.index', array_filter(['status' => $status !== 'All' ? $status : null, 'type' => $type !== 'All' ? $type : null])) }}"
                               class="room-search-clear-btn"
                               id="room-search-clear"
                               title="Clear search"
                               aria-label="Clear room search">
                                &times;
                            </a>
                        @endif
                    </div>
                    <div class="room-search-actions">
                        <button type="submit" class="btn sec room-search-submit-btn">Search</button>
                        @if ($search !== '' || $status !== 'All' || $type !== 'All')
                            <a href="{{ route('rooms.index') }}" class="btn sec room-reset-btn" title="Reset all filters">Reset</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        @if ($rooms->isEmpty())
            @if ($search !== '' || $status !== 'All' || $type !== 'All')
                <div style="padding: 24px 0;">
                    <p class="muted">No rooms found matching your search and filter criteria.</p>
                    <a href="{{ route('rooms.index') }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center;">Clear all filters</a>
                </div>
            @else
                <p class="muted">No rooms yet.@can('manage-rooms') Use "+ Add room" to add the first one.@endcan</p>
            @endif
        @else
            {{-- Desktop / Wide Tablet View --}}
            <div class="table-wrap rooms-table-wrap hidden md:block">
                <table>
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Type &amp; Setup</th>
                            <th class="r">Monthly Rent</th>
                            <th>Status</th>
                            <th>Available</th>
                            <th>Waiting Demand</th>
                            @canany(['manage-rooms', 'manage-leases'])<th>Actions</th>@endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rooms as $room)
                            <tr>
                                <td>
                                    <b>{{ $room->room_no }}</b>
                                    <div><a href="{{ route('concerns.index', ['room' => $room->room_id]) }}" class="sub">concern history</a></div>
                                </td>
                                <td>
                                    {{ $room->room_type }}
                                    <div class="sub nowrap">{{ $room->is_metered ? 'Own meter' : 'No meter' }} · max {{ $room->max_occupants }}</div>
                                </td>
                                <td class="money">@peso($room->monthly_rent)</td>
                                <td><x-room-status :room="$room" /></td>
                                <td>
                                    @if ($room->status === 'Vacant')
                                        Now
                                    @else
                                        <span class="sub">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($room->waiting_demand > 0)
                                        <span class="tag t-blue">{{ $room->waiting_demand }} waiting</span>
                                    @else
                                        <span class="sub">None</span>
                                    @endif
                                </td>
                                @canany(['manage-rooms', 'manage-leases'])
                                    <td class="actions">
                                        @if ($room->status === 'Vacant')
                                            @can('manage-leases')
                                                <a href="{{ route('leases.create', ['room_id' => $room->room_id]) }}" class="btn">Encode MOA</a>
                                            @endcan
                                        @endif
                                        @can('manage-rooms')
                                            <a href="{{ route('rooms.edit', $room) }}" class="btn sec">Edit</a>
                                        @endcan
                                    </td>
                                @endcanany
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards View (Responsive Android phones / stacked viewports) --}}
            <div class="room-cards block md:hidden">
                @foreach ($rooms as $room)
                    <div class="room-card w-full rounded-lg border p-4 shadow-sm mb-3">
                        <div class="room-card-head flex items-center justify-between mb-2">
                            <span class="room-card-no text-2xl sm:text-3xl font-extrabold tracking-tight">Room {{ $room->room_no }}</span>
                            <x-room-status :room="$room" />
                        </div>
                        <div class="room-card-specs text-sm text-muted mb-2">
                            {{ $room->room_type }} · {{ $room->is_metered ? 'Own meter' : 'No meter' }} · max {{ $room->max_occupants }} {{ Str::plural('occupant', $room->max_occupants) }}
                        </div>
                        <div class="room-card-body flex items-center justify-between py-2 border-t border-b mb-3 text-sm">
                            <div>
                                <span class="font-bold text-base">@peso($room->monthly_rent)</span> <span class="text-xs text-muted">/ mo</span>
                            </div>
                            <div class="text-right">
                                <span class="font-medium">
                                    @if ($room->status === 'Vacant')
                                        Available: <span class="text-green-700 font-semibold">Now</span>
                                    @else
                                        <span class="text-muted">Occupied</span>
                                    @endif
                                </span>
                                @if ($room->waiting_demand > 0)
                                    <div class="text-xs text-blue-700 mt-0.5 font-medium">{{ $room->waiting_demand }} waiting</div>
                                @endif
                            </div>
                        </div>
                        <div class="room-card-actions flex items-center gap-2">
                            <a href="{{ route('concerns.index', ['room' => $room->room_id]) }}" class="btn sec flex-1 justify-center text-center">
                                Concern history
                            </a>
                            @if ($room->status === 'Vacant')
                                @can('manage-leases')
                                    <a href="{{ route('leases.create', ['room_id' => $room->room_id]) }}" class="btn flex-1 justify-center text-center">
                                        Encode MOA
                                    </a>
                                @endcan
                            @endif
                            @can('manage-rooms')
                                <a href="{{ route('rooms.edit', $room) }}" class="btn sec justify-center text-center">
                                    Edit
                                </a>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>

            @include('partials.pagination', ['paginator' => $rooms])

            <p class="muted">Status changes automatically: Occupied when an MOA is encoded, Vacant when a lease is ended.</p>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('room-search-input');
            const clearBtn = document.getElementById('room-search-clear');
            if (searchInput && clearBtn) {
                searchInput.addEventListener('input', function () {
                    clearBtn.style.display = this.value.length > 0 ? 'inline-flex' : 'none';
                });
            }
        });
    </script>
@endsection
