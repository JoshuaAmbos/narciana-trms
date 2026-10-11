@extends('layouts.app')

@section('title', 'Tenants')

@section('content')
    {{-- Onboarding Flash Prompt when a Tenant is just registered --}}
    @if (session('prompt_lease') && session('new_tenant_id'))
        <div class="panel" style="background: #eff6ff; border: 1px solid #bfdbfe; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3 style="margin: 0 0 4px; color: #1e40af;">⚡ Next Step: Assign a Room &amp; Create MOA</h3>
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
    @php($tenantCardCount = 3 + ($metrics['blacklisted'] > 0 ? 1 : 0))
    <div class="cards has-{{ $tenantCardCount }}">
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
                <h2 style="margin: 0;">Tenants Directory</h2>
            </div>
            <div class="actions">
                <a href="{{ route('leases.index') }}" class="btn sec">View Active Leases &rarr;</a>
            </div>
        </div>

        {{-- Search & Filters Bar --}}
        <form method="GET" action="{{ route('tenants.index') }}" class="tenant-filter-bar no-print">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="tenancy" value="{{ $tenancyFilter }}">

            <div class="tenant-controls-wrap">
                {{-- Search Input (Option B: Top on Portrait; Option A: Left on Landscape) --}}
                <div class="tenant-search-box">
                    <div class="tenant-search-input-wrap">
                        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" 
                            id="tenant-search-input"
                            name="search" 
                            class="search-input" 
                            placeholder="Search name, phone, ID, room..." 
                            value="{{ $search }}" 
                            autocomplete="off">

                        {{-- Instant Real-Time Clear Button --}}
                        <button type="button" 
                                id="tenant-search-clear" 
                                class="tenant-search-clear-btn"
                                title="Clear search"
                                onclick="document.getElementById('tenant-search-input').value=''; this.style.display='none'; this.form.submit();"
                                style="display: {{ $search ? 'inline-flex' : 'none' }};">
                            &times;
                        </button>
                    </div>
                </div>

                {{-- Filter Chips (Option B: Bottom on Portrait; Option A: Right on Landscape) --}}
                <div class="tenant-chips-container">
                    {{-- Status Chips --}}
                    <div class="tenant-chips-subgroup">
                        <span class="status-label">Status:</span>
                        <div class="tenant-chips-wrap">
                            @foreach ($statuses as $st)
                                <a href="{{ route('tenants.index', ['status' => $st, 'search' => $search, 'tenancy' => $tenancyFilter]) }}"
                                class="chip {{ $status === $st ? 'on' : '' }}">
                                    {{ $st }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Tenancy Chips --}}
                    <div class="tenant-chips-subgroup">
                        <span class="status-label">Tenancy:</span>
                        <div class="tenant-chips-wrap">
                            @foreach (['all' => 'All', 'active' => 'Currently Renting', 'past' => 'No Active Lease'] as $key => $lbl)
                                <a href="{{ route('tenants.index', ['status' => $status, 'search' => $search, 'tenancy' => $key]) }}"
                                class="chip {{ $tenancyFilter === $key ? 'on' : '' }}">
                                    {{ $lbl }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @if ($tenants->isEmpty())
            <p class="muted">No tenants found matching the filter criteria.
                @if ($search !== '' || $status !== 'All' || $tenancyFilter !== 'all')
                    <a href="{{ route('tenants.index') }}">Clear filters</a> or
                @endif
                <a href="{{ route('leases.create') }}">encode an MOA on the Leases page</a>.
            </p>
        @else
            {{-- Desktop & Tablet Landscape: Clean Tabular View with Clickable Rows --}}
            <div class="table-wrap tenants-table-wrap">
                <table>
                    <tr>
                        <th>Tenant Name</th>
                        <th>Current Rented Room</th>
                        <th>Contact Number</th>
                        <th>Valid ID Type</th>
                        <th>Masked ID Number</th>
                        <th>Status</th>
                    </tr>
                    @foreach ($tenants as $tenant)
                        @php($active = $tenant->activeLease)
                        <tr class="tenant-row"
                            tabindex="0"
                            role="button"
                            aria-label="View details and actions for {{ $tenant->full_name }}"
                            onclick="openTenantModal({{ $tenant->tenant_id }})"
                            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openTenantModal({{ $tenant->tenant_id }});}">
                            <td>
                                <span style="font-weight: 600; color: inherit;">
                                    {{ $tenant->full_name }}
                                </span>
                                @if ($tenant->occupation)
                                    <div class="sub nowrap muted">{{ $tenant->occupation }}</div>
                                @endif
                            </td>
                            <td class="nowrap">
                                @if ($active)
                                    <span class="tag t-green" style="font-weight: 600;">
                                        Room {{ $active->room->room_no }} ({{ $active->room->room_type }})
                                    </span>
                                @else
                                    <span class="muted">None / Past Tenant</span>
                                @endif
                            </td>
                            <td class="nowrap">{{ $tenant->contact_no }}</td>
                            <td>
                                <b>{{ $tenant->valid_id_type }}</b>
                                @if ($tenant->other_id_type)
                                    <span class="sub muted">({{ $tenant->other_id_type }})</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <code style="font-family: inherit; font-size: 13px; color: #475569;" title="Masked for privacy">{{ $tenant->masked_id_number }}</code>
                            </td>
                            <td>
                                <span class="tag {{ $tenant->status === 'Active' ? 't-green' : ($tenant->status === 'Blacklisted' ? 't-red' : 't-amber') }}" style="font-size: 11px;">
                                    {{ $tenant->status }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>

            {{-- Tablet Portrait & Mobile: Touch Friendly Card List --}}
            <div class="tenant-cards">
                @foreach ($tenants as $tenant)
                    @php($active = $tenant->activeLease)
                    <div class="tenant-card tenant-card-clickable"
                         tabindex="0"
                         role="button"
                         aria-label="View details and actions for {{ $tenant->full_name }}"
                         onclick="openTenantModal({{ $tenant->tenant_id }})"
                         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openTenantModal({{ $tenant->tenant_id }});}">
                        <div class="tenant-card-head">
                            <span class="tenant-card-title">{{ $tenant->full_name }}</span>
                            <span class="tag {{ $tenant->status === 'Active' ? 't-green' : ($tenant->status === 'Blacklisted' ? 't-red' : 't-amber') }}" style="font-size: 11px;">
                                {{ $tenant->status }}
                            </span>
                        </div>
                        <div class="tenant-card-specs">
                            #TNT-{{ str_pad($tenant->tenant_id, 4, '0', STR_PAD_LEFT) }} &middot; {{ $tenant->occupation ?: 'Tenant' }}
                        </div>
                        <div class="tenant-card-body">
                            <div>
                                <div class="sub muted">Contact &amp; ID</div>
                                <div style="font-weight: 600; color: #0f172a;">{{ $tenant->contact_no }}</div>
                                <div class="sub muted">{{ $tenant->valid_id_type }} &middot; <code>{{ $tenant->masked_id_number }}</code></div>
                            </div>
                            <div style="text-align: right;">
                                <div class="sub muted">Room Assignment</div>
                                @if ($active)
                                    <span class="tag t-green" style="font-weight: 600; display: inline-block; margin-top: 2px;">
                                        Room {{ $active->room->room_no }}
                                    </span>
                                @else
                                    <span class="muted" style="font-size: 12.5px;">No Active Lease</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Tenant Details & Actions Popups for each record --}}
            @foreach ($tenants as $tenant)
                @php($active = $tenant->activeLease)
                <div id="tenant-detail-modal-{{ $tenant->tenant_id }}" class="modal-backdrop tenant-detail-modal" hidden>
                    <div class="modal-box" style="max-width: 540px;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px;">
                            <div>
                                <h2 style="margin: 0 0 4px 0; font-size: 19px; color: var(--navy);">
                                    {{ $tenant->full_name }}
                                </h2>
                                <div class="sub" style="font-size: 12.5px;">
                                    Master Profile #TNT-{{ str_pad($tenant->tenant_id, 4, '0', STR_PAD_LEFT) }} &middot; Registered {{ $tenant->created_at?->format('j M Y') }}
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="tag {{ $tenant->status === 'Active' ? 't-green' : ($tenant->status === 'Blacklisted' ? 't-red' : 't-amber') }}" style="font-size: 11px;">
                                    {{ $tenant->status }}
                                </span>
                                <button type="button" class="modal-close-btn" onclick="closeTenantModal({{ $tenant->tenant_id }})" aria-label="Close">&times;</button>
                            </div>
                        </div>

                        {{-- Tenant Facts Grid --}}
                        <div class="facts" style="margin-bottom: 16px;">
                            <div>
                                <dt>Contact Number</dt>
                                <dd><b style="font-size: 15px;">{{ $tenant->contact_no }}</b></dd>
                            </div>
                            <div>
                                <dt>Current Tenancy</dt>
                                <dd>
                                    @if ($active)
                                        <span class="tag t-green" style="font-weight: 600;">
                                            Room {{ $active->room->room_no }} ({{ $active->room->room_type }})
                                        </span>
                                        <div class="sub muted" style="margin-top: 2px;">Since {{ $active->move_in_date->format('j M Y') }}</div>
                                    @else
                                        <span class="muted">No Active Lease</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Government Identification</dt>
                                <dd>
                                    <b>{{ $tenant->valid_id_type }}</b>
                                    @if ($tenant->other_id_type)
                                        <span class="sub muted">({{ $tenant->other_id_type }})</span>
                                    @endif
                                    <div class="sub" style="margin-top: 2px;">
                                        ID No: <code style="font-family: inherit; font-size: 13px;" title="Masked for privacy">{{ $tenant->masked_id_number }}</code>
                                    </div>
                                </dd>
                            </div>
                            <div>
                                <dt>Provincial Address</dt>
                                <dd>{{ $tenant->provincial_address ?: 'None provided' }}</dd>
                            </div>
                            <div>
                                <dt>Occupation</dt>
                                <dd>{{ $tenant->occupation ?: 'None provided' }}</dd>
                            </div>
                            <div>
                                <dt>Lifetime MOAs</dt>
                                <dd><b>{{ $tenant->leases->count() }} total</b> ({{ $tenant->leases->where('status', 'Active')->count() }} active, {{ $tenant->leases->where('status', 'Ended')->count() }} settled)</dd>
                            </div>
                        </div>

                        {{-- Modal Action Bar --}}
                        <div class="tenant-modal-actions">
                            <a href="{{ route('tenants.edit', $tenant) }}" class="btn sec" title="Edit personal details">Edit Profile</a>
                            <a href="{{ route('tenants.show', $tenant) }}" class="btn sec" title="View Profile & Tenancy History">Profile &amp; History</a>
                            @if (! $active && $tenant->status !== 'Blacklisted')
                                <a href="{{ route('leases.create', ['tenant_id' => $tenant->tenant_id]) }}" class="btn">+ Create Lease</a>
                            @elseif ($active)
                                <a href="{{ route('leases.show', $active) }}" class="btn sec">View Active MOA</a>
                            @endif
                            <button type="button" class="btn sec" onclick="closeTenantModal({{ $tenant->tenant_id }})">Close</button>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <script>
        function openTenantModal(id) {
            const modal = document.getElementById('tenant-detail-modal-' + id);
            if (modal) modal.removeAttribute('hidden');
        }
        function closeTenantModal(id) {
            const modal = document.getElementById('tenant-detail-modal-' + id);
            if (modal) modal.setAttribute('hidden', '');
        }
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-backdrop')) {
                e.target.setAttribute('hidden', '');
            }
        });
    </script>
@endsection
