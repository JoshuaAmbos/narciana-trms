@extends('layouts.app')

@section('title', 'Log concern')

@section('content')
    <div class="panel form-panel">
        <h2>New concern</h2>
        <form method="POST" action="{{ route('concerns.store') }}" enctype="multipart/form-data" novalidate>
            @include('concerns._form')
            <div class="form-actions">
                <button type="submit" class="btn">Save concern</button>
                <a href="{{ route('concerns.index') }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
