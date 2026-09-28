@extends('layouts.app')

@section('title', 'No access')

@section('content')
    <div class="panel form-panel narrow">
        <h2>You do not have access to this page</h2>
        <p>
            This part of the system is not available for your role{{ auth()->check() ? ' ('.auth()->user()->role.')' : '' }}.
            If you need it, please ask the Owner.
        </p>
        <a href="{{ route('dashboard') }}" class="btn">Back to the dashboard</a>
    </div>
@endsection
