@extends('layouts.app')

@section('title', 'Register Tenant')

@section('content')
    <div class="panel form-panel">
        <h2>
            Register Tenant Profile
            <span class="muted" style="font-size: 13.5px; font-weight: normal; margin-left: 8px;">Master identity record & ID verification</span>
        </h2>
        <p class="muted" style="margin-bottom: 20px;">
            Encode the personal identity credentials of the renter. Once registered, an active lease (MOA) can be created and assigned to any available room.
        </p>

        <form method="POST" action="{{ route('tenants.store') }}" novalidate>
            @csrf
            @include('tenants._fields', ['tenant' => $tenant])

            <div class="form-actions" style="margin-top: 24px; display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                <a href="{{ route('tenants.index') }}" class="btn sec">Cancel</a>
                <button type="submit" class="btn">Register</button>
            </div>
        </form>
    </div>
@endsection
