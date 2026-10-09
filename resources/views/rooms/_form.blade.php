{{-- Shared add/edit room form (Owner). Needs: $room, $types --}}
@csrf

<div class="row">
    <div class="f @error('room_no') has-err @enderror" id="standalone_room_no_field">
        <label for="room_no">Room number</label>
        <input id="room_no" name="room_no" type="text" maxlength="10" required autofocus value="{{ old('room_no', $room->room_no) }}" autocomplete="off">
        <div id="standalone_room_no_feedback" class="err" style="display: none; margin-top: 4px;"></div>
        @error('room_no') <div class="err server-err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('room_type') has-err @enderror">
        <label for="room_type">Room type</label>
        <select id="room_type" name="room_type" required>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('room_type', $room->room_type) === $type)>{{ $type }}</option>
            @endforeach
        </select>
        @error('room_type') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="f check @error('is_metered') has-err @enderror">
    <label>
        <input type="checkbox" id="is_metered" name="is_metered" value="1" @checked(old('is_metered', $room->is_metered))>
        Has its own electric meter (billed per kWh)
    </label>
    <div class="hint">Ticked automatically for Aircon rooms and unticked for Basic rooms; change it if this room is different.
        Rooms without a meter are billed a flat fee per appliance.</div>
    @error('is_metered') <div class="err">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="f @error('max_occupants') has-err @enderror">
        <label for="max_occupants">Maximum occupants</label>
        <select id="max_occupants" name="max_occupants" required>
            @foreach ([1, 2] as $n)
                <option value="{{ $n }}" @selected((int) old('max_occupants', $room->max_occupants) === $n)>{{ $n }}</option>
            @endforeach
        </select>
        @error('max_occupants') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('monthly_rent') has-err @enderror">
        <label for="monthly_rent">Monthly rent (₱)</label>
        <input id="monthly_rent" name="monthly_rent" type="number" min="0.01" step="0.01" inputmode="decimal" required
               value="{{ old('monthly_rent', $room->monthly_rent) }}">
        <div class="hint">Applies to new MOAs. Existing tenants keep the rent written in their MOA.</div>
        @error('monthly_rent') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

@if ($room->exists && $room->status === 'Occupied')
    <div class="f check" style="margin-top: 6px;">
        <input type="hidden" name="is_rentable" value="1">
        <label>
            <input type="checkbox" id="is_rentable" checked disabled>
            <b>Available for Renting</b>
        </label>
        <div class="hint" style="color: var(--navy); font-weight: 500;">
            This room is currently occupied by an active lease and cannot be disabled.
        </div>
    </div>
@else
    <div class="f check @error('is_rentable') has-err @enderror" style="margin-top: 6px;">
        <input type="hidden" name="is_rentable" value="0">
        <label>
            <input type="checkbox" id="is_rentable" name="is_rentable" value="1" @checked(old('is_rentable', $room->isRentable()))>
            <b>Available for Renting</b>
        </label>
        <div class="hint">Uncheck this if the room is currently out of service, undergoing maintenance, or otherwise unavailable for new tenants.</div>
        @error('is_rentable') <div class="err">{{ $message }}</div> @enderror
    </div>

    <div class="f @error('disabled_reason') has-err @enderror" id="disabled_reason_group" style="{{ old('is_rentable', $room->isRentable()) ? 'display: none;' : '' }}">
        <label for="disabled_reason">Reason If Out / Disabled</label>
        <input id="disabled_reason" name="disabled_reason" type="text" maxlength="255" placeholder="e.g. Under maintenance, repairs in progress..." value="{{ old('disabled_reason', $room->disabled_reason) }}">
        <div class="hint">Optional note explaining why this room is out or unavailable for rent.</div>
        @error('disabled_reason') <div class="err">{{ $message }}</div> @enderror
    </div>
@endif

