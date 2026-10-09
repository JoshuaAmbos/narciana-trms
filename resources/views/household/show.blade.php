@extends('layouts.app')

@section('title', 'Appliances & visitors')

{{-- Used by every role. Receives only $household (no rent, deposits, MOA or ID details). --}}
@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <p class="page-intro" style="margin-bottom: 0;">
            <b>Room {{ $household['room_no'] }}</b> — {{ $household['tenant_name'] }}@if (!empty($household['co_tenant_name'])) &amp; {{ $household['co_tenant_name'] }}@endif
            @unless ($household['lease_active']) <span class="tag t-grey">Lease no longer active</span> @endunless
        </p>
        @can('manage-leases')
            <a href="{{ route('leases.index') }}" class="btn sec flex items-center gap-1.5 min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to Leases</span>
            </a>
        @else
            <a href="{{ route('tenants.index') }}" class="btn sec flex items-center gap-1.5 min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to Tenants</span>
            </a>
        @endcan
    </div>
    @include('household._appliances')
    @include('household._visitors')
@endsection
