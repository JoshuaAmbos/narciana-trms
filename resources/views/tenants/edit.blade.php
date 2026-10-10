@extends('layouts.app')

@section('title', 'Edit tenant')

@section('content')
    <div class="panel form-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <h2 style="margin: 0;">Tenant profile</h2>
            <a href="{{ route('tenants.index') }}" class="btn sec">&larr; Back to Tenants</a>
        </div>
        <form method="POST" action="{{ route('tenants.update', $tenant) }}" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="return_to" value="{{ route('tenants.index') }}">
            @include('tenants._fields', ['tenant' => $tenant])
            <div class="form-actions">
                <button type="submit" class="btn">Save changes</button>
                <a href="{{ route('tenants.index') }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
