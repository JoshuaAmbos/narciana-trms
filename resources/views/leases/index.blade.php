@extends('layouts.app')

@section('title', 'Leases (MOA)')

@section('content')
    {{-- Metric Summary Cards --}}
    <div class="cards">
        <div class="card">
            <div class="n green">{{ $counts['Active'] }}</div>
            <div class="l">Active Leases (Occupied)</div>
        </div>
        <div class="card">
            <div class="n blue">{{ $vacantRoomsCount }}</div>
            <div class="l">Vacant Rooms Available</div>
        </div>
        <div class="card">
            <div class="n amber">{{ $counts['Ended'] }}</div>
            <div class="l">Ended / Settled Contracts</div>
        </div>
        @if ($counts['Cancelled'] > 0)
            <div class="card">
                <div class="n muted">{{ $counts['Cancelled'] }}</div>
                <div class="l">Cancelled Agreements</div>
            </div>
        @endif
    </div>

    {{-- Main Leases Management Panel --}}
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
            <div>
                <h2 style="margin: 0;">
                    Leases &amp; MOA Directory
                </h2>
            </div>
            <div class="actions">
                <a href="{{ route('tenants.index') }}" class="btn sec">Tenants Directory &rarr;</a>
                @can('manage-leases')
                    <a href="{{ route('leases.create') }}" class="btn">+ Encode New MOA</a>
                @endcan
            </div>
        </div>

        {{-- Search & Filters Bar --}}
        <form method="GET" action="{{ route('leases.index') }}" class="tenant-filter-bar">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="room_type" value="{{ $roomType }}">

            <div class="tenant-filter-row">
                <div class="tenant-filter-groups">
                    <div class="tenant-filter-group">
                        <span class="tenant-filter-label">Status:</span>
                        <div class="tenant-chips-wrap">
                            @foreach (['All' => 'All', 'Active' => 'Active', 'Ended' => 'Ended', 'Cancelled' => 'Cancelled'] as $st => $label)
                                <a href="{{ route('leases.index', ['status' => $st, 'search' => $search, 'room_type' => $roomType]) }}"
                                   class="chip {{ $status === $st ? 'on' : '' }}">{{ $label }} ({{ $counts[$st] ?? 0 }})</a>
                            @endforeach
                        </div>
                    </div>

                    <div class="tenant-filter-group">
                        <span class="tenant-filter-label">Room Type:</span>
                        <div class="tenant-chips-wrap">
                            @foreach (['All', 'Basic', 'Aircon'] as $rt)
                                <a href="{{ route('leases.index', ['status' => $status, 'search' => $search, 'room_type' => $rt]) }}"
                                   class="chip {{ $roomType === $rt ? 'on' : '' }}">{{ $rt }}</a>
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
                        <input type="text" name="search" class="search-input" placeholder="Search room no or tenant name..." value="{{ $search }}" autocomplete="off">
                    </div>
                    <button type="submit" class="btn sec tenant-search-btn">Search</button>
                    @if ($search !== '' || $roomType !== 'All' || $status !== 'Active')
                        <a href="{{ route('leases.index') }}" class="btn sec tenant-clear-btn" title="Clear all filters">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($leases->isEmpty())
            <p class="muted">No {{ strtolower($status) }} lease agreements found.
                @if ($status === 'Active')
                    <a href="{{ route('leases.create') }}">+ Encode New MOA &rarr;</a>
                @endif
            </p>
        @else
            <div class="table-wrap leases-table-wrap">
                <table>
                    <tr>
                        <th>Contract Ref</th>
                        <th>Room</th>
                        <th>Tenant</th>
                        <th>Move-in Date</th>
                        <th>Due Day</th>
                        <th class="r">Monthly Rent</th>
                        <th>Occupants</th>
                        <th>Status</th>
                    </tr>
                    @foreach ($leases as $lease)
                        <tr class="lease-row"
                            tabindex="0"
                            role="button"
                            aria-label="View details and actions for MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }}"
                            onclick="openLeaseModal({{ $lease->lease_id }})"
                            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openLeaseModal({{ $lease->lease_id }});}">
                            <td class="nowrap"><b>MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }}</b></td>
                            <td>
                                <b>Room {{ $lease->room->room_no }}</b>
                                <div class="sub nowrap muted">{{ $lease->room->room_type }}</div>
                            </td>
                            <td>
                                @php($coTenant = $lease->coTenant())
                                <div>
                                    <a href="{{ route('tenants.show', $lease->tenant) }}" style="font-weight: 600; text-decoration: none; color: inherit;" onclick="event.stopPropagation()">
                                        {{ $lease->tenant->full_name }}
                                    </a>
                                    @if ($coTenant)
                                        <span class="muted font-normal"> &amp; </span><b>{{ $coTenant->full_name }}</b>
                                    @endif
                                </div>
                                <div class="sub nowrap muted">{{ $lease->tenant->contact_no }}</div>
                            </td>
                            <td class="nowrap">{{ $lease->move_in_date->format('j M Y') }}</td>
                            <td>
                                <span class="tag t-amber" style="font-size: 11px;">Every {{ $lease->dueDayLabel() }}</span>
                            </td>
                            <td class="money">@peso($lease->monthly_rent)</td>
                            <td>
                                <b>{{ $lease->active_occupants_count }}</b> <span class="muted">/ {{ min($lease->room->max_occupants, \App\Models\Room::MAX_OCCUPANTS) }}</span>
                            </td>
                            <td class="nowrap">
                                <x-lease-status :lease="$lease" />
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>

            {{-- Mobile Cards View --}}
            <div class="lease-cards">
                @foreach ($leases as $lease)
                    <div class="lease-card lease-card-clickable"
                         tabindex="0"
                         role="button"
                         aria-label="View details and actions for MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }}"
                         onclick="openLeaseModal({{ $lease->lease_id }})"
                         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openLeaseModal({{ $lease->lease_id }});}">
                        <div class="lease-card-head">
                            <span class="lease-card-title">Room {{ $lease->room->room_no }}</span>
                            <x-lease-status :lease="$lease" />
                        </div>
                        <div class="lease-card-specs">
                            MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }} · {{ $lease->room->room_type }} · {{ $lease->active_occupants_count }} / {{ min($lease->room->max_occupants, \App\Models\Room::MAX_OCCUPANTS) }} {{ Str::plural('occupant', $lease->active_occupants_count) }}
                        </div>
                        <div class="lease-card-body">
                            <div>
                                @php($coTenant = $lease->coTenant())
                                <a href="{{ route('tenants.show', $lease->tenant) }}" class="font-bold text-slate-900" style="text-decoration: none; color: inherit;" onclick="event.stopPropagation()">
                                    {{ $lease->tenant->full_name }}
                                </a>
                                @if ($coTenant)
                                    <div class="sub text-slate-700 font-medium" style="margin-top: 2px;">
                                        Co-tenant: <b>{{ $coTenant->full_name }}</b>
                                    </div>
                                @endif
                                <div class="sub muted">{{ $lease->tenant->contact_no }}</div>
                            </div>
                            <div style="text-align: right;">
                                <span class="font-bold">@peso($lease->monthly_rent)</span> <span class="sub muted">/ mo</span>
                                <div class="sub muted">Due every {{ $lease->dueDayLabel() }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($leases->hasPages())
                <div class="leases-pagination">
                    @include('partials.pagination', ['paginator' => $leases])
                </div>
            @endif

            {{-- Lease Details & Actions Popups for each record --}}
            @foreach ($leases as $lease)
                <div id="lease-detail-modal-{{ $lease->lease_id }}" class="modal-backdrop lease-detail-modal" hidden>
                    <div class="modal-box" style="max-width: 540px;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px;">
                            <div>
                                <h2 style="margin: 0 0 4px 0; font-size: 19px; color: var(--navy);">
                                    MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }} &middot; Room {{ $lease->room->room_no }}
                                </h2>
                                <div class="sub" style="font-size: 12.5px;">
                                    {{ $lease->room->room_type }} &middot; {{ $lease->move_in_date->format('j M Y') }}
                                    @if ($lease->actual_end_date)
                                        to {{ $lease->actual_end_date->format('j M Y') }}
                                    @else
                                        (Active)
                                    @endif
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <x-lease-status :lease="$lease" />
                                <button type="button" class="modal-close-btn" onclick="closeLeaseModal({{ $lease->lease_id }})" aria-label="Close">&times;</button>
                            </div>
                        </div>

                        {{-- Lease Facts Grid --}}
                        <div class="facts" style="margin-bottom: 16px;">
                            <div>
                                <dt>Principal Tenant</dt>
                                <dd>
                                    <a href="{{ route('tenants.show', $lease->tenant) }}" style="font-weight: 600; color: inherit;">
                                        {{ $lease->tenant->full_name }}
                                    </a>
                                    <div class="sub muted">{{ $lease->tenant->contact_no }}</div>
                                </dd>
                            </div>
                            @php($coTenant = $lease->coTenant())
                            <div>
                                <dt>Co-Tenant</dt>
                                <dd>
                                    @if ($coTenant)
                                        <b>{{ $coTenant->full_name }}</b>
                                        <div class="sub muted">{{ $coTenant->contact_no ?: 'No phone logged' }}</div>
                                    @else
                                        <span class="muted">None (Solo occupant)</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Monthly Rent</dt>
                                <dd><b class="money">@peso($lease->monthly_rent)</b> / month</dd>
                            </div>
                            <div>
                                <dt>Contractual Due Day</dt>
                                <dd>Every <b>{{ $lease->dueDayLabel() }}</b></dd>
                            </div>
                            <div>
                                <dt>Active Occupants</dt>
                                <dd><b>{{ $lease->active_occupants_count }}</b> / {{ min($lease->room->max_occupants, \App\Models\Room::MAX_OCCUPANTS) }} occupants</dd>
                            </div>
                            <div>
                                <dt>Contractual Minimum Stay</dt>
                                <dd><b>{{ $lease->min_stay_months }} Months</b></dd>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <dt>Security Deposit Breakdown</dt>
                                <dd style="font-size: 13px;">
                                    Maintenance: @peso($lease->deposit_maintenance) &middot;
                                    Damage: @peso($lease->deposit_damage) &middot;
                                    Bills: @peso($lease->deposit_bills) &middot;
                                    Keys: @peso($lease->deposit_keys)
                                    <div style="margin-top: 4px; font-weight: 700; color: var(--navy);">
                                        Total Deposit: @peso($lease->depositTotal())
                                    </div>
                                </dd>
                            </div>
                            @if ($lease->termination_notes)
                                <div style="grid-column: 1 / -1;">
                                    <dt>Termination Notes</dt>
                                    <dd style="white-space: pre-wrap; color: var(--text);">{{ $lease->termination_notes }}</dd>
                                </div>
                            @endif
                        </div>

                        {{-- Modal Action Bar --}}
                        <div class="lease-modal-actions">
                            <a href="{{ route('leases.show', $lease) }}" class="btn sec" title="View MOA details">View MOA</a>
                            <a href="{{ route('leases.household', $lease) }}" class="btn sec" title="Manage occupants, appliances, and visitors">Household</a>
                            @if ($lease->status === 'Active')
                                @can('manage-leases')
                                    <button type="button" class="btn danger"
                                            style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center; padding: 0 14px; font-weight: 600;"
                                            onclick="closeLeaseModal({{ $lease->lease_id }}); openEndLeaseModal({{ $lease->lease_id }}, '{{ addslashes($lease->tenant->full_name) }}', '{{ addslashes($lease->room->room_no) }}', '{{ addslashes($lease->room->room_type) }}')">
                                        End Lease
                                    </button>
                                @endcan
                            @endif
                            <button type="button" class="btn sec" onclick="closeLeaseModal({{ $lease->lease_id }})">Close</button>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    @can('manage-leases')
        <div id="end-lease-modal" class="modal-backdrop" hidden>
            <div class="modal-box" style="max-width: 480px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="margin: 0;">End Lease &amp; Vacate Room</h3>
                    <button type="button" class="modal-close-btn" onclick="closeEndLeaseModal()">&times;</button>
                </div>

                <form method="POST" id="end-lease-form">
                    @csrf
                    @method('PATCH')

                    <div class="facts" style="margin-bottom: 14px;">
                        <div><dt>Tenant</dt><dd><b id="elm_tenant_name">-</b></dd></div>
                        <div><dt>Room</dt><dd><b id="elm_room_info">-</b></dd></div>
                    </div>

                    <div class="f" style="margin-bottom: 14px;">
                        <label for="elm_actual_date">Actual Move-out Date</label>
                        <input id="elm_actual_date" type="date" name="actual_end_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}">
                    </div>

                    <div class="f" style="margin-bottom: 14px;">
                        <label for="elm_notes">Termination Notes (optional)</label>
                        <textarea id="elm_notes" name="termination_notes" rows="2" placeholder="e.g. Keys returned, room vacated without incident."></textarea>
                    </div>

                    <div class="alert alert-warn" style="margin-bottom: 16px; font-size: 13px;">
                        Ending this lease will immediately mark Room <span id="elm_room_no_note"></span> as Vacant. All physical room inspections and deposit returns are handled offline per MOA terms.
                    </div>

                    <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="btn sec" style="min-height: 44px; padding: 0 16px;" onclick="closeEndLeaseModal()">Cancel</button>
                        <button type="submit" class="btn danger" style="min-height: 44px; padding: 0 16px;">Confirm &amp; Vacate Room</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <script>
        function openLeaseModal(id) {
            const modal = document.getElementById('lease-detail-modal-' + id);
            if (modal) modal.removeAttribute('hidden');
        }
        function closeLeaseModal(id) {
            const modal = document.getElementById('lease-detail-modal-' + id);
            if (modal) modal.setAttribute('hidden', '');
        }
        function openEndLeaseModal(leaseId, tenantName, roomNo, roomType) {
            const form = document.getElementById('end-lease-form');
            if (form) {
                form.action = '{{ url('/leases') }}/' + leaseId + '/end';
                document.getElementById('elm_tenant_name').textContent = tenantName;
                document.getElementById('elm_room_info').textContent = 'Room ' + roomNo + ' (' + roomType + ')';
                document.getElementById('elm_room_no_note').textContent = roomNo;
            }
            const modal = document.getElementById('end-lease-modal');
            if (modal) modal.removeAttribute('hidden');
        }
        function closeEndLeaseModal() {
            const modal = document.getElementById('end-lease-modal');
            if (modal) modal.setAttribute('hidden', '');
        }
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-backdrop')) {
                e.target.setAttribute('hidden', '');
            }
        });
    </script>
@endsection
