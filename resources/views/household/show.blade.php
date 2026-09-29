@extends('layouts.app')

@section('title', 'Appliances & visitors')

{{-- Used by every role. Receives only $household (no rent, deposits, MOA or ID details). --}}
@section('content')
    <p class="page-intro">
        <b>Room {{ $household['room_no'] }}</b> — {{ $household['tenant_name'] }}
        @unless ($household['lease_active']) <span class="tag t-grey">Lease no longer active</span> @endunless
        &nbsp; <a href="{{ route('tenants.index') }}">‹ Back to the list</a>
    </p>
    @include('household._appliances')
    @include('household._visitors')
@endsection
