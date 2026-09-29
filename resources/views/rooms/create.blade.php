@extends('layouts.app')

@section('title', 'Add room')

@section('content')
    <div class="panel form-panel">
        <h2>New room</h2>
        <form method="POST" action="{{ route('rooms.store') }}" novalidate>
            @include('rooms._form')
            <p class="muted">A new room starts as <b>Vacant</b>.</p>
            <div class="form-actions">
                <button type="submit" class="btn">Save room</button>
                <a href="{{ route('rooms.index') }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
