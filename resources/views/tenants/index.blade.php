@extends('layouts.app')

@section('title', 'Tenants')

@section('content')
    {{-- Onboarding Flash Prompt when a Tenant is just registered --}}
    @if (session('prompt_lease') && session('new_tenant_id'))
        <div class="panel" style="background: #eff6ff; border: 1px solid #bfdbfe; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3 style="margin: 0 0 4px; color: #1e40af;">⚡ Next Step: Assign a Room & Create MOA</h3>
                    <p style="margin: 0; color: #1e3a8a; font-size: 13.5px;">
                        <b>{{ session('new_tenant_name') }}</b> is registered in the master directory. Would you like to proceed with room assignment and lease terms?
                    </p>
                </div>
                <div>
                    <a href="{{ route('leases.create', ['tenant_id' => session('new_tenant_id')]) }}" class="btn">
                        + Register Lease for this Tenant
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Metric Summary Cards --}}
    <div class="cards">
        <div class="card">
            <div class="n">{{ $metrics['total'] }}</div>
            <div class="l">Registered Tenants</div>
        </div>
        <div class="card">
            <div class="n green">{{ $metrics['active_renters'] }}</div>
            <div class="l">Active Renters (Occupying Room)</div>
        </div>
        <div class="card">
            <div class="n amber">{{ $metrics['past_renters'] }}</div>
            <div class="l">Past / Returning Prospects</div>
        </div>
        @if ($metrics['blacklisted'] > 0)
            <div class="card">
                <div class="n red">{{ $metrics['blacklisted'] }}</div>
                <div class="l">Blacklisted Tenants</div>
            </div>
        @endif
    </div>

    {{-- Main Master Directory Panel --}}
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
            <div>
                <h2 style="margin: 0;">
                    Tenants Directory
                    <span class="muted" style="font-size: 13px; font-weight: normal; margin-left: 8px;">Master identity profiles & verification</span>
                </h2>
            </div>
            <div class="actions">
                <a href="{{ route('tenants.create') }}" class="btn">+ Register Tenant</a>
                <a href="{{ route('leases.index') }}" class="btn sec">View Active Leases &rarr;</a>
            </div>
        </div>

        {{-- Search & Filters Bar --}}
        <form method="GET" action="{{ route('tenants.index') }}" class="billing-filter-bar" style="margin-bottom: 16px;">
            <div class="billing-filter-group">
                <span class="muted" style="font-size: 13px;">Status:</span>
                @foreach ($statuses as $st)
                    <a href="{{ route('tenants.index', ['status' => $st, 'search' => $search, 'tenancy' => $tenancyFilter]) }}"
                       class="chip {{ $status === $st ? 'on' : '' }}">{{ $st }}</a>
                @endforeach
            </div>

            <div class="billing-filter-group">
                <span class="muted" style="font-size: 13px;">Tenancy:</span>
                @foreach (['all' => 'All', 'active' => 'Currently Renting', 'past' => 'No Active Lease'] as $key => $lbl)
                    <a href="{{ route('tenants.index', ['status' => $status, 'search' => $search, 'tenancy' => $key]) }}"
                       class="chip {{ $tenancyFilter === $key ? 'on' : '' }}">{{ $lbl }}</a>
                @endforeach
            </div>

            <div class="billing-filter-group">
                <input type="text" name="search" class="search-input" placeholder="Search name, phone, ID, room..." value="{{ $search }}">
                <button type="submit" class="btn sec" style="padding: 6px 12px; font-size: 13px;">Search</button>
                @if ($search !== '' || $status !== 'All' || $tenancyFilter !== 'all')
                    <a href="{{ route('tenants.index') }}" class="btn sec" style="padding: 6px 10px; font-size: 13px;">Clear</a>
                @endif
            </div>
        </form>

        @if ($tenants->isEmpty())
            <p class="muted">No tenants found matching the filter criteria.
                @if ($search !== '' || $status !== 'All' || $tenancyFilter !== 'all')
                    <a href="{{ route('tenants.index') }}">Clear filters</a> or
                @endif
                <a href="{{ route('tenants.create') }}">register a new tenant &rarr;</a>
            </p>
        @else
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Tenant ID</th>
                        <th>Full Name</th>
                        <th>Contact No.</th>
                        <th>Valid ID</th>
                        <th>Provincial Origin</th>
                        <th>Current Room</th>
                        <th>Profile Status</th>
                        <th></th>
                    </tr>
                    @foreach ($tenants as $tenant)
                        @php($active = $tenant->activeLease)
                        <tr>
                            <td class="nowrap"><span class="muted">TNT-{{ str_pad($tenant->tenant_id, 4, '0', STR_PAD_LEFT) }}</span></td>
                            <td>
                                <a href="{{ route('tenants.show', $tenant) }}" style="font-weight: 600; text-decoration: none; color: inherit;">
                                    {{ $tenant->full_name }}
                                </a>
                                @if ($tenant->occupation)
                                    <div class="sub nowrap muted">{{ $tenant->occupation }}</div>
                                @endif
                            </td>
                            <td class="nowrap">{{ $tenant->contact_no }}</td>
                            <td>
                                <b>{{ $tenant->valid_id_type }}</b>
                                <div class="sub nowrap muted">{{ $tenant->valid_id_no }}</div>
                            </td>
                            <td>{{ $tenant->provincial_address ?: '—' }}</td>
                            <td class="nowrap">
                                @if ($active)
                                    <a href="{{ route('leases.show', $active) }}" class="tag t-green" style="text-decoration: none;">
                                        Room {{ $active->room->room_no }} ({{ $active->room->room_type }})
                                    </a>
                                @else
                                    <span class="muted">None (Past)</span>
                                @endif
                            </td>
                            <td>
                                <span class="tag {{ $tenant->status === 'Active' ? 't-green' : ($tenant->status === 'Blacklisted' ? 't-red' : 't-amber') }}" style="font-size: 11px;">
                                    {{ $tenant->status }}
                                </span>
                            </td>
                            <td class="actions nowrap">
                                <a href="{{ route('tenants.show', $tenant) }}" class="btn sec" title="View Profile & Tenancy History">Profile</a>
                                @if (! $active)
                                    <a href="{{ route('leases.create', ['tenant_id' => $tenant->tenant_id]) }}" class="btn" title="Create Lease for this tenant">+ Lease</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
