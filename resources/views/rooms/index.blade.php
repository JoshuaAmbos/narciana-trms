@extends('layouts.app')

@section('title', 'Rooms')

@section('content')
    <div class="panel w-full max-w-full">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
            <h2 style="margin: 0; text-transform: capitalize;">
                Room availability
            </h2>
            @can('manage-rooms')
                <div>
                    <button type="button" class="btn hidden lg:inline-flex items-center gap-1" onclick="openCreateRoomModal()">+ Add room</button>
                </div>
            @endcan
        </div>

        {{-- Search & Filters Bar --}}
        <form method="GET" action="{{ route('rooms.index') }}" class="room-filter-bar" id="room-filter-form">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="type" value="{{ $type }}">

            {{-- Filter Groups (Status and Type Pills) --}}
            <div class="room-filter-groups">
                {{-- Status Filter --}}
                <div class="room-filter-group">
                    <span class="room-filter-label" style="font-weight: 600;">Status:</span>
                    <div class="room-chips-wrap">
                        @foreach ($statuses as $st)
                            <a href="{{ route('rooms.index', array_filter(['status' => $st, 'type' => $type !== 'All' ? $type : null, 'search' => $search !== '' ? $search : null])) }}"
                               class="chip {{ $status === $st ? 'on' : '' }}">
                                {{ $st === 'Disabled' ? 'Out / Disabled' : $st }}
                                @if (isset($statusCounts[$st]))
                                    <span class="chip-count">({{ $statusCounts[$st] }})</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Room Type Filter --}}
                <div class="room-filter-group">
                    <span class="room-filter-label" style="font-weight: 600;">Type:</span>
                    <div class="room-chips-wrap">
                        @foreach ($types as $tp)
                            <a href="{{ route('rooms.index', array_filter(['type' => $tp, 'status' => $status !== 'All' ? $status : null, 'search' => $search !== '' ? $search : null])) }}"
                               class="chip {{ $type === $tp ? 'on' : '' }}">{{ $tp }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Search Bar (Dedicated row below the filters) --}}
            <div class="room-search-form">
                <div class="room-search-input-wrap">
                    <x-icon name="search" size="16" class="search-icon" />
                    <input type="text"
                           id="room-search-input"
                           name="search"
                           class="room-search-input"
                           placeholder="Search room no..."
                           value="{{ $search }}"
                           autocomplete="off"
                           aria-label="Search room number">
                    @if ($search !== '')
                        <a href="{{ route('rooms.index', array_filter(['status' => $status !== 'All' ? $status : null, 'type' => $type !== 'All' ? $type : null])) }}"
                           class="room-search-clear-btn"
                           id="room-search-clear"
                           title="Clear search"
                           aria-label="Clear room search">
                            &times;
                        </a>
                    @endif
                </div>
                <div class="room-search-actions">
                    <button type="submit" class="btn sec room-search-submit-btn" style="min-height: 44px;">Search</button>
                    @if ($search !== '' || $status !== 'All' || $type !== 'All')
                        <a href="{{ route('rooms.index') }}" class="btn sec room-reset-btn" style="min-height: 44px;" title="Reset all filters">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($rooms->isEmpty())
            @if ($search !== '' || $status !== 'All' || $type !== 'All')
                <div style="padding: 24px 0;">
                    <p class="muted">No rooms found matching your search and filter criteria.</p>
                    <a href="{{ route('rooms.index') }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center;">Clear all filters</a>
                </div>
            @else
                <p class="muted">No rooms yet.@can('manage-rooms') Use "+ Add room" to add the first one.@endcan</p>
            @endif
        @else
            {{-- Desktop / Wide Tablet View (Action buttons moved into popup modal) --}}
            <div class="table-wrap rooms-table-wrap">
                <table class="fit">
                    <thead>
                        <tr>
                            <th>Room Number</th>
                            <th>Type &amp; Setup</th>
                            <th class="r">Monthly Rent</th>
                            <th>Unit Status</th>
                            <th>Waiting Demand</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rooms as $room)
                            <tr class="room-row-clickable"
                                onclick="openRoomModal({{ $room->room_id }})"
                                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openRoomModal({{ $room->room_id }});}"
                                tabindex="0"
                                role="button"
                                aria-label="View details and actions for Room {{ $room->room_no }}">
                                <td>
                                    <b>Room {{ $room->room_no }}</b>
                                    <div class="sub muted">Click to view actions</div>
                                </td>
                                <td>
                                    {{ $room->room_type }}
                                    <div class="sub nowrap">{{ $room->is_metered ? 'Own meter' : 'No meter' }} · max {{ $room->max_occupants }} {{ Str::plural('occupant', $room->max_occupants) }}</div>
                                </td>
                                <td class="money"><b>@peso($room->monthly_rent)</b></td>
                                <td><x-room-status :room="$room" :show-reason="true" /></td>
                                <td>
                                    @if ($room->waiting_demand > 0)
                                        <span class="tag t-blue">{{ $room->waiting_demand }} waiting</span>
                                    @else
                                        <span class="sub text-muted">None</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Portrait Tablet & Mobile Cards View (Action buttons moved into popup modal) --}}
            <div class="room-cards">
                @foreach ($rooms as $room)
                    <div class="room-card room-card-clickable"
                         onclick="openRoomModal({{ $room->room_id }})"
                         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openRoomModal({{ $room->room_id }});}"
                         tabindex="0"
                         role="button"
                         aria-label="View details and actions for Room {{ $room->room_no }}">
                        <div class="room-card-head">
                            <div>
                                <span class="room-card-no">Room {{ $room->room_no }}</span>
                                <span class="sub" style="margin-left: 8px;">{{ $room->room_type }} · {{ $room->is_metered ? 'Metered' : 'Unmetered' }}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <x-room-status :room="$room" />
                                <span class="room-chevron"><x-icon name="chevron-right" size="18" /></span>
                            </div>
                        </div>
                        <div class="room-card-specs">
                            Max {{ $room->max_occupants }} {{ Str::plural('occupant', $room->max_occupants) }}
                            @if (! $room->isRentable() && $room->disabled_reason)
                                · <span class="text-red font-medium">{{ $room->disabled_reason }}</span>
                            @endif
                        </div>
                        <div class="room-card-body">
                            <div>
                                <b>@peso($room->monthly_rent)</b> <span class="muted" style="font-size: 12px;">/ month</span>
                            </div>
                            <div style="text-align: right;">
                                @if ($room->waiting_demand > 0)
                                    <span class="tag t-blue">{{ $room->waiting_demand }} waiting</span>
                                @else
                                    <span class="sub text-muted">No queue</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @include('partials.pagination', ['paginator' => $rooms])

            <p class="muted">Status changes automatically: Occupied when an MOA is encoded, Vacant when a lease is ended.</p>
        @endif
    </div>

    @can('manage-rooms')
        {{-- Floating Action Button (FAB) for Portrait and Mobile viewports --}}
        <button type="button" class="room-fab inquiry-fab fixed bottom-6 right-6 z-40 lg:hidden flex items-center justify-center"
                onclick="openCreateRoomModal()" aria-label="Add Room" title="Add Room">
            +
        </button>
    @endcan

    {{-- ========================================================================= --}}
    {{-- ROOM DETAIL & ACTIONS MODALS (#roomModal{{ $room->room_id }}) --}}
    {{-- ========================================================================= --}}
    @foreach ($rooms as $room)
        <div id="roomModal{{ $room->room_id }}" class="modal-backdrop room-modal-backdrop" hidden>
            <div class="modal-box modal-lg">
                <div class="modal-header">
                    <h2>
                        <x-icon name="door-open" size="22" />
                        <span>Room {{ $room->room_no }} Details</span>
                        <span style="margin-left: 8px;"><x-room-status :room="$room" /></span>
                    </h2>
                    <button type="button" class="modal-close-btn" onclick="closeRoomModal({{ $room->room_id }})" aria-label="Close modal">&times;</button>
                </div>

                <div class="room-modal-content">
                    {{-- Detail Facts --}}
                    <div class="grid2 report-grid" style="margin-bottom: 14px;">
                        <div>
                            <dl class="facts">
                                <div>
                                    <dt>Room Number</dt>
                                    <dd><b>Room {{ $room->room_no }}</b></dd>
                                </div>
                                <div>
                                    <dt>Room Type &amp; Setup</dt>
                                    <dd>{{ $room->room_type }} ({{ $room->is_metered ? 'Own Electric Meter' : 'No Meter / Flat Fee' }})</dd>
                                </div>
                                <div>
                                    <dt>Maximum Capacity</dt>
                                    <dd>{{ $room->max_occupants }} {{ Str::plural('Occupant', $room->max_occupants) }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <dl class="facts">
                                <div>
                                    <dt>Monthly Base Rent</dt>
                                    <dd><b>@peso($room->monthly_rent)</b> <span class="muted" style="font-size: 13px;">/ month</span></dd>
                                </div>
                                <div>
                                    <dt>Unit Status</dt>
                                    <dd>
                                        <x-room-status :room="$room" :show-reason="true" />
                                    </dd>
                                </div>
                                <div>
                                    <dt>Queued Inquiry Demand</dt>
                                    <dd>
                                        @if ($room->waiting_demand > 0)
                                            <span class="tag t-blue">{{ $room->waiting_demand }} waiting in queue</span>
                                        @else
                                            <span class="muted">None currently</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {{-- Action Buttons in Popup Dialog --}}
                    <div class="form-actions" style="margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--border); display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px;">
                        <a href="{{ route('concerns.index', ['room' => $room->room_id]) }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                            <x-icon name="wrench" size="18" /> Concern History
                        </a>

                        @if ($room->status === 'Vacant' && $room->isRentable())
                            @can('manage-leases')
                                <a href="{{ route('leases.create', ['room_id' => $room->room_id]) }}" class="btn" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                    <x-icon name="scroll-text" size="18" /> Encode MOA
                                </a>
                            @endcan
                        @endif

                        @can('manage-rooms')
                            <a href="{{ route('rooms.edit', $room) }}" onclick="closeRoomModal({{ $room->room_id }}); openEditRoomModal({{ $room->room_id }}); return false;" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <x-icon name="edit-3" size="18" /> Edit Room
                            </a>
                            @if ($room->status === 'Vacant' && $room->isRentable())
                                <form method="POST" action="{{ route('rooms.toggle-rentable', $room) }}" style="display:inline;" onsubmit="return confirm('Disable Room {{ $room->room_no }} for renting?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn sec" style="min-height: 44px; color: var(--red); display: inline-flex; align-items: center; justify-content: center; gap: 6px;" title="Disable room for renting (room is out)">
                                        <x-icon name="ban" size="18" /> Disable Room
                                    </button>
                                </form>
                            @elseif (! $room->isRentable())
                                <form method="POST" action="{{ route('rooms.toggle-rentable', $room) }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn sec" style="min-height: 44px; color: var(--green); display: inline-flex; align-items: center; justify-content: center; gap: 6px;" title="Enable room for renting">
                                        <x-icon name="check-circle" size="18" /> Enable Room
                                    </button>
                                </form>
                            @endif
                        @endcan

                        <button type="button" class="btn sec" onclick="closeRoomModal({{ $room->room_id }})" style="min-height: 44px; padding: 0 18px;">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- ========================================================================= --}}
    {{-- MODAL 1: Add Room (#createRoomModal) --}}
    {{-- ========================================================================= --}}
    @can('manage-rooms')
        <div id="createRoomModal" class="modal-backdrop" hidden>
            <div class="modal-box modal-lg">
                <div class="modal-header">
                    <h2>
                        <x-icon name="plus" size="20" />
                        <span>Add Room</span>
                    </h2>
                    <button type="button" class="modal-close-btn" onclick="closeCreateRoomModal()" aria-label="Close modal">&times;</button>
                </div>

                <form method="POST" action="{{ route('rooms.store') }}" id="createRoomForm" novalidate>
                    @csrf
                    <input type="hidden" name="is_modal_create" value="1">

                    <div class="row">
                        <div class="f @error('room_no') has-err @enderror" id="create_room_no_field">
                            <label for="create_room_no">Room Number</label>
                            <input id="create_room_no" name="room_no" type="text" maxlength="10" required autofocus value="{{ old('is_modal_create') ? old('room_no') : '' }}" placeholder="Room number" autocomplete="off">
                            <div id="create_room_no_feedback" class="err" style="display: none; margin-top: 4px;"></div>
                            @if (old('is_modal_create'))
                                @error('room_no') <div class="err server-err">{{ $message }}</div> @enderror
                            @endif
                        </div>
                        <div class="f @error('room_type') has-err @enderror">
                            <label for="create_room_type">Room Type</label>
                            <select id="create_room_type" name="room_type" required>
                                @foreach (['Basic', 'Aircon'] as $typeOption)
                                    <option value="{{ $typeOption }}" @selected(old('room_type', 'Basic') === $typeOption)>{{ $typeOption }}</option>
                                @endforeach
                            </select>
                            @if (old('is_modal_create'))
                                @error('room_type') <div class="err">{{ $message }}</div> @enderror
                            @endif
                        </div>
                    </div>

                    <div class="f check @error('is_metered') has-err @enderror" style="margin-top: 6px;">
                        <label>
                            <input type="checkbox" id="create_is_metered" name="is_metered" value="1" @checked(old('is_metered', false))>
                            Has its own electric meter (billed per kWh)
                        </label>
                        <div class="hint">Ticked automatically for Aircon rooms and unticked for Basic rooms; change it if this room is different.</div>
                    </div>

                    <div class="f @error('monthly_rent') has-err @enderror" style="margin-top: 10px;">
                        <label for="create_monthly_rent">Monthly Rent (₱)</label>
                        <input id="create_monthly_rent" name="monthly_rent" type="number" min="0.01" step="0.01" inputmode="decimal" required
                               value="{{ old('is_modal_create') ? old('monthly_rent') : number_format((float) ($setting->standard_basic_rent ?? 3000), 2, '.', '') }}">
                        <div class="hint">Applies to new MOAs. Existing tenants retain their contracted rate.</div>
                        @if (old('is_modal_create'))
                            @error('monthly_rent') <div class="err">{{ $message }}</div> @enderror
                        @endif
                    </div>

                    <div class="f check @error('is_rentable') has-err @enderror" style="margin-top: 10px;">
                        <input type="hidden" name="is_rentable" value="0">
                        <label>
                            <input type="checkbox" id="create_is_rentable" name="is_rentable" value="1" @checked(old('is_modal_create') ? old('is_rentable') : true)>
                            <b>Available for Renting</b>
                        </label>
                        <div class="hint">Uncheck this if the room is currently out of order, undergoing maintenance, or otherwise unavailable for new tenants.</div>
                    </div>

                    <div class="f @error('disabled_reason') has-err @enderror" id="create_disabled_reason_group" style="display: none; margin-top: 8px;">
                        <label for="create_disabled_reason">Reason If Out / Disabled</label>
                        <input id="create_disabled_reason" name="disabled_reason" type="text" maxlength="255" placeholder="e.g. Under maintenance, renovations..." value="{{ old('disabled_reason') }}">
                    </div>

                    <p class="muted" style="margin-top: 14px;">A new room starts as <b>Vacant</b>.</p>

                    <div class="form-actions" style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="btn sec" onclick="closeCreateRoomModal()" style="min-height: 44px; padding: 0 18px;">Cancel</button>
                        <button type="submit" class="btn" id="createRoomSubmitBtn" style="min-height: 44px; padding: 0 18px;">Save Room</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    {{-- ========================================================================= --}}
    {{-- MODAL 2: Edit Room (#editRoomModal) --}}
    {{-- ========================================================================= --}}
    @can('manage-rooms')
        <div id="editRoomModal" class="modal-backdrop" hidden>
            <div class="modal-box modal-lg">
                <div class="modal-header">
                    <h2>
                        <x-icon name="edit-3" size="20" />
                        <span id="edit_modal_title">Edit Room</span>
                        <span id="edit_modal_status_badge" style="margin-left: 8px;"></span>
                    </h2>
                    <button type="button" class="modal-close-btn" onclick="closeEditRoomModal()" aria-label="Close modal">&times;</button>
                </div>

                <form method="POST" id="editRoomForm" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="is_modal_edit" value="1">
                    <input type="hidden" name="edit_room_id" id="edit_room_id" value="{{ old('edit_room_id') }}">

                    <div class="row">
                        <div class="f @error('room_no') has-err @enderror" id="edit_room_no_field">
                            <label for="edit_room_no">Room Number</label>
                            <input id="edit_room_no" name="room_no" type="text" maxlength="10" required value="{{ old('is_modal_edit') ? old('room_no') : '' }}" autocomplete="off">
                            <div id="edit_room_no_feedback" class="err" style="display: none; margin-top: 4px;"></div>
                            @if (old('is_modal_edit'))
                                @error('room_no') <div class="err server-err">{{ $message }}</div> @enderror
                            @endif
                        </div>
                        <div class="f @error('room_type') has-err @enderror">
                            <label for="edit_room_type">Room Type</label>
                            <select id="edit_room_type" name="room_type" required>
                                @foreach (['Basic', 'Aircon'] as $typeOption)
                                    <option value="{{ $typeOption }}">{{ $typeOption }}</option>
                                @endforeach
                            </select>
                            @if (old('is_modal_edit'))
                                @error('room_type') <div class="err">{{ $message }}</div> @enderror
                            @endif
                        </div>
                    </div>

                    <div class="f check @error('is_metered') has-err @enderror" style="margin-top: 6px;">
                        <label>
                            <input type="checkbox" id="edit_is_metered" name="is_metered" value="1">
                            Has its own electric meter (billed per kWh)
                        </label>
                        <div class="hint">Rooms without a meter are billed a flat fee per declared appliance.</div>
                    </div>

                    <div class="row" style="margin-top: 10px;">
                        <div class="f @error('max_occupants') has-err @enderror">
                            <label for="edit_max_occupants">Maximum Occupants</label>
                            <select id="edit_max_occupants" name="max_occupants" required>
                                @foreach (range(1, (int) ($setting->default_room_capacity ?? 2)) as $n)
                                    <option value="{{ $n }}">{{ $n }} {{ Str::plural('Occupant', $n) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="f @error('monthly_rent') has-err @enderror">
                            <label for="edit_monthly_rent">Monthly Rent (₱)</label>
                            <input id="edit_monthly_rent" name="monthly_rent" type="number" min="0.01" step="0.01" inputmode="decimal" required
                                   value="{{ old('is_modal_edit') ? old('monthly_rent') : '' }}">
                            <div class="hint">Applies to future MOAs. Active leases maintain their signed rate.</div>
                            @if (old('is_modal_edit'))
                                @error('monthly_rent') <div class="err">{{ $message }}</div> @enderror
                            @endif
                        </div>
                    </div>

                    <div id="edit_rentable_container">
                        <div class="f check @error('is_rentable') has-err @enderror" style="margin-top: 10px;">
                            <input type="hidden" name="is_rentable" id="edit_is_rentable_hidden" value="0">
                            <label id="edit_is_rentable_label">
                                <input type="checkbox" id="edit_is_rentable" name="is_rentable" value="1">
                                <b>Available for Renting</b>
                            </label>
                            <div class="hint" id="edit_is_rentable_hint">Uncheck this if the room is out of service, undergoing maintenance, or temporarily unavailable.</div>
                            <div id="edit_occupied_disclaimer" class="sub text-xs text-muted" style="display: none; margin-top: 4px; color: var(--navy); font-weight: 500;">
                                <x-icon name="info" size="14" style="vertical-align: -2px; display: inline;" /> This room is currently occupied by an active lease and cannot be disabled.
                            </div>
                        </div>

                        <div class="f @error('disabled_reason') has-err @enderror" id="edit_disabled_reason_group" style="display: none; margin-top: 8px;">
                            <label for="edit_disabled_reason">Reason If Out / Disabled</label>
                            <input id="edit_disabled_reason" name="disabled_reason" type="text" maxlength="255" placeholder="e.g. Under maintenance, repairs..." value="{{ old('disabled_reason') }}">
                            <div class="hint">Optional note describing why the room is disabled.</div>
                        </div>
                    </div>

                    <p class="muted" style="margin-top: 14px;">The tenancy status (Vacant / Occupied) updates automatically via signed MOAs and lease endings.</p>

                    <div class="form-actions" style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" class="btn sec" onclick="closeEditRoomModal()" style="min-height: 44px; padding: 0 18px;">Cancel</button>
                        <button type="submit" class="btn" id="editRoomSubmitBtn" style="min-height: 44px; padding: 0 18px;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <script>
        const roomsMap = {!! json_encode($roomsMap ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
        const basicRent = '{{ number_format((float) ($setting->standard_basic_rent ?? 3000), 2, '.', '') }}';
        const airconRent = '{{ number_format((float) ($setting->standard_aircon_rent ?? 3600), 2, '.', '') }}';
        const checkAvailabilityUrl = '{{ route("rooms.check-availability") }}';
        let currentEditingRoomNo = '';
        let currentEditingRoomId = null;

        // Local cache for room availability checks
        const roomAvailabilityCache = {};
        Object.values(roomsMap).forEach(r => {
            roomAvailabilityCache[String(r.room_no).trim().toLowerCase()] = false; // false = already exists
        });

        let createCheckTimer = null;
        let editCheckTimer = null;

        function openRoomModal(roomId) {
            const modal = document.getElementById('roomModal' + roomId);
            if (modal) modal.removeAttribute('hidden');
        }

        function closeRoomModal(roomId) {
            const modal = document.getElementById('roomModal' + roomId);
            if (modal) modal.setAttribute('hidden', '');
        }

        function closeAllRoomModals() {
            document.querySelectorAll('.room-modal-backdrop').forEach(modal => {
                modal.setAttribute('hidden', '');
            });
        }

        // ---------- Real-Time Room Number Validation (Create Modal) ----------
        const createRoomNoInput = document.getElementById('create_room_no');
        const createRoomNoField = document.getElementById('create_room_no_field');
        const createRoomNoFeedback = document.getElementById('create_room_no_feedback');
        const createRoomSubmitBtn = document.getElementById('createRoomSubmitBtn');

        function applyCreateRoomValidationResult(val, exists) {
            if (exists) {
                if (createRoomNoField) createRoomNoField.classList.add('has-err');
                createRoomNoFeedback.style.display = 'block';
                createRoomNoFeedback.className = 'err text-red';
                createRoomNoFeedback.innerHTML = `Room "${val}" already exists. Please choose a different room number.`;
                if (createRoomSubmitBtn) {
                    createRoomSubmitBtn.disabled = true;
                    createRoomSubmitBtn.style.opacity = '0.55';
                    createRoomSubmitBtn.style.cursor = 'not-allowed';
                }
            } else {
                if (createRoomNoField) createRoomNoField.classList.remove('has-err');
                createRoomNoFeedback.style.display = 'block';
                createRoomNoFeedback.className = 'sub text-xs';
                createRoomNoFeedback.style.color = '#16a34a';
                createRoomNoFeedback.style.fontWeight = '600';
                createRoomNoFeedback.innerHTML = `✓ Room "${val}" is available`;
                if (createRoomSubmitBtn) {
                    createRoomSubmitBtn.disabled = false;
                    createRoomSubmitBtn.style.opacity = '1';
                    createRoomSubmitBtn.style.cursor = 'pointer';
                }
            }
        }

        function validateCreateRoomNoRealtime(immediate = false) {
            if (!createRoomNoInput || !createRoomNoFeedback) return;
            const val = createRoomNoInput.value.trim();

            const serverErr = createRoomNoField ? createRoomNoField.querySelector('.server-err') : null;
            if (serverErr) serverErr.style.display = 'none';

            if (!val) {
                createRoomNoFeedback.style.display = 'none';
                createRoomNoFeedback.innerHTML = '';
                if (createRoomNoField) createRoomNoField.classList.remove('has-err');
                if (createRoomSubmitBtn) {
                    createRoomSubmitBtn.disabled = false;
                    createRoomSubmitBtn.style.opacity = '1';
                    createRoomSubmitBtn.style.cursor = 'pointer';
                }
                return;
            }

            const valLower = val.toLowerCase();
            if (roomAvailabilityCache[valLower] !== undefined) {
                applyCreateRoomValidationResult(val, !roomAvailabilityCache[valLower]);
                return;
            }

            clearTimeout(createCheckTimer);
            const performCheck = () => {
                fetch(`${checkAvailabilityUrl}?room_no=${encodeURIComponent(val)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if (createRoomNoInput.value.trim().toLowerCase() === valLower) {
                        roomAvailabilityCache[valLower] = data.available;
                        applyCreateRoomValidationResult(val, data.exists);
                    }
                })
                .catch(err => console.error(err));
            };

            if (immediate) {
                performCheck();
            } else {
                createCheckTimer = setTimeout(performCheck, 120);
            }
        }

        if (createRoomNoInput) {
            createRoomNoInput.addEventListener('input', () => validateCreateRoomNoRealtime(false));
            createRoomNoInput.addEventListener('change', () => validateCreateRoomNoRealtime(true));
            createRoomNoInput.addEventListener('blur', () => validateCreateRoomNoRealtime(true));
        }

        const createRoomForm = document.getElementById('createRoomForm');
        if (createRoomForm) {
            createRoomForm.addEventListener('submit', function (e) {
                if (!createRoomNoInput) return;
                const val = createRoomNoInput.value.trim();
                const valLower = val.toLowerCase();
                if (roomAvailabilityCache[valLower] === false) {
                    e.preventDefault();
                    applyCreateRoomValidationResult(val, true);
                    createRoomNoInput.focus();
                    return false;
                }
            });
        }

        function openCreateRoomModal() {
            const modal = document.getElementById('createRoomModal');
            if (modal) {
                modal.removeAttribute('hidden');
                if (createRoomNoFeedback) {
                    createRoomNoFeedback.style.display = 'none';
                    createRoomNoFeedback.innerHTML = '';
                }
                if (createRoomNoField) createRoomNoField.classList.remove('has-err');
                if (createRoomSubmitBtn) {
                    createRoomSubmitBtn.disabled = false;
                    createRoomSubmitBtn.style.opacity = '1';
                    createRoomSubmitBtn.style.cursor = 'pointer';
                }
                setTimeout(() => {
                    if (createRoomNoInput) {
                        createRoomNoInput.focus();
                        if (createRoomNoInput.value.trim()) {
                            validateCreateRoomNoRealtime(true);
                        }
                    }
                }, 50);
            }
        }

        function closeCreateRoomModal() {
            const modal = document.getElementById('createRoomModal');
            if (modal) modal.setAttribute('hidden', '');
        }

        // ---------- Real-Time Room Number Validation (Edit Modal) ----------
        const editRoomNoInput = document.getElementById('edit_room_no');
        const editRoomNoField = document.getElementById('edit_room_no_field');
        const editRoomNoFeedback = document.getElementById('edit_room_no_feedback');
        const editRoomSubmitBtn = document.getElementById('editRoomSubmitBtn');

        function applyEditRoomValidationResult(val, exists) {
            if (exists) {
                if (editRoomNoField) editRoomNoField.classList.add('has-err');
                editRoomNoFeedback.style.display = 'block';
                editRoomNoFeedback.className = 'err text-red';
                editRoomNoFeedback.innerHTML = `Room "${val}" already exists. Please choose a different number.`;
                if (editRoomSubmitBtn) {
                    editRoomSubmitBtn.disabled = true;
                    editRoomSubmitBtn.style.opacity = '0.55';
                    editRoomSubmitBtn.style.cursor = 'not-allowed';
                }
            } else {
                if (editRoomNoField) editRoomNoField.classList.remove('has-err');
                if (val.toLowerCase() !== String(currentEditingRoomNo).trim().toLowerCase()) {
                    editRoomNoFeedback.style.display = 'block';
                    editRoomNoFeedback.className = 'sub text-xs';
                    editRoomNoFeedback.style.color = '#16a34a';
                    editRoomNoFeedback.style.fontWeight = '600';
                    editRoomNoFeedback.innerHTML = `✓ Room "${val}" is available`;
                } else {
                    editRoomNoFeedback.style.display = 'none';
                }
                if (editRoomSubmitBtn) {
                    editRoomSubmitBtn.disabled = false;
                    editRoomSubmitBtn.style.opacity = '1';
                    editRoomSubmitBtn.style.cursor = 'pointer';
                }
            }
        }

        function validateEditRoomNoRealtime(immediate = false) {
            if (!editRoomNoInput || !editRoomNoFeedback) return;
            const val = editRoomNoInput.value.trim();

            const serverErr = editRoomNoField ? editRoomNoField.querySelector('.server-err') : null;
            if (serverErr) serverErr.style.display = 'none';

            if (!val) {
                editRoomNoFeedback.style.display = 'none';
                editRoomNoFeedback.innerHTML = '';
                if (editRoomNoField) editRoomNoField.classList.remove('has-err');
                if (editRoomSubmitBtn) {
                    editRoomSubmitBtn.disabled = false;
                    editRoomSubmitBtn.style.opacity = '1';
                    editRoomSubmitBtn.style.cursor = 'pointer';
                }
                return;
            }

            const valLower = val.toLowerCase();
            if (valLower === String(currentEditingRoomNo).trim().toLowerCase()) {
                applyEditRoomValidationResult(val, false);
                return;
            }

            clearTimeout(editCheckTimer);
            const performCheck = () => {
                fetch(`${checkAvailabilityUrl}?room_no=${encodeURIComponent(val)}&exclude_id=${encodeURIComponent(currentEditingRoomId || '')}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if (editRoomNoInput.value.trim().toLowerCase() === valLower) {
                        applyEditRoomValidationResult(val, data.exists);
                    }
                })
                .catch(err => console.error(err));
            };

            if (immediate) {
                performCheck();
            } else {
                editCheckTimer = setTimeout(performCheck, 120);
            }
        }

        if (editRoomNoInput) {
            editRoomNoInput.addEventListener('input', () => validateEditRoomNoRealtime(false));
            editRoomNoInput.addEventListener('change', () => validateEditRoomNoRealtime(true));
            editRoomNoInput.addEventListener('blur', () => validateEditRoomNoRealtime(true));
        }

        const editRoomForm = document.getElementById('editRoomForm');
        if (editRoomForm) {
            editRoomForm.addEventListener('submit', function (e) {
                if (!editRoomNoInput) return;
                const val = editRoomNoInput.value.trim();
                const valLower = val.toLowerCase();
                if (valLower !== String(currentEditingRoomNo).trim().toLowerCase() && roomAvailabilityCache[valLower] === false) {
                    e.preventDefault();
                    applyEditRoomValidationResult(val, true);
                    editRoomNoInput.focus();
                    return false;
                }
            });
        }

        function openEditRoomModal(roomId) {
            const data = roomsMap[roomId];
            if (!data) return;

            currentEditingRoomNo = data.room_no || '';
            currentEditingRoomId = roomId;

            const form = document.getElementById('editRoomForm');
            if (form) form.action = data.update_url;

            const idInput = document.getElementById('edit_room_id');
            if (idInput) idInput.value = roomId;

            const titleEl = document.getElementById('edit_modal_title');
            if (titleEl) titleEl.textContent = 'Edit Room ' + data.room_no;

            const badgeEl = document.getElementById('edit_modal_status_badge');
            if (badgeEl) {
                let badgeClass = data.status === 'Vacant' ? 't-green' : 't-grey';
                badgeEl.innerHTML = `<span class="tag ${badgeClass}">${data.status}</span>`;
            }

            const noInput = document.getElementById('edit_room_no');
            if (noInput) noInput.value = data.room_no;

            if (editRoomNoFeedback) {
                editRoomNoFeedback.style.display = 'none';
                editRoomNoFeedback.innerHTML = '';
            }
            if (editRoomNoField) editRoomNoField.classList.remove('has-err');
            if (editRoomSubmitBtn) {
                editRoomSubmitBtn.disabled = false;
                editRoomSubmitBtn.style.opacity = '1';
                editRoomSubmitBtn.style.cursor = 'pointer';
            }

            const typeSelect = document.getElementById('edit_room_type');
            if (typeSelect) typeSelect.value = data.room_type;

            const meteredInput = document.getElementById('edit_is_metered');
            if (meteredInput) meteredInput.checked = !!data.is_metered;

            const maxInput = document.getElementById('edit_max_occupants');
            if (maxInput) maxInput.value = data.max_occupants;

            const rentInput = document.getElementById('edit_monthly_rent');
            if (rentInput) rentInput.value = data.monthly_rent_formatted;

            const rentableCb = document.getElementById('edit_is_rentable');
            const rentableHidden = document.getElementById('edit_is_rentable_hidden');
            const reasonGroup = document.getElementById('edit_disabled_reason_group');
            const reasonInput = document.getElementById('edit_disabled_reason');
            const occupiedDisclaimer = document.getElementById('edit_occupied_disclaimer');
            const rentableHint = document.getElementById('edit_is_rentable_hint');

            if (data.status === 'Occupied') {
                if (rentableCb) {
                    rentableCb.checked = true;
                    rentableCb.disabled = true;
                }
                if (rentableHidden) rentableHidden.value = '1';
                if (reasonGroup) reasonGroup.style.display = 'none';
                if (occupiedDisclaimer) occupiedDisclaimer.style.display = 'block';
                if (rentableHint) rentableHint.style.display = 'none';
            } else {
                if (rentableCb) {
                    rentableCb.disabled = false;
                    rentableCb.checked = !!data.is_rentable;
                }
                if (rentableHidden) rentableHidden.value = data.is_rentable ? '1' : '0';
                if (reasonInput) reasonInput.value = data.disabled_reason || '';
                if (reasonGroup) reasonGroup.style.display = data.is_rentable ? 'none' : 'block';
                if (occupiedDisclaimer) occupiedDisclaimer.style.display = 'none';
                if (rentableHint) rentableHint.style.display = 'block';
            }

            const modal = document.getElementById('editRoomModal');
            if (modal) modal.removeAttribute('hidden');
        }

        function closeEditRoomModal() {
            const modal = document.getElementById('editRoomModal');
            if (modal) modal.setAttribute('hidden', '');
        }

        // Room type change updates meter & rent in Create Modal
        const createRoomType = document.getElementById('create_room_type');
        if (createRoomType) {
            createRoomType.addEventListener('change', function () {
                const metered = document.getElementById('create_is_metered');
                if (metered) metered.checked = this.value === 'Aircon';
                const rent = document.getElementById('create_monthly_rent');
                if (rent) rent.value = this.value === 'Aircon' ? airconRent : basicRent;
            });
        }

        // Create modal rentable checkbox toggle
        const createRentable = document.getElementById('create_is_rentable');
        const createReasonGroup = document.getElementById('create_disabled_reason_group');
        if (createRentable && createReasonGroup) {
            createRentable.addEventListener('change', function () {
                createReasonGroup.style.display = this.checked ? 'none' : 'block';
            });
        }

        // Edit modal rentable checkbox toggle
        const editRentable = document.getElementById('edit_is_rentable');
        const editRentableHidden = document.getElementById('edit_is_rentable_hidden');
        const editReasonGroup = document.getElementById('edit_disabled_reason_group');
        if (editRentable && editReasonGroup) {
            editRentable.addEventListener('change', function () {
                if (editRentableHidden) editRentableHidden.value = this.checked ? '1' : '0';
                editReasonGroup.style.display = this.checked ? 'none' : 'block';
            });
        }

        // Auto-reopen modal if validation errors exist
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('room-search-input');
            const clearBtn = document.getElementById('room-search-clear');
            if (searchInput && clearBtn) {
                searchInput.addEventListener('input', function () {
                    clearBtn.style.display = this.value.length > 0 ? 'inline-flex' : 'none';
                });
            }

            @if ($errors->any())
                @if (old('is_modal_edit'))
                    openEditRoomModal({{ (int) old('edit_room_id') }});
                @elseif (old('is_modal_create'))
                    openCreateRoomModal();
                @endif
            @endif
        });

        // ESC key closes open modals
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAllRoomModals();
                closeCreateRoomModal();
                closeEditRoomModal();
            }
        });
    </script>
@endsection
