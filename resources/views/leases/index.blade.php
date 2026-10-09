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
                        <th class="tenant-actions-header">Actions</th>
                    </tr>
                    @foreach ($leases as $lease)
                        <tr>
                            <td class="nowrap"><b>MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }}</b></td>
                            <td>
                                <b>Room {{ $lease->room->room_no }}</b>
                                <div class="sub nowrap muted">{{ $lease->room->room_type }}</div>
                            </td>
                            <td>
                                <a href="{{ route('tenants.show', $lease->tenant) }}" style="font-weight: 600; text-decoration: none; color: inherit;">
                                    {{ $lease->tenant->full_name }}
                                </a>
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
                            <td class="tenant-actions-cell nowrap">
                                <div class="tenant-action-btns">
                                    <a href="{{ route('leases.show', $lease) }}" class="btn sec">View MOA</a>
                                    <a href="{{ route('leases.household', $lease) }}" class="btn sec" title="Manage occupants, appliances, and visitors">Household</a>
                                    @if ($lease->status === 'Active')
                                        @can('manage-leases')
                                            <button type="button" class="btn danger" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center; padding: 0 12px; font-weight: 600;" onclick="openEndLeaseModal({{ $lease->lease_id }}, '{{ addslashes($lease->tenant->full_name) }}', '{{ addslashes($lease->room->room_no) }}', '{{ addslashes($lease->room->room_type) }}')">End Lease</button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>

            {{-- Mobile Cards View --}}
            <div class="lease-cards">
                @foreach ($leases as $lease)
                    <div class="lease-card">
                        <div class="lease-card-head">
                            <span class="lease-card-title">Room {{ $lease->room->room_no }}</span>
                            <x-lease-status :lease="$lease" />
                        </div>
                        <div class="lease-card-specs">
                            MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }} · {{ $lease->room->room_type }} · {{ $lease->active_occupants_count }} / {{ min($lease->room->max_occupants, \App\Models\Room::MAX_OCCUPANTS) }} {{ Str::plural('occupant', $lease->active_occupants_count) }}
                        </div>
                        <div class="lease-card-body">
                            <div>
                                <a href="{{ route('tenants.show', $lease->tenant) }}" style="font-weight: 600; text-decoration: none; color: inherit;">
                                    {{ $lease->tenant->full_name }}
                                </a>
                                <div class="sub muted">{{ $lease->tenant->contact_no }}</div>
                            </div>
                            <div style="text-align: right;">
                                <span class="font-bold">@peso($lease->monthly_rent)</span> <span class="sub muted">/ mo</span>
                                <div class="sub muted">Due every {{ $lease->dueDayLabel() }}</div>
                            </div>
                        </div>
                        <div class="lease-card-actions">
                            <a href="{{ route('leases.show', $lease) }}" class="btn sec">View MOA</a>
                            <a href="{{ route('leases.household', $lease) }}" class="btn sec">Household</a>
                            @if ($lease->status === 'Active')
                                @can('manage-leases')
                                    <button type="button" class="btn danger" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center; font-weight: 600;" onclick="openEndLeaseModal({{ $lease->lease_id }}, '{{ addslashes($lease->tenant->full_name) }}', '{{ addslashes($lease->room->room_no) }}', '{{ addslashes($lease->room->room_type) }}')">End Lease</button>
                                @endcan
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
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

        <script>
            function openEndLeaseModal(leaseId, tenantName, roomNo, roomType) {
                const form = document.getElementById('end-lease-form');
                form.action = '{{ url('/leases') }}/' + leaseId + '/end';
                document.getElementById('elm_tenant_name').textContent = tenantName;
                document.getElementById('elm_room_info').textContent = 'Room ' + roomNo + ' (' + roomType + ')';
                document.getElementById('elm_room_no_note').textContent = roomNo;
                document.getElementById('end-lease-modal').removeAttribute('hidden');
            }
            function closeEndLeaseModal() {
                document.getElementById('end-lease-modal').setAttribute('hidden', '');
            }
            document.addEventListener('click', function(e) {
                const modal = document.getElementById('end-lease-modal');
                if (modal && e.target === modal) {
                    closeEndLeaseModal();
                }
            });
        </script>
    @endcan
@endsection
