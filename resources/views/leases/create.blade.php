@extends('layouts.app')

@section('title', 'Encode new MOA')

@section('content')
    @if ($rooms->isEmpty())
        <div class="panel form-panel">
            <h2>No Vacant room</h2>
            <p>A new MOA can only be made for a Vacant room, and there is none right now.</p>
            <a href="{{ route('rooms.index') }}" class="btn sec">See room availability</a>
        </div>
    @else
        <form method="POST" action="{{ route('leases.store') }}" novalidate class="form-panel">
            @csrf

            @if ($inquiry)
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong>Inquiry-to-Lease Conversion:</strong>
                        Prospect <strong>{{ $inquiry->prospect_name }}</strong> ({{ $inquiry->contact_no }}).
                        Saving this MOA will automatically transition Inquiry #{{ $inquiry->inquiry_id }} to <b>Rented</b>.
                    </div>
                    <span style="font-size: 18px;">📋</span>
                </div>
                <input type="hidden" name="inquiry_id" value="{{ $inquiry->inquiry_id }}">
            @elseif ($inquiryId)
                <input type="hidden" name="inquiry_id" value="{{ $inquiryId }}">
            @endif

            <div class="panel">
                <h2>1. Principal renter</h2>
                @php($defaultMode = ($selectedTenantId ?? false) ? 'existing' : 'new')
                @php($mode = old('tenant_mode', $defaultMode))
                @php($availableTenants = $eligibleTenants ?? $returningTenants ?? collect())
                <div class="filters" role="radiogroup">
                    <label class="chip {{ $mode === 'existing' ? 'on' : '' }} {{ $availableTenants->isEmpty() ? 'disabled' : '' }}">
                        <input type="radio" name="tenant_mode" value="existing" @checked($mode === 'existing') @disabled($availableTenants->isEmpty()) class="sr-only"> Select registered tenant
                    </label>
                    <label class="chip {{ $mode === 'new' ? 'on' : '' }}">
                        <input type="radio" name="tenant_mode" value="new" @checked($mode === 'new') class="sr-only"> Encode new tenant
                    </label>
                </div>

                <div id="tenant-existing" @if ($mode !== 'existing') hidden @endif>
                    <div class="f @error('tenant_id') has-err @enderror">
                        <label for="tenant_id">Select tenant from Master Directory</label>
                        <select id="tenant_id" name="tenant_id">
                            <option value="">Choose registered tenant…</option>
                            @foreach ($availableTenants as $t)
                                <option value="{{ $t->tenant_id }}" @selected((string) old('tenant_id', $selectedTenantId ?? '') === (string) $t->tenant_id)>
                                    {{ $t->full_name }} ({{ $t->valid_id_type }}: {{ $t->valid_id_no }})
                                </option>
                            @endforeach
                        </select>
                        <div class="hint">Only verified tenants without an active lease are listed. Or switch to "Encode new tenant" above.</div>
                        @error('tenant_id') <div class="err">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div id="tenant-new" @if ($mode !== 'new') hidden @endif>
                    @include('tenants._fields', ['tenant' => $tenant])
                </div>
            </div>

            <div class="panel">
                <h2>2. Room and MOA details</h2>
                <div class="f @error('room_id') has-err @enderror">
                    <label for="room_id">Room</label>
                    <select id="room_id" name="room_id">
                        <option value="">Choose…</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->room_id }}" data-rent="{{ $room->monthly_rent }}" data-max="{{ $room->max_occupants }}" data-metered="{{ $room->is_metered ? 1 : 0 }}"
                                    @selected((string) old('room_id', $selectedRoomId) === (string) $room->room_id)>
                                Room {{ $room->room_no }} — {{ $room->room_type }}, {{ $room->is_metered ? 'own meter' : 'no meter' }}, max {{ $room->max_occupants }}, ₱{{ number_format((float) $room->monthly_rent, 2) }}
                            </option>
                        @endforeach
                    </select>
                    @error('room_id') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('move_in_reading') has-err @enderror" id="move-in-reading"
                     @if (! $rooms->firstWhere('room_id', old('room_id', $selectedRoomId))?->is_metered) hidden @endif>
                    <label for="move_in_reading">Electric meter reading at move-in (kWh, optional)</label>
                    <input id="move_in_reading" name="move_in_reading" type="number" min="0" step="0.01" inputmode="decimal" value="{{ old('move_in_reading') }}">
                    <div class="hint">Becomes the "previous reading" of this tenant's first bill.</div>
                    @error('move_in_reading') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('appliance_name') has-err @enderror" id="unmetered-appliance"
                     @if ($rooms->firstWhere('room_id', old('room_id', $selectedRoomId))?->is_metered) hidden @endif>
                    <label for="appliance_name">Declared Onboarding Appliance (Unmetered Unit, optional)</label>
                    <input id="appliance_name" name="appliance_name" type="text" maxlength="100"
                           value="{{ old('appliance_name') }}"
                           placeholder="e.g. Electric Rice Cooker">
                    <div class="hint">For units without submeters, declare standard high-wattage appliance (e.g. Electric Rice Cooker) to log in appliance registry.</div>
                    @error('appliance_name') <div class="err">{{ $message }}</div> @enderror
                </div>
                @include('leases._moa_fields', ['lease' => $lease])
            </div>

            <div class="panel">
                <h2>3. Declared co-tenant (optional)</h2>
                <div class="f @error('co_tenant_name') has-err @enderror">
                    <label for="co_tenant_name">Full name of co-tenant</label>
                    <input id="co_tenant_name" name="co_tenant_name" type="text" maxlength="255" value="{{ old('co_tenant_name') }}">
                    <div class="hint">Maximum 2 occupants per room (principal renter + 1). Leave blank if the tenant lives alone.</div>
                    @error('co_tenant_name') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="panel">
                <h2>4. Upfront Move-in Settlement</h2>
                <p class="muted" style="margin-bottom: 12px; font-size: 13px;">
                    The initial move-in settlement encompasses the 1-month advance rent plus required security/maintenance deposit components. A paid settlement bill and official payment receipt will automatically be logged.
                </p>
                <div class="row">
                    <div class="f @error('mode') has-err @enderror" style="flex: 1 1 200px;">
                        <label for="payment_mode">Payment Mode <span style="color: #ef4444;">*</span></label>
                        <select id="payment_mode" name="mode" required>
                            <option value="Cash" @selected(old('mode', 'Cash') === 'Cash')>Cash</option>
                            <option value="GCash" @selected(old('mode') === 'GCash')>GCash</option>
                        </select>
                        @error('mode') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    <div class="f @error('gcash_ref_no') has-err @enderror" id="gcash-ref-container" style="flex: 1 1 260px;"
                         @if (old('mode', 'Cash') !== 'GCash') hidden @endif>
                        <label for="gcash_ref_no">GCash Reference Number (13 digits) <span style="color: #ef4444;">*</span></label>
                        <input id="gcash_ref_no" name="gcash_ref_no" type="text" maxlength="13" inputmode="numeric"
                               value="{{ old('gcash_ref_no') }}" placeholder="13-digit reference number">
                        <div class="hint">Strictly 13 numerical digits from the GCash transaction SMS/receipt.</div>
                        @error('gcash_ref_no') <div class="err">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save MOA</button>
                <a href="{{ route('tenants.index') }}" class="btn sec">Cancel</a>
            </div>
            <p class="muted">Saving sets the room to <b>Occupied</b>.</p>
        </form>

        @push('scripts')
            <script>
                (function () {
                    // New / returning tenant switch
                    document.querySelectorAll('input[name="tenant_mode"]').forEach(function (radio) {
                        radio.addEventListener('change', function () {
                            document.getElementById('tenant-new').hidden = this.value !== 'new';
                            document.getElementById('tenant-existing').hidden = this.value !== 'existing';
                            document.querySelectorAll('input[name="tenant_mode"]').forEach(function (r) {
                                r.parentElement.classList.toggle('on', r.checked);
                            });
                        });
                    });

                    // Picking a room fills in its rent (and the advance) if still empty, toggles meter / appliance inputs.
                    var room = document.getElementById('room_id');
                    var reading = document.getElementById('move-in-reading');
                    var appliance = document.getElementById('unmetered-appliance');
                    room.addEventListener('change', function () {
                        var opt = room.options[room.selectedIndex];
                        var isMetered = (opt && opt.dataset.metered === '1');
                        if (reading) reading.hidden = !isMetered;
                        if (appliance) appliance.hidden = isMetered;
                        var rent = document.getElementById('monthly_rent');
                        var adv = document.getElementById('advance_amount');
                        if (opt && opt.dataset.rent) {
                            if (!rent.value) { rent.value = opt.dataset.rent; }
                            if (!adv.value) { adv.value = opt.dataset.rent; }
                            rent.dispatchEvent(new Event('input'));
                        }
                    });

                    // Payment mode switch
                    var modeSelect = document.getElementById('payment_mode');
                    var gcashContainer = document.getElementById('gcash-ref-container');
                    if (modeSelect && gcashContainer) {
                        modeSelect.addEventListener('change', function () {
                            gcashContainer.hidden = (this.value !== 'GCash');
                        });
                    }
                })();
            </script>
        @endpush
    @endif
@endsection
