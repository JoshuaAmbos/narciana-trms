@extends('layouts.app')

@section('title', $room ? 'Concerns: Room '.$room->room_no : 'Concerns')

@section('content')
    <div class="panel">
        <h2>
            {{ $room ? 'History of Room '.$room->room_no : 'Concern log' }}
            <a href="{{ route('concerns.create', $room ? ['room' => $room->room_id] : []) }}" class="btn">+ Log concern</a>
        </h2>

        @if ($repeated->isNotEmpty())
            <div class="alert alert-warn">
                Repeated issue:
                @foreach ($repeated as $category => $times)
                    {{ $category }} reported {{ $times }} times{{ $loop->last ? '' : ';' }}
                @endforeach
                — consider a lasting fix.
            </div>
        @endif

        <div class="filters">
            <span class="muted">Show:</span>
            @foreach (['open' => 'Not resolved', 'Open' => 'Open', 'In progress' => 'In progress', 'Resolved' => 'Resolved', 'all' => 'All'] as $key => $label)
                <a href="{{ route('concerns.index', array_filter(['status' => $key, 'room' => $room?->room_id, 'mine' => $mine ?: null])) }}"
                   class="chip {{ $status === $key ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('concerns.index', array_filter(['status' => $status, 'room' => $room?->room_id, 'mine' => $mine ? null : 1])) }}"
               class="chip {{ $mine ? 'on' : '' }}">Assigned to me</a>
            @if ($room)<a href="{{ route('concerns.index') }}" class="chip">All rooms</a>@endif
        </div>

        @if ($concerns->isEmpty())
            <p class="muted">No concerns here.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Reported</th><th>Where</th><th>Category</th><th>Concern</th><th>Assigned</th><th>Status</th><th></th></tr>
                    @foreach ($concerns as $c)
                        <tr>
                            <td class="nowrap">{{ $c->reported_at->format('j M, g:i A') }}</td>
                            <td>
                                @if ($c->room)
                                    <a href="{{ route('concerns.index', ['room' => $c->room_id]) }}"><b>{{ $c->room->room_no }}</b></a>
                                @else
                                    <b>Shared area</b>
                                @endif
                                @if ($c->lease)<span class="sub">{{ $c->lease->tenant->full_name }}</span>@endif
                            </td>
                            <td>{{ $c->category }}</td>
                            <td>{{ Str::limit($c->description, 70) }}
                                @if ($c->photo_path)<span class="sub">with photo</span>@endif
                                @if ($c->charged_to_tenant)<span class="sub">charged ₱{{ number_format((float) $c->cost, 2) }}{{ $c->bill_id ? ' · billed' : '' }}</span>@endif
                            </td>
                            <td>{{ $c->assignedTo->role }}</td>
                            <td class="nowrap"><x-concern-status :concern="$c" /></td>
                            <td class="actions"><a href="{{ route('concerns.show', $c) }}" class="btn sec">Open</a></td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $concerns])
        @endif
    </div>
@endsection
