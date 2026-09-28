@extends('layouts.app')

@section('title', 'Add user')

@section('content')
    <div class="panel form-panel">
        <h2>New user account</h2>
        <form method="POST" action="{{ route('users.store') }}" novalidate>
            @include('users._form', ['canChangeRole' => true, 'passwordRequired' => true])
            <div class="form-actions">
                <button type="submit" class="btn">Save user</button>
                <a href="{{ route('users.index') }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