@push('scripts')
    <script>
        // Default the meter checkbox and baseline rent from room type.
        var isNewRoom = {{ $room->exists ? 'false' : 'true' }};
        var basicRent = '{{ number_format((float) ($setting->standard_basic_rent ?? 3000), 2, '.', '') }}';
        var airconRent = '{{ number_format((float) ($setting->standard_aircon_rent ?? 3600), 2, '.', '') }}';

        var roomTypeSelect = document.getElementById('room_type');
        if (roomTypeSelect) {
            roomTypeSelect.addEventListener('change', function () {
                var meteredCb = document.getElementById('is_metered');
                if (meteredCb) meteredCb.checked = this.value === 'Aircon';
                if (isNewRoom) {
                    var rentInput = document.getElementById('monthly_rent');
                    if (rentInput) {
                        rentInput.value = this.value === 'Aircon' ? airconRent : basicRent;
                    }
                }
            });
        }

        var rentableCb = document.getElementById('is_rentable');
        var reasonGroup = document.getElementById('disabled_reason_group');
        if (rentableCb && reasonGroup) {
            rentableCb.addEventListener('change', function () {
                reasonGroup.style.display = this.checked ? 'none' : 'block';
            });
        }

        // Real-time room number uniqueness check
        var checkAvailabilityUrl = '{{ route("rooms.check-availability") }}';
        var currentRoomNo = '{{ $room->room_no ?? '' }}';
        var currentRoomId = '{{ $room->room_id ?? '' }}';
        var roomNoInput = document.getElementById('room_no');
        var roomNoField = document.getElementById('standalone_room_no_field');
        var roomNoFeedback = document.getElementById('standalone_room_no_feedback');
        var formEl = roomNoInput ? roomNoInput.closest('form') : null;
        var submitBtn = formEl ? formEl.querySelector('button[type="submit"]') : null;
        var standaloneCache = {};
        var standaloneTimer = null;

        function applyStandaloneResult(val, exists) {
            if (exists) {
                if (roomNoField) roomNoField.classList.add('has-err');
                roomNoFeedback.style.display = 'block';
                roomNoFeedback.className = 'err text-red';
                roomNoFeedback.innerHTML = 'Room "' + val + '" already exists. Please choose a different number.';
                if (submitBtn) { submitBtn.disabled = true; submitBtn.style.opacity = '0.55'; }
            } else {
                if (roomNoField) roomNoField.classList.remove('has-err');
                if (val.toLowerCase() !== String(currentRoomNo).trim().toLowerCase()) {
                    roomNoFeedback.style.display = 'block';
                    roomNoFeedback.className = 'sub text-xs';
                    roomNoFeedback.style.color = '#16a34a';
                    roomNoFeedback.style.fontWeight = '600';
                    roomNoFeedback.innerHTML = '✓ Room "' + val + '" is available';
                } else {
                    roomNoFeedback.style.display = 'none';
                }
                if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = '1'; }
            }
        }

        function validateStandaloneRoomNo(immediate) {
            if (!roomNoInput || !roomNoFeedback) return;
            var val = roomNoInput.value.trim();
            var serverErr = roomNoField ? roomNoField.querySelector('.server-err') : null;
            if (serverErr) serverErr.style.display = 'none';

            if (!val) {
                roomNoFeedback.style.display = 'none';
                roomNoFeedback.innerHTML = '';
                if (roomNoField) roomNoField.classList.remove('has-err');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = '1'; }
                return;
            }

            var valLower = val.toLowerCase();
            if (valLower === String(currentRoomNo).trim().toLowerCase()) {
                applyStandaloneResult(val, false);
                return;
            }

            if (standaloneCache[valLower] !== undefined) {
                applyStandaloneResult(val, !standaloneCache[valLower]);
                return;
            }

            clearTimeout(standaloneTimer);
            var performCheck = function () {
                var url = checkAvailabilityUrl + '?room_no=' + encodeURIComponent(val);
                if (currentRoomId) url += '&exclude_id=' + encodeURIComponent(currentRoomId);
                fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (roomNoInput.value.trim().toLowerCase() === valLower) {
                            standaloneCache[valLower] = data.available;
                            applyStandaloneResult(val, data.exists);
                        }
                    })
                    .catch(function (err) { console.error(err); });
            };

            if (immediate) {
                performCheck();
            } else {
                standaloneTimer = setTimeout(performCheck, 120);
            }
        }

        if (roomNoInput) {
            roomNoInput.addEventListener('input', function () { validateStandaloneRoomNo(false); });
            roomNoInput.addEventListener('change', function () { validateStandaloneRoomNo(true); });
            roomNoInput.addEventListener('blur', function () { validateStandaloneRoomNo(true); });
        }
        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                if (!roomNoInput) return;
                var val = roomNoInput.value.trim();
                var valLower = val.toLowerCase();
                if (valLower !== String(currentRoomNo).trim().toLowerCase() && standaloneCache[valLower] === false) {
                    e.preventDefault();
                    applyStandaloneResult(val, true);
                    roomNoInput.focus();
                    return false;
                }
            });
        }
    </script>
@endpush
