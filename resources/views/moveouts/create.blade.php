@extends('layouts.app')

@section('title', 'Move-out notice')

@section('content')
    <div class="panel form-panel narrow">
        <h2>Notice: Room {{ $lease->room->room_no }} <span class="muted">{{ $lease->tenant->full_name }}</span></h2>
        <form method="POST" action="{{ route('moveouts.store') }}" novalidate>
            @csrf
            <input type="hidden" name="lease_id" value="{{ $lease->lease_id }}">
            <div class="f @error('notice_date') has-err @enderror">
                <label for="notice_date">Notice given on</label>
                <input id="notice_date" name="notice_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('notice_date', $noticeDate->toDateString()) }}">
                @error('notice_date') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="f @error('planned_date') has-err @enderror">
                <label for="planned_date">Planned move-out date</label>
                <input id="planned_date" name="planned_date" type="date" value="{{ old('planned_date', $plannedDate->toDateString()) }}">
                <div class="hint">The MOA asks for one month's notice (a shorter notice is allowed, with a warning).
                    Minimum stay until {{ $minStayUntil->format('j M Y') }}: leaving earlier forfeits the deposit.</div>
                @error('planned_date') <div class="err">{{ $message }}</div> @enderror
            </div>
            @error('lease') <div class="alert alert-err">{{ $message }}</div> @enderror
            <p class="muted">Saving sets Room {{ $lease->room->room_no }} to <b>Vacating</b>, so waiting prospects can be matched to it.</p>
            <div class="form-actions">
                <button type="submit" class="btn">Save notice</button>
                <a href="{{ route('leases.show', $lease) }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
