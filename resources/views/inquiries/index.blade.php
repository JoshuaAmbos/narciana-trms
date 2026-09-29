@extends('layouts.app')

@section('title', 'Inquiry log')

@section('content')
    <div class="panel">
        <h2>
            Inquiry log
            <a href="{{ route('rooms.index') }}" class="btn">+ Log an inquiry</a>
        </h2>

        <div class="filters">
            <span class="muted">Show:</span>
            <a href="{{ route('inquiries.index') }}" class="chip {{ $status ? '' : 'on' }}">All</a>
            @foreach ($statuses as $s)
                <a href="{{ route('inquiries.index', ['status' => $s]) }}" class="chip {{ $status === $s ? 'on' : '' }}">{{ $s }}</a>
            @endforeach
        </div>

        @if ($inquiries->isEmpty())
            <p class="muted">No inquiries{{ $status ? " with status {$status}" : '' }}.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Date</th><th>Name</th><th>Contact</th><th>Wants</th><th>Channel</th><th>Outcome</th><th>Room</th><th>Logged by</th><th></th></tr>
                    @foreach ($inquiries as $inquiry)
                        <tr>
                            <td>{{ $inquiry->inquiry_date->format('j M Y') }}</td>
                            <td>
                                {{ $inquiry->prospect_name }}
                                @if ($inquiry->remarks)<div class="sub">{{ $inquiry->remarks }}</div>@endif
                            </td>
                            <td><a href="{{ $inquiry->telHref() }}">{{ $inquiry->contact_no }}</a></td>
                            <td>{{ $inquiry->preferred_type }}, {{ $inquiry->no_of_occupants }}</td>
                            <td>{{ $inquiry->channel }}@if ($inquiry->via_representative)<div class="sub">representative</div>@endif</td>
                            <td><x-inquiry-status :status="$inquiry->status" /></td>
                            <td>{{ $inquiry->room?->room_no ?? '–' }}</td>
                            <td>{{ $inquiry->user->role }}</td>
                            <td class="actions"><a href="{{ route('inquiries.edit', $inquiry) }}" class="btn sec">Update</a></td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $inquiries])
        @endif
    </div>
@endsection
