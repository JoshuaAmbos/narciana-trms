@extends('layouts.app')

@section('title', 'Rooms')

@section('content')
    <div class="panel">
        <h2>
            Room availability
            @can('manage-rooms')
                <a href="{{ route('rooms.create') }}" class="btn sec">+ Add room</a>
            @endcan
        </h2>

        @if ($rooms->isEmpty())
            <p class="muted">No rooms yet.@can('manage-rooms') Use "+ Add room" to add the first one.@endcan</p>
        @else
            {{-- Desktop / Wide Tablet View --}}
            <div class="table-wrap rooms-table-wrap hidden md:block">
                <table>
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Type & Setup</th>
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
                                    @elseif ($room->status === 'Vacating')
                                        {{ $room->expected_vacant_date?->format('j M Y') ?? 'Date not set' }}
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
                                    @elseif ($room->status === 'Vacating')
                                        Available: <span class="text-amber-700 font-semibold">{{ $room->expected_vacant_date?->format('j M Y') ?? 'Date not set' }}</span>
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

            <p class="muted">Status changes automatically: Occupied when an MOA is encoded, Vacating when a move-out notice is recorded.</p>
        @endif
    </div>
@endsection
