@extends('layouts.app')

@section('title', 'Update concern')

@section('content')
    <div class="panel form-panel">
        <h2>{{ $concern->location() }} <x-concern-status :concern="$concern" /></h2>
        <form method="POST" action="{{ route('concerns.update', $concern) }}" enctype="multipart/form-data" novalidate>
            @method('PUT')
            @include('concerns._form')
            <div class="form-actions">
                <button type="submit" class="btn">Save changes</button>
                <a href="{{ route('concerns.show', $concern) }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
