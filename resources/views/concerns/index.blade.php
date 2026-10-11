@extends('layouts.app')

@section('title', $room ? 'History of Room '.$room->room_no : 'Tenant Concerns')

@section('content')
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
            <h2 style="margin: 0;">
                {{ $room ? 'History of Room '.$room->room_no : 'Tenant Concerns' }}
            </h2>
            <div class="actions" style="display: flex; gap: 8px; align-items: center;">
                @if ($room)
                    <a href="{{ route('concerns.index') }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center;">All Rooms &rarr;</a>
                @endif
                <button type="button" class="btn top-add-btn" onclick="openCreateConcernModal()" style="min-height: 44px; display: inline-flex; align-items: center; gap: 6px;">
                    + New Concern   
                </button>
            </div>
        </div>

        @if ($repeated->isNotEmpty())
            <div class="alert alert-warn" style="display: flex; align-items: center; gap: 10px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-icon" style="flex-shrink: 0;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>
                    <b>Repeated issue:</b>
                    @foreach ($repeated as $category => $times)
                        {{ $category }} reported {{ $times }} times{{ $loop->last ? '' : ';' }}
                    @endforeach
                    — consider a lasting fix.
                </div>
            </div>
        @endif

        {{-- Filter & Universal Search Bar --}}
        <form method="GET" action="{{ route('concerns.index') }}" class="concern-filter-bar no-print">
            @if ($room)
                <input type="hidden" name="room" value="{{ $room->room_id }}">
            @endif
            @if ($status && $status !== ($room ? 'all' : 'open'))
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            @if ($mine)
                <input type="hidden" name="mine" value="1">
            @endif

            <div class="concern-controls-wrap">
                {{-- Search Input (Option B: Top on Portrait; Option A: Left on Landscape) --}}
                <div class="concern-search-box">
                    <div class="concern-search-input-wrap">
                        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" 
                            id="concernSearchInput" 
                            name="search" 
                            class="search-input concern-search-input"
                            placeholder="Search room, tenant, category, or keyword..."
                            value="{{ $search }}" 
                            autocomplete="off" 
                            oninput="filterConcernsRealtime(this.value)">

                        {{-- Instant Real-Time Clear Button --}}
                        <button type="button" 
                                id="concern-search-clear" 
                                class="concern-search-clear-btn"
                                title="Clear search"
                                onclick="document.getElementById('concernSearchInput').value=''; filterConcernsRealtime(''); this.style.display='none';"
                                style="display: {{ $search ? 'inline-flex' : 'none' }};">
                            &times;
                        </button>
                    </div>
                </div>

                {{-- Status Filter Chips (Option B: Bottom on Portrait; Option A: Right on Landscape) --}}
                <div class="concern-chips-box">
                    <span class="status-label">Status:</span>
                    <div class="concern-chips-wrap">
                        @foreach (['open' => 'Not resolved', 'Open' => 'Open', 'In progress' => 'In progress', 'Resolved' => 'Resolved', 'all' => 'All'] as $key => $label)
                            <a href="{{ route('concerns.index', array_filter(['status' => $key, 'room' => $room?->room_id, 'search' => $search ?: null])) }}"
                            class="chip concern-chip {{ $status === $key && !$mine ? 'on' : '' }}">
                                {{ $label }} ({{ $counts[$key] ?? 0 }})
                            </a>
                        @endforeach
                        <a href="{{ route('concerns.index', array_filter(['status' => $status, 'room' => $room?->room_id, 'mine' => $mine ? null : 1, 'search' => $search ?: null])) }}"
                        class="chip concern-chip {{ $mine ? 'on' : '' }}">
                            Assigned to me ({{ $counts['mine'] ?? 0 }})
                        </a>
                    </div>
                </div>
            </div>
        </form>

        @if ($concerns->isEmpty())
            <p class="muted">No concerns found {{ $search ? "matching '{$search}'" : 'here' }}.</p>
        @else
            {{-- Landscape Mode (~1024px – 1280px) & Desktop Table --}}
            <div class="table-wrap concerns-table-wrap">
                <table class="concerns-table">
                    <thead>
                        <tr>
                            <th>Reported</th>
                            <th>Where</th>
                            <th>Category</th>
                            <th>Concern</th>
                            <th>Assigned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="concernsTableBody">
                        @foreach ($concerns as $c)
                            <tr class="concern-row"
                                data-concern-id="{{ $c->concern_id }}"
                                data-search="{{ strtolower($c->location() . ' ' . ($c->lease?->tenant?->full_name ?? '') . ' ' . $c->category . ' ' . $c->description . ' ' . ($c->resolution ?? '')) }}"
                                onclick="openDetailModal({{ $c->concern_id }})"
                                onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openDetailModal({{ $c->concern_id }}); }"
                                tabindex="0"
                                role="button"
                                aria-label="View concern details for {{ $c->location() }}">
                                <td class="nowrap" style="font-size: 13px;">{{ $c->reported_at->format('j M, g:i A') }}</td>
                                <td>
                                    @if ($c->room)
                                        <b>{{ $c->room->room_no }}</b>
                                    @else
                                        <b>Shared area</b>
                                    @endif
                                    @if ($c->lease)
                                        <span class="sub">{{ $c->lease->tenant->full_name }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="tag t-sub" style="font-size: 11.5px; font-weight: 600;">{{ $c->category }}</span>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: var(--navy); line-height: 1.35;">{{ Str::limit($c->description, 70) }}</div>
                                    <div class="concern-card-sub-badges" style="margin-top: 4px;">
                                        @if ($c->photo_path)
                                            <span class="tag t-grey" style="font-size: 11px; padding: 1px 6px;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-icon" style="vertical-align: -1px;"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                                                Photo
                                            </span>
                                        @endif
                                        @if ($c->charged_to_tenant)
                                            <span class="tag t-amber" style="font-size: 11px; padding: 1px 6px;">
                                                Charge: ₱{{ number_format((float) $c->cost, 2) }}{{ $c->bill_id ? ' · Billed' : '' }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="nowrap" style="font-size: 13px;">
                                    <b>{{ $c->assignedTo->role }}</b>
                                    <div class="sub">{{ $c->assignedTo->full_name }}</div>
                                </td>
                                <td class="nowrap">
                                    <x-concern-status :concern="$c" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Portrait Mode (~712px – 800px) & Mobile Stacked Cards --}}
            <div class="concern-cards" id="concernCardsContainer">
                @foreach ($concerns as $c)
                    <div class="concern-card"
                         data-concern-id="{{ $c->concern_id }}"
                         data-search="{{ strtolower($c->location() . ' ' . ($c->lease?->tenant?->full_name ?? '') . ' ' . $c->category . ' ' . $c->description . ' ' . ($c->resolution ?? '')) }}"
                         onclick="openDetailModal({{ $c->concern_id }})"
                         onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openDetailModal({{ $c->concern_id }}); }"
                         tabindex="0"
                         role="button"
                         aria-label="View concern details for {{ $c->location() }}">
                        <div class="concern-card-head">
                            <div>
                                <div class="concern-card-location">
                                    @if ($c->room)
                                        Room {{ $c->room->room_no }}
                                    @else
                                        Shared Area
                                    @endif
                                </div>
                                @if ($c->lease)
                                    <div class="concern-card-tenant">{{ $c->lease->tenant->full_name }}</div>
                                @endif
                            </div>
                            <div class="concern-card-badges">
                                <x-concern-status :concern="$c" />
                                <div class="concern-card-chevron">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                </div>
                            </div>
                        </div>

                        <div class="concern-card-meta">
                            <span class="tag t-sub" style="font-size: 11.5px; font-weight: 600;">{{ $c->category }}</span>
                        </div>

                        <div class="concern-card-desc">
                            {{ $c->description }}
                        </div>

                        <div class="concern-card-sub-badges">
                            @if ($c->photo_path)
                                <span class="tag t-grey" style="font-size: 11px; padding: 2px 7px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-icon" style="vertical-align: -1px;"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                                    Photo attached
                                </span>
                            @endif
                            @if ($c->charged_to_tenant)
                                <span class="tag t-amber" style="font-size: 11px; padding: 2px 7px;">
                                    Charge to Tenant: ₱{{ number_format((float) $c->cost, 2) }}{{ $c->bill_id ? ' · Billed' : '' }}
                                </span>
                            @endif
                        </div>

                        <div class="concern-card-foot">
                            <span>{{ $c->reported_at->format('j M, g:i A') }} ({{ $c->channel }})</span>
                            <span>Assigned: <b>{{ $c->assignedTo->role }}</b></span>
                        </div>
                    </div>
                @endforeach
            </div>

            @include('partials.pagination', ['paginator' => $concerns])
        @endif
    </div>

    {{-- Floating Action Button (FAB) for Mobile / Portrait Views --}}
    <button type="button" class="concern-fab" onclick="openCreateConcernModal()" aria-label="Log New Concern" title="Log New Concern">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="12" y2="19"></line>
        </svg>
    </button>

    {{-- ========================================================================= --}}
    {{-- MODAL 1: Log New Concern (#createConcernModal) --}}
    {{-- ========================================================================= --}}
    <div id="createConcernModal" class="modal-backdrop" hidden>
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="header-icon"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/></svg>
                    Log New Concern
                </h2>
                <button type="button" class="modal-close-btn" onclick="closeCreateConcernModal()" aria-label="Close modal">&times;</button>
            </div>

            <form method="POST" action="{{ route('concerns.store') }}" enctype="multipart/form-data" id="createConcernForm">
                @csrf
                <input type="hidden" name="from_index" value="1">

                <div class="row">
                    <div class="f">
                        <label for="create_category">Category</label>
                        <select id="create_category" name="category" onchange="handleCreateCategoryChange(this)">
                            @foreach ($categories as $name => $help)
                                <option value="{{ $name }}" data-help="{{ $help }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <div class="hint category-help" id="create_category_help">{{ reset($categories) }}</div>
                    </div>
                    <div class="f">
                        <label for="create_room_id">Room</label>
                        <select id="create_room_id" name="room_id" onchange="handleCreateRoomChange(this)">
                            <option value="" disabled selected>Choose a room…</option>
                            @foreach ($rooms as $r)
                                <option value="{{ $r['room_id'] }}" data-lease="{{ $r['lease_id'] }}" data-tenant="{{ $r['tenant'] }}">{{ $r['label'] }}{{ $r['tenant'] ? ' — '.$r['tenant'] : ' — vacant' }}</option>
                            @endforeach
                            <option value="shared" data-lease="">No room (shared area)</option>
                        </select>
                    </div>
                    <div class="f">
                        <label for="create_lease_id">Tenant</label>
                        <select id="create_lease_id" name="lease_id">
                            <option value="">None</option>
                            @foreach ($rooms->whereNotNull('lease_id') as $r)
                                <option value="{{ $r['lease_id'] }}">{{ $r['tenant'] }} ({{ $r['label'] }})</option>
                            @endforeach
                        </select>
                        <div class="hint">Filled in from the room. Needed to charge the tenant.</div>
                    </div>
                </div>

                <div class="row">
                    <div class="f">
                        <label for="create_reported_at">Reported on</label>
                        <input id="create_reported_at" name="reported_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="f">
                        <label for="create_channel">Reported by</label>
                        <select id="create_channel" name="channel">
                            @foreach ($channels as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="f">
                        <label for="create_priority">Priority</label>
                        <select id="create_priority" name="priority">
                            @foreach ($priorities as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="f">
                        <label for="create_assigned_to">Assigned to</label>
                        <select id="create_assigned_to" name="assigned_to">
                            @foreach ($users as $u)
                                <option value="{{ $u->user_id }}" @selected($u->user_id === auth()->id())>{{ $u->role }} ({{ $u->full_name }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="f">
                    <label for="create_description">Concern</label>
                    <textarea id="create_description" name="description" rows="3" maxlength="500" placeholder="What is the problem? For a shared area, specify the exact location (e.g. 2nd-floor hallway or CR)."></textarea>
                </div>

                <div class="f">
                    <label for="create_photo">Photo of the problem (optional)</label>
                    <input id="create_photo" name="photo" type="file" accept="image/jpeg,image/png" capture="environment">
                    <div class="hint">Proof of the problem when it was reported. Take it with the tablet camera or pick one. JPG or PNG, up to 5 MB.</div>
                </div>

                <fieldset class="resolution-box" style="margin-top: 14px;">
                    <legend>Progress &amp; Financials</legend>
                    <div class="row">
                        <div class="f">
                            <label for="create_status">Status</label>
                            <select id="create_status" name="status">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if ($canSetMoney)
                        <div class="row">
                            <div class="f">
                                <label for="create_cost">Cost of repair (₱)</label>
                                <input id="create_cost" name="cost" type="number" min="0" step="0.01" inputmode="decimal">
                            </div>
                            <div class="f check">
                                <label><input type="checkbox" id="create_charged_to_tenant" name="charged_to_tenant" value="1"> Charge to Tenant</label>
                            </div>
                        </div>
                    @else
                        <p class="muted">Only the Owner or Manager can set the cost and whether it is charged to the tenant.</p>
                    @endif
                </fieldset>

                <div class="form-actions" style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn sec" onclick="closeCreateConcernModal()" style="min-height: 44px; padding: 0 16px;">Cancel</button>
                    <button type="submit" class="btn" style="min-height: 44px; padding: 0 16px;">Log Concern</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 2: Concern Ticket Details (#detailConcernModal) --}}
    {{-- ========================================================================= --}}
    <div id="detailConcernModal" class="modal-backdrop" hidden>
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="header-icon"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/></svg>
                    Concern Ticket Details
                </h2>
                <button type="button" class="modal-close-btn" onclick="closeDetailModal()" aria-label="Close modal">&times;</button>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                <span id="detail_location_text" style="font-size: 16px; font-weight: 700; color: var(--navy);">-</span>
                <div style="display: flex; gap: 6px; align-items: center;" id="detail_status_tags_container">
                    <span id="detail_status_tag" class="tag"></span>
                    <span id="detail_priority_tag" class="tag t-red" style="display: none;">Urgent</span>
                </div>
            </div>

            <div class="detail-description-box" id="detail_description_text"></div>

            {{-- Progress timeline --}}
            <ol class="timeline" aria-label="Progress" id="detail_timeline_list"></ol>

            <dl class="facts">
                <div><dt>Category</dt><dd id="detail_category_val">-</dd></div>
                <div><dt>Reported</dt><dd id="detail_reported_val">-</dd></div>
                <div><dt>Tenant</dt><dd id="detail_tenant_val">-</dd></div>
                <div><dt>Assigned to</dt><dd id="detail_assigned_val">-</dd></div>
                <div><dt>Priority</dt><dd id="detail_priority_val">-</dd></div>
                <div>
                    <dt>Cost &amp; Charge</dt>
                    <dd id="detail_cost_charge_val">-</dd>
                </div>
            </dl>

            <div id="detail_resolution_box" style="display: none; background: #eef7ee; border: 1px solid #c3e6cb; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; color: #1e4620;">
                <b>How it was resolved:</b>
                <div id="detail_resolution_text" style="margin-top: 4px; white-space: pre-wrap;"></div>
            </div>

            <div id="detail_bill_note" style="display: none; margin-bottom: 14px;"></div>

            {{-- Photos --}}
            <div class="detail-photos-grid">
                <div class="detail-photo-card">
                    <b>When Reported (The Problem)</b>
                    <div id="detail_problem_photo_container">
                        <span class="muted" style="font-size: 13px;">No photo provided.</span>
                    </div>
                </div>
                <div class="detail-photo-card">
                    <b>When Resolved (The Fix)</b>
                    <div id="detail_resolved_photo_container">
                        <span class="muted" style="font-size: 13px;">No photo provided.</span>
                    </div>
                </div>
            </div>

            <div class="modal-actions-bar" style="margin-top: 18px; border-top: 1px solid var(--border); padding-top: 14px; display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn" id="detail_update_button" onclick="openUpdateModalFromDetail()" style="min-height: 44px; padding: 0 16px;">Update Concern</button>
                <button type="button" class="btn sec" onclick="closeDetailModal()" style="min-height: 44px; padding: 0 16px;">Close</button>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 3: Update Concern (#updateConcernModal) --}}
    {{-- ========================================================================= --}}
    <div id="updateConcernModal" class="modal-backdrop" hidden>
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="header-icon"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/></svg>
                    <span id="update_modal_title">Update Concern</span>
                </h2>
                <button type="button" class="modal-close-btn" onclick="closeUpdateModal()" aria-label="Close modal">&times;</button>
            </div>

            <form method="POST" id="updateConcernForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="from_index" value="1">
                <input type="hidden" name="concern_id" id="update_concern_id" value="{{ old('concern_id') }}">
                <input type="hidden" name="room_id" id="update_room_id">
                <input type="hidden" name="lease_id" id="update_lease_id">
                <input type="hidden" name="reported_at" id="update_reported_at">
                <input type="hidden" name="channel" id="update_channel">
                <input type="hidden" name="category" id="update_category">
                <input type="hidden" name="description" id="update_description">

                <div class="row">
                    <div class="f">
                        <label for="update_status">Status</label>
                        <select id="update_status" name="status">
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="status" id="update_status_hidden" disabled>
                    </div>
                    <div class="f">
                        <label for="update_priority">Priority</label>
                        <select id="update_priority" name="priority">
                            @foreach ($priorities as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="f">
                        <label for="update_assigned_to">Assigned to</label>
                        <select id="update_assigned_to" name="assigned_to">
                            @foreach ($users as $u)
                                <option value="{{ $u->user_id }}">{{ $u->role }} ({{ $u->full_name }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="f">
                    <label for="update_resolved_at">Resolved on</label>
                    <input id="update_resolved_at" name="resolved_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}">
                    <div class="hint">Left blank = now, when the status is Resolved.</div>
                </div>

                <div class="f @error('resolution') has-err @enderror">
                    <label for="update_resolution">How it was resolved</label>
                    <textarea id="update_resolution" name="resolution" rows="3" maxlength="500" placeholder="Describe how the problem was resolved (required if marked Resolved)...">{{ old('resolution') }}</textarea>
                    @error('resolution') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div class="f">
                    <label for="update_resolution_photo">Photo after the fix (optional)</label>
                    <div id="update_current_resolution_photo_box" style="display: none; margin-bottom: 8px;">
                        <img id="update_current_resolution_img" src="" alt="Fix photo" style="max-height: 120px; border-radius: 6px; border: 1px solid var(--border); display: block; margin-bottom: 4px;">
                        <label class="inline-check"><input type="checkbox" name="remove_resolution_photo" value="1"> Remove this photo</label>
                    </div>
                    <input id="update_resolution_photo" name="resolution_photo" type="file" accept="image/jpeg,image/png" capture="environment">
                    <div class="hint">Proof it was fixed. Add it when you mark the concern Resolved. JPG or PNG, up to 5 MB.</div>
                </div>

                <fieldset class="resolution-box" style="margin-top: 14px;">
                    <legend>Cost &amp; Charge</legend>
                    @if ($canSetMoney)
                        <div class="row">
                            <div class="f">
                                <label for="update_cost">Cost of repair (₱)</label>
                                <input id="update_cost" name="cost" type="number" min="0" step="0.01" inputmode="decimal">
                                <input type="hidden" name="cost" id="update_cost_hidden" disabled>
                            </div>
                            <div class="f check">
                                <label><input type="checkbox" id="update_charged_to_tenant" name="charged_to_tenant" value="1"> Charge to Tenant</label>
                                <input type="hidden" name="charged_to_tenant" id="update_charged_to_tenant_hidden" disabled>
                            </div>
                        </div>
                        <div id="update_billed_warning" class="hint" style="display: none; color: var(--navy); font-weight: 600; margin-top: 6px;">
                            Already charged on the bill: status, cost and charge can no longer be changed.
                        </div>
                    @else
                        <p class="muted">Only the Owner or Manager can set the cost and whether it is charged to the tenant.</p>
                    @endif
                </fieldset>

                <div class="form-actions" style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn sec" onclick="cancelUpdateModal()" style="min-height: 44px; padding: 0 16px;">Back</button>
                    <button type="submit" class="btn" style="min-height: 44px; padding: 0 16px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const concernsMap = {!! json_encode($concernsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
        let currentConcernId = null;

        function openDetailModal(id) {
            const data = concernsMap[id];
            if (!data) return;
            currentConcernId = id;

            document.getElementById('detail_location_text').textContent = data.location;
            document.getElementById('detail_description_text').textContent = data.description;
            document.getElementById('detail_category_val').textContent = data.category;
            document.getElementById('detail_reported_val').textContent = data.reported_at + ' (' + data.channel + ')';
            document.getElementById('detail_tenant_val').textContent = data.tenant_name ? data.tenant_name : '–';
            document.getElementById('detail_assigned_val').textContent = data.assigned_to_role + (data.assigned_to_name ? ' (' + data.assigned_to_name + ')' : '');
            document.getElementById('detail_priority_val').textContent = data.priority;

            // Status tag
            const statusTag = document.getElementById('detail_status_tag');
            statusTag.textContent = data.status;
            statusTag.className = 'tag ' + (data.status === 'Open' ? 't-blue' : (data.status === 'In progress' ? 't-amber' : 't-green'));

            // Priority tag
            const priorityTag = document.getElementById('detail_priority_tag');
            if (data.priority === 'Urgent' && data.status !== 'Resolved') {
                priorityTag.style.display = 'inline-block';
            } else {
                priorityTag.style.display = 'none';
            }

            // Cost & Charge
            const costBox = document.getElementById('detail_cost_charge_val');
            if (data.cost !== null) {
                let chargeStr = '₱' + data.cost_formatted;
                if (data.charged_to_tenant) {
                    chargeStr += ' · <span class="tag t-amber">Charge to Tenant</span>';
                } else {
                    chargeStr += ' <span class="sub muted">(Property expense)</span>';
                }
                costBox.innerHTML = chargeStr;
            } else {
                costBox.textContent = '–';
            }

            // Resolution box
            const resBox = document.getElementById('detail_resolution_box');
            if (data.resolution) {
                document.getElementById('detail_resolution_text').textContent = data.resolution;
                resBox.style.display = 'block';
            } else {
                resBox.style.display = 'none';
            }

            // Billed Note
            const billNote = document.getElementById('detail_bill_note');
            if (data.is_billed && data.bill_due_date) {
                billNote.innerHTML = '<p class="paid-note">Charged on the bill due ' + data.bill_due_date + (data.bill_url ? ' — <a href="' + data.bill_url + '">open the bill</a>' : '') + '</p>';
                billNote.style.display = 'block';
            } else if (data.charged_to_tenant && data.status === 'Resolved') {
                billNote.innerHTML = '<p class="muted">Will be offered on this tenant\'s next bill (the Manager picks it when generating).</p>';
                billNote.style.display = 'block';
            } else {
                billNote.style.display = 'none';
            }

            // Timeline
            const timelineList = document.getElementById('detail_timeline_list');
            timelineList.innerHTML = '';
            if (data.timeline && Array.isArray(data.timeline)) {
                data.timeline.forEach(step => {
                    const li = document.createElement('li');
                    li.className = step.done ? 'done' : (step.skipped ? 'skipped' : 'todo');
                    let content = '<b>' + step.label + '</b>';
                    if (step.done) {
                        content += '<span class="sub">' + (step.at || '') + '</span>';
                        content += '<span class="sub">by ' + (step.by || '—') + '</span>';
                    } else if (step.skipped) {
                        content += '<span class="sub">skipped (resolved directly)</span>';
                    } else {
                        content += '<span class="sub">not yet</span>';
                    }
                    li.innerHTML = content;
                    timelineList.appendChild(li);
                });
            }

            // Photos
            const probContainer = document.getElementById('detail_problem_photo_container');
            if (data.photo_url) {
                probContainer.innerHTML = '<a href="' + data.photo_url + '" target="_blank"><img src="' + data.photo_url + '" alt="When reported photo" class="detail-photo-img"></a>';
            } else {
                probContainer.innerHTML = '<span class="muted" style="font-size: 13px;">No photo provided.</span>';
            }

            const resContainer = document.getElementById('detail_resolved_photo_container');
            if (data.resolution_photo_url) {
                resContainer.innerHTML = '<a href="' + data.resolution_photo_url + '" target="_blank"><img src="' + data.resolution_photo_url + '" alt="When resolved photo" class="detail-photo-img"></a>';
            } else {
                resContainer.innerHTML = '<span class="muted" style="font-size: 13px;">No photo provided.</span>';
            }

            document.getElementById('detailConcernModal').removeAttribute('hidden');
        }

        function closeDetailModal() {
            document.getElementById('detailConcernModal').setAttribute('hidden', '');
        }

        function openCreateConcernModal() {
            document.getElementById('createConcernModal').removeAttribute('hidden');
        }

        function closeCreateConcernModal() {
            document.getElementById('createConcernModal').setAttribute('hidden', '');
        }

        function openUpdateModal(id, preserveOld = false) {
            const data = concernsMap[id];
            if (!data) return;
            currentConcernId = id;

            const form = document.getElementById('updateConcernForm');
            form.action = data.update_url;

            const updateConcernIdInput = document.getElementById('update_concern_id');
            if (updateConcernIdInput) updateConcernIdInput.value = id;

            document.getElementById('update_modal_title').textContent = 'Update: ' + data.location;
            document.getElementById('update_room_id').value = data.room_id;
            document.getElementById('update_lease_id').value = data.lease_id || '';
            document.getElementById('update_reported_at').value = data.reported_at_input;
            document.getElementById('update_channel').value = data.channel;
            document.getElementById('update_category').value = data.category;
            document.getElementById('update_description').value = data.description;

            if (!preserveOld) {
                document.getElementById('update_status').value = data.status;
                document.getElementById('update_priority').value = data.priority;
                document.getElementById('update_assigned_to').value = data.assigned_to;
                document.getElementById('update_resolved_at').value = data.resolved_at_input || '';
                document.getElementById('update_resolution').value = data.resolution || '';
            }

            // Fix photo
            const currentFixPhotoBox = document.getElementById('update_current_resolution_photo_box');
            const currentFixImg = document.getElementById('update_current_resolution_img');
            if (data.resolution_photo_url) {
                currentFixImg.src = data.resolution_photo_url;
                currentFixPhotoBox.style.display = 'block';
            } else {
                currentFixPhotoBox.style.display = 'none';
            }

            // Money
            const costInput = document.getElementById('update_cost');
            const chargeCheck = document.getElementById('update_charged_to_tenant');
            const billedWarning = document.getElementById('update_billed_warning');
            const statusSelect = document.getElementById('update_status');
            const statusHidden = document.getElementById('update_status_hidden');
            const costHidden = document.getElementById('update_cost_hidden');
            const chargeHidden = document.getElementById('update_charged_to_tenant_hidden');

            if (costInput && chargeCheck) {
                if (!preserveOld) {
                    costInput.value = data.cost !== null ? data.cost : '';
                    chargeCheck.checked = !!data.charged_to_tenant;
                }

                if (data.is_billed) {
                    costInput.disabled = true;
                    chargeCheck.disabled = true;
                    statusSelect.disabled = true;
                    if (billedWarning) billedWarning.style.display = 'block';

                    if (statusHidden) { statusHidden.value = data.status; statusHidden.disabled = false; }
                    if (costHidden) { costHidden.value = data.cost; costHidden.disabled = false; }
                    if (chargeHidden) { chargeHidden.value = data.charged_to_tenant ? 1 : 0; chargeHidden.disabled = false; }
                } else {
                    costInput.disabled = false;
                    chargeCheck.disabled = false;
                    statusSelect.disabled = false;
                    if (billedWarning) billedWarning.style.display = 'none';

                    if (statusHidden) statusHidden.disabled = true;
                    if (costHidden) costHidden.disabled = true;
                    if (chargeHidden) chargeHidden.disabled = true;
                }
            }

            document.getElementById('updateConcernModal').removeAttribute('hidden');
        }

        function closeUpdateModal() {
            document.getElementById('updateConcernModal').setAttribute('hidden', '');
        }

        function openUpdateModalFromDetail() {
            if (!currentConcernId) return;
            closeDetailModal();
            openUpdateModal(currentConcernId);
        }

        function cancelUpdateModal() {
            closeUpdateModal();
            if (currentConcernId) {
                openDetailModal(currentConcernId);
            }
        }

        function handleCreateRoomChange(select) {
            const lease = select.options[select.selectedIndex].dataset.lease || '';
            const tenantSelect = document.getElementById('create_lease_id');
            if (tenantSelect) {
                tenantSelect.value = lease;
            }
        }

        function handleCreateCategoryChange(select) {
            const help = select.options[select.selectedIndex].dataset.help || '';
            const helpDiv = document.getElementById('create_category_help');
            if (helpDiv) helpDiv.textContent = help;

            const roomSelect = document.getElementById('create_room_id');
            if (select.value === @json($sharedCategory) && roomSelect) {
                roomSelect.value = 'shared';
                handleCreateRoomChange(roomSelect);
            }
        }

        function filterConcernsRealtime(query) {
            const q = (query || '').toLowerCase().trim();
            const rows = document.querySelectorAll('#concernsTableBody .concern-row');
            rows.forEach(row => {
                const text = row.getAttribute('data-search') || '';
                if (!q || text.indexOf(q) !== -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            const cards = document.querySelectorAll('#concernCardsContainer .concern-card');
            cards.forEach(card => {
                const text = card.getAttribute('data-search') || '';
                if (!q || text.indexOf(q) !== -1) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Auto-open modal if redirected with concern ID or validation errors
        document.addEventListener('DOMContentLoaded', function () {
            @if ($errors->any() && old('from_index'))
                @if (old('concern_id'))
                    openUpdateModal({{ (int) old('concern_id') }}, true);
                @else
                    openCreateConcernModal();
                @endif
            @elseif ($selectedConcernId)
                openDetailModal({{ $selectedConcernId }});
            @endif
        });

        // Escape key closes open modals
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDetailModal();
                closeCreateConcernModal();
                closeUpdateModal();
            }
        });
    </script>
@endpush
