@extends('layouts.app')

@section('title', 'Rooms & Inquiries')

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
                        <th>Room</th><th>Type</th><th class="r">Rent</th><th>Status</th><th>Available</th><th>Waiting</th>
                        @can('manage-rooms')<th></th>@endcan
                    </tr>
                    @foreach ($rooms as $room)
                        <tr>
                            <td><b>{{ $room->room_no }}</b></td>
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
                            <td>
                                @if (isset($matches[$room->room_id]))
                                    @php($count = $matches[$room->room_id]->count())
                                    @if ($count)
                                        <span class="tag t-blue">{{ $count }} {{ Str::plural('prospect', $count) }}</span>
                                    @else
                                        <span class="muted">None</span>
                                    @endif
                                @endif
                            </td>
                            @can('manage-rooms')
                                <td class="actions"><a href="{{ route('rooms.edit', $room) }}" class="btn sec">Edit</a></td>
                            @endcan
                        </tr>
                    @endforeach
                </table>
            </div>
            <p class="muted">Status changes automatically: Occupied when an MOA is encoded, Vacating when a move-out notice is recorded.</p>
        @endif
    </div>

    <div class="panel">
        <h2>Log an inquiry</h2>
        <form method="POST" action="{{ route('inquiries.store') }}" novalidate>
            @include('inquiries._fields')
            <div class="form-actions">
                <button type="submit" class="btn">Save inquiry</button>
                <a href="{{ route('inquiries.index') }}" class="btn sec">Inquiry log</a>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Prospects waiting for a room <span class="muted">Oldest first</span></h2>
        @if ($waiting->isEmpty())
            <p class="muted">No one is waiting for a room.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Date</th><th>Name</th><th>Contact</th><th>Wants</th><th>Fits available room</th><th>Status</th><th></th></tr>
                    @foreach ($waiting as $prospect)
                        <tr>
                            <td>{{ $prospect->inquiry_date->format('j M Y') }}</td>
                            <td>
                                {{ $prospect->prospect_name }}
                                @if ($prospect->via_representative)<div class="sub">through a representative</div>@endif
                            </td>
                            <td><a href="{{ $prospect->telHref() }}">{{ $prospect->contact_no }}</a></td>
                            <td>{{ $prospect->preferred_type }}, {{ $prospect->no_of_occupants }} {{ Str::plural('occupant', $prospect->no_of_occupants) }}</td>
                            <td>
                                @if (! empty($fitsRooms[$prospect->inquiry_id]))
                                    <b>{{ implode(', ', $fitsRooms[$prospect->inquiry_id]) }}</b>
                                @else
                                    <span class="muted">None right now</span>
                                @endif
                            </td>
                            <td><x-inquiry-status :status="$prospect->status" /></td>
                            <td class="actions">
                                <a href="{{ $prospect->telHref() }}" class="btn sec">Call</a>
                                <a href="{{ route('inquiries.edit', $prospect) }}" class="btn sec">Update</a>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
