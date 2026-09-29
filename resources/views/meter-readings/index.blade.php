@extends('layouts.app')

@section('title', 'Meter readings')

@section('content')
    <div class="panel">
        <h2>Meter readings <a href="{{ route('bills.index') }}" class="btn sec">Back to Billing</a></h2>
        <div class="filters">
            <span class="muted">Room:</span>
            <a href="{{ route('meter-readings.index') }}" class="chip {{ $roomId ? '' : 'on' }}">All</a>
            @foreach ($rooms as $room)
                <a href="{{ route('meter-readings.index', ['room' => $room->room_id]) }}" class="chip {{ $roomId === $room->room_id ? 'on' : '' }}">{{ $room->room_no }}</a>
            @endforeach
        </div>
        @if ($readings->isEmpty())
            <p class="muted">No readings yet. Readings are entered when a bill is generated (or at move-in on the MOA form).</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Date</th><th>Room</th><th class="r">Previous</th><th class="r">Current</th><th class="r">kWh used</th><th class="r">Rate</th><th>Source</th><th>Used for</th><th>By</th></tr>
                    @foreach ($readings as $r)
                        <tr>
                            <td class="nowrap">{{ $r->reading_date->format('j M Y') }}</td>
                            <td><b>{{ $r->room->room_no }}</b></td>
                            <td class="money">{{ number_format((float) $r->previous_reading, 2) }}</td>
                            <td class="money">{{ number_format((float) $r->current_reading, 2) }}</td>
                            <td class="money">{{ number_format((float) $r->current_reading - (float) $r->previous_reading, 2) }}</td>
                            <td class="money">@peso($r->rate_per_kwh)</td>
                            <td>{{ $r->source }}</td>
                            <td>
                                @forelse ($r->bills as $b)
                                    <a href="{{ route('bills.show', $b) }}">Bill due {{ $b->due_date->format('j M Y') }}</a>
                                @empty
                                    <span class="muted">Move-in reading</span>
                                @endforelse
                            </td>
                            <td>{{ $r->user->role }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $readings])
        @endif
    </div>
@endsection
