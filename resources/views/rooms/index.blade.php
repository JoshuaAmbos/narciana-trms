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
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Room</th><th>Type</th><th class="r">Rent</th><th>Status</th><th>Available</th>
                        @canany(['manage-rooms', 'manage-leases'])<th></th>@endcanany
                    </tr>
                    @foreach ($rooms as $room)
                        <tr>
                            <td><b>{{ $room->room_no }}</b>
                                <a href="{{ route('concerns.index', ['room' => $room->room_id]) }}" class="sub">concern history</a></td>
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
                </table>
            </div>
            <p class="muted">Status changes automatically: Occupied when an MOA is encoded, Vacating when a move-out notice is recorded.</p>
        @endif
    </div>

@endsection
