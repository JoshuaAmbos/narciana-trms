@extends('layouts.app')

@section('title', 'Concern: '.$concern->location())

@section('content')
    <div class="grid3">
        <div class="panel">
            <h2>
                <span>{{ $concern->location() }} <x-concern-status :concern="$concern" /></span>
                <a href="{{ route('concerns.edit', $concern) }}" class="btn">Update</a>
            </h2>
            <p class="concern-text">{{ $concern->description }}</p>
            <dl class="facts">
                <div><dt>Category</dt><dd>{{ $concern->category }}</dd></div>
                <div><dt>Reported</dt><dd>{{ $concern->reported_at->format('j M Y, g:i A') }} ({{ $concern->channel }})</dd></div>
                <div><dt>Tenant</dt><dd>{{ $concern->lease?->tenant->full_name ?? '–' }}</dd></div>
                <div><dt>Assigned to</dt><dd>{{ $concern->assignedTo->role }}</dd></div>
                <div><dt>Resolved</dt><dd>{{ $concern->resolved_at?->format('j M Y, g:i A') ?? 'Not yet' }}</dd></div>
                <div><dt>Cost</dt><dd>{{ $concern->cost !== null ? '₱'.number_format((float) $concern->cost, 2) : '–' }}
                    @if ($concern->charged_to_tenant) <span class="tag t-amber">charged to tenant</span> @endif</dd></div>
            </dl>
            @if ($concern->resolution)
                <p><b>How it was resolved:</b> {{ $concern->resolution }}</p>
            @endif
            @if ($concern->bill)
                <p class="paid-note">Charged on the bill due {{ $concern->bill->due_date->format('j M Y') }}
                    @can('manage-leases') — <a href="{{ route('bills.show', $concern->bill) }}">open the bill</a>@endcan</p>
            @elseif ($concern->charged_to_tenant && $concern->status === 'Resolved')
                <p class="muted">Will be offered on this tenant's next bill (the Manager picks it when generating).</p>
            @endif
            <p>
                @if ($concern->room)
                    <a href="{{ route('concerns.index', ['room' => $concern->room_id]) }}">History of {{ $concern->location() }}</a> ·
                @endif
                <a href="{{ route('concerns.index') }}">All concerns</a>
            </p>
        </div>
        <div class="panel">
            <h2>Photo</h2>
            @if ($concern->photo_path)
                <a href="{{ $concern->photoUrl() }}" target="_blank"><img src="{{ $concern->photoUrl() }}" alt="Photo of the concern" class="concern-photo"></a>
            @else
                <p class="muted">No photo.</p>
            @endif
        </div>
    </div>
@endsection
