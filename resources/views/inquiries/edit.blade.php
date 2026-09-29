@extends('layouts.app')

@section('title', 'Update inquiry')

@section('content')
    <div class="panel form-panel">
        <h2>
            Inquiry from {{ $inquiry->prospect_name }}
            <x-inquiry-status :status="$inquiry->status" />
        </h2>
        <p class="muted">Logged by the {{ $inquiry->user->role }} on {{ $inquiry->inquiry_date->format('j M Y') }}.</p>
        <form method="POST" action="{{ route('inquiries.update', $inquiry) }}" novalidate>
            @method('PUT')
            @include('inquiries._fields')
            <p class="muted">Marking an inquiry as Rented does not change the room's status; that happens when the MOA is encoded.</p>
            <div class="form-actions">
                <button type="submit" class="btn">Save changes</button>
                <a href="{{ route('inquiries.index') }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
