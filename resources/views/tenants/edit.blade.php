@extends('layouts.app')

@section('title', 'Edit tenant')

@section('content')
    <div class="panel form-panel">
        <h2>Tenant profile</h2>
        <form method="POST" action="{{ route('tenants.update', $tenant) }}" novalidate>
            @csrf
            @method('PUT')
            @include('tenants._fields', ['tenant' => $tenant])
            <div class="form-actions">
                <button type="submit" class="btn">Save changes</button>
                <a href="{{ $activeLease ? route('leases.show', $activeLease) : route('tenants.index') }}" class="btn sec">Cancel</a>
            </div>
        </form>
    </div>
@endsection
