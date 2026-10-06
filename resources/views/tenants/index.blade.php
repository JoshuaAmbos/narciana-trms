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
                </h2>
            </div>
            <div class="actions">
                <a href="{{ route('leases.index') }}" class="btn sec">View Active Leases</a>
                <a href="{{ route('tenants.create') }}" class="btn">+ Register Tenant</a>
            </div>
        </div>

        {{-- Search & Filters Bar --}}
        <form method="GET" action="{{ route('tenants.index') }}" class="tenant-filter-bar">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="tenancy" value="{{ $tenancyFilter }}">

            <div class="tenant-filter-row">
                <div class="tenant-filter-groups">
                    <div class="tenant-filter-group">
                        <span class="tenant-filter-label">Status:</span>
                        <div class="tenant-chips-wrap">
                            @foreach ($statuses as $st)
                                <a href="{{ route('tenants.index', ['status' => $st, 'search' => $search, 'tenancy' => $tenancyFilter]) }}"
                                   class="chip {{ $status === $st ? 'on' : '' }}">{{ $st }}</a>
                            @endforeach
                        </div>
                    </div>

                    <div class="tenant-filter-group">
                        <span class="tenant-filter-label">Tenancy:</span>
                        <div class="tenant-chips-wrap">
                            @foreach (['all' => 'All', 'active' => 'Currently Renting', 'past' => 'No Active Lease'] as $key => $lbl)
                                <a href="{{ route('tenants.index', ['status' => $status, 'search' => $search, 'tenancy' => $key]) }}"
                                   class="chip {{ $tenancyFilter === $key ? 'on' : '' }}">{{ $lbl }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="tenant-search-form">
                    <div class="tenant-search-input-wrap">
                        <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" name="search" class="search-input" placeholder="Search name, phone, ID, room..." value="{{ $search }}" autocomplete="off">
                    </div>
                    <button type="submit" class="btn sec tenant-search-btn">Search</button>
                    @if ($search !== '' || $status !== 'All' || $tenancyFilter !== 'all')
                        <a href="{{ route('tenants.index') }}" class="btn sec tenant-clear-btn" title="Clear all filters">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($tenants->isEmpty())
            <p class="muted">No tenants found matching the filter criteria.
                @if ($search !== '' || $status !== 'All' || $tenancyFilter !== 'all')
                    <a href="{{ route('tenants.index') }}">Clear filters</a> or
                @endif
                <a href="{{ route('tenants.create') }}">register a new tenant</a>
            </p>
        @else
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Full Name</th>
                        <th>Contact No.</th>
                        <th>Valid ID</th>
                        <th>Provincial Origin</th>
                        <th>Current Room</th>
                        <th>Profile Status</th>
                        <th class="tenant-actions-header">Actions</th>
                    </tr>
                    @foreach ($tenants as $tenant)
                        @php($active = $tenant->activeLease)
                        <tr>
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
                                @if ($tenant->other_id_type)
                                    <span class="sub muted">({{ $tenant->other_id_type }})</span>
                                @endif
                                <div class="sub nowrap muted" title="Masked for privacy">{{ $tenant->masked_id_number }}</div>
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
                            <td class="tenant-actions-cell nowrap">
                                <div class="tenant-action-btns">
                                    <a href="{{ route('tenants.show', $tenant) }}" class="btn sec" title="View Profile & Tenancy History">Profile</a>
                                    @if (! $active)
                                        <a href="{{ route('leases.create', ['tenant_id' => $tenant->tenant_id]) }}" class="btn" title="Create Lease for this tenant">+ Lease</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
