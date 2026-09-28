@extends('layouts.app')

@section('title', 'Edit user')

@section('content')
    <div class="panel form-panel">
        <h2>
            Edit account: {{ $user->username }}
            @if ($user->is_active)
                <span class="tag t-green">Active</span>
            @else
                <span class="tag t-grey">Deactivated</span>
            @endif
        </h2>
        <form method="POST" action="{{ route('users.update', $user) }}" novalidate>
            @method('PUT')
            @include('users._form', ['passwordRequired' => false])
            <div class="form-actions">
                <button type="submit" class="btn">Save changes</button>
                <a href="{{ route('users.index') }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
