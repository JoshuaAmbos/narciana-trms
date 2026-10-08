@extends('layouts.app')

@section('title', 'Inquiries')

@section('content')
    <div class="panel">
        <h2>Log an inquiry</h2>
        <form method="POST" action="{{ route('inquiries.store') }}" novalidate>
            @include('inquiries._fields')
            <div class="form-actions">
                <button type="submit" class="btn">Save inquiry</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Inquiry log</h2>

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
                    <tr><th>Date</th><th>Name</th><th>Contact</th><th>Wants</th><th>Channel</th><th>Status</th><th>Room</th><th>Logged by</th><th></th></tr>
                    @foreach ($inquiries as $inquiry)
                        <tr>
                            <td>{{ $inquiry->inquiry_date->format('j M Y') }}</td>
                            <td>
                                {{ $inquiry->prospect_name }}
                                @if ($inquiry->remarks)<div class="sub">{{ $inquiry->remarks }}</div>@endif
                            </td>
                            <td><a href="{{ $inquiry->telHref() }}">{{ $inquiry->contact_no }}</a>
                                @if ($inquiry->wants_contact)<div class="sub nowrap">wants to be contacted</div>@endif</td>
                            <td>{{ $inquiry->preferred_type }}, {{ $inquiry->no_of_occupants }}</td>
                            <td>{{ $inquiry->channel }}@if ($inquiry->via_representative)<div class="sub">representative</div>@endif</td>
                            <td><x-inquiry-status :status="$inquiry->status" /></td>
                            <td>{{ $inquiry->room?->room_no ?? '–' }}</td>
                            <td>{{ $inquiry->user->role }}</td>
                            <td class="actions">
                                @can('manage-leases')
                                    @if ($inquiry->status === 'Waiting' && (! $inquiry->room_id || $inquiry->room?->status === 'Vacant'))
                                        <a href="{{ route('leases.create', array_filter(['inquiry_id' => $inquiry->inquiry_id, 'room_id' => $inquiry->room_id])) }}" class="btn" style="padding: 2px 8px; font-size: 11.5px; margin-right: 4px;">Encode MOA</a>
                                    @endif
                                @endcan
                                <a href="{{ route('inquiries.edit', $inquiry) }}" class="btn sec">Update</a>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $inquiries])
        @endif
    </div>
@endsection
