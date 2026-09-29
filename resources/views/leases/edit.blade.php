@extends('layouts.app')

@section('title', 'Edit MOA')

@section('content')
    <div class="panel form-panel">
        <h2>MOA details: Room {{ $lease->room->room_no }} — {{ $lease->tenant->full_name }}</h2>
        <form method="POST" action="{{ route('leases.update', $lease) }}" novalidate>
            @csrf
            @method('PUT')
            @include('leases._moa_fields', ['lease' => $lease])
            <p class="muted">The room and the tenant cannot be changed on an existing MOA. Use "Edit tenant" to correct the tenant's details.</p>
            <div class="form-actions">
                <button type="submit" class="btn">Save changes</button>
                <a href="{{ route('leases.show', $lease) }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
