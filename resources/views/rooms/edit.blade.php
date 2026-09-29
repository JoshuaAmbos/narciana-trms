@extends('layouts.app')

@section('title', 'Edit room')

@section('content')
    <div class="panel form-panel">
        <h2>Room {{ $room->room_no }} <x-room-status :room="$room" /></h2>
        <form method="POST" action="{{ route('rooms.update', $room) }}" novalidate>
            @method('PUT')
            @include('rooms._form')
            <p class="muted">The status is updated by the system (MOA encoded, move-out notice, move-out completed) and cannot be changed here.</p>
            <div class="form-actions">
                <button type="submit" class="btn">Save changes</button>
                <a href="{{ route('rooms.index') }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
