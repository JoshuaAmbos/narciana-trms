@extends('layouts.app')

@section('title', 'Encode New MOA')

@section('content')
    <div class="w-full max-w-full lg:max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @if ($rooms->isEmpty())
            <div class="panel p-6 sm:p-8">
                <h2>No Vacant Room</h2>
                <p class="muted">A new MOA can only be made for a Vacant room, and there is none right now.</p>
                <a href="{{ route('rooms.index') }}" class="btn sec">See room availability</a>
            </div>
        @else
            <form method="POST" action="{{ route('leases.store') }}" novalidate class="w-full space-y-6 sm:space-y-8">
                @csrf

                @if ($inquiry)
                    <div class="alert alert-ok" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <strong>Inquiry-to-Lease Conversion:</strong>
                            Prospect <strong>{{ $inquiry->prospect_name }}</strong> ({{ $inquiry->contact_no }}).
                            Saving this MOA will automatically transition Inquiry #{{ $inquiry->inquiry_id }} to <b>Rented</b>.
                        </div>
                        <x-icon name="arrow-right" size="18" />
                    </div>
                    <input type="hidden" name="inquiry_id" value="{{ $inquiry->inquiry_id }}">
                @elseif ($inquiryId)
                    <input type="hidden" name="inquiry_id" value="{{ $inquiryId }}">
                @endif

                {{-- Section 1: Principal Renter --}}
                <div class="panel p-6 sm:p-8 space-y-5 sm:space-y-6">
                    <h2>1. Principal Renter</h2>
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
                            <label for="tenant_id">Select tenant from Master Directory <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <select id="tenant_id" name="tenant_id">
                                <option value="">Choose registered tenant…</option>
                                @foreach ($availableTenants as $t)
                                    <option value="{{ $t->tenant_id }}" @selected((string) old('tenant_id', $selectedTenantId ?? '') === (string) $t->tenant_id)>
                                        {{ $t->full_name }} ({{ $t->valid_id_type }}: {{ $t->valid_id_no }})
                                    </option>
                                @endforeach
                            </select>
                            @error('tenant_id') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div id="tenant-new" @if ($mode !== 'new') hidden @endif>
                        @include('tenants._fields', ['tenant' => $tenant])
                    </div>
                </div>

                {{-- Section 2: Room & MOA Details --}}
                <div class="panel p-6 sm:p-8 space-y-5 sm:space-y-6">
                    <h2>2. Room & MOA Details</h2>
                    @php($selectedRoom = $rooms->firstWhere('room_id', old('room_id', $selectedRoomId)))
                    @php($isAirconSelected = (bool) ($selectedRoom && ($selectedRoom->room_type === 'Aircon' || $selectedRoom->is_metered)))
                    
                    <div id="min-stay-banner" class="alert alert-info min-stay-info-banner">
                        <x-icon name="info" size="18" />
                        <span id="min-stay-banner-text">
                            @if ($isAirconSelected)
                                Minimum Stay: 6 Months (MOA Aircon Terms — premature departure forfeits deposit)
                            @else
                                Minimum Stay: 4 Months (MOA Basic Terms — premature departure forfeits deposit)
                            @endif
                        </span>
                    </div>

                    <div class="f @error('room_id') has-err @enderror">
                        <label for="room_id">Room <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                        <select id="room_id" name="room_id" required>
                            <option value="">Choose…</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->room_id }}" data-rent="{{ $room->monthly_rent }}" data-max="{{ $room->max_occupants }}" data-metered="{{ $room->is_metered ? 1 : 0 }}" data-type="{{ $room->room_type }}"
                                        @selected((string) old('room_id', $selectedRoomId) === (string) $room->room_id)>
                                    Room {{ $room->room_no }} — {{ $room->room_type }}, {{ $room->is_metered ? 'own meter' : 'no meter' }}, max {{ $room->max_occupants }}, ₱{{ number_format((float) $room->monthly_rent, 2) }}
                                </option>
                            @endforeach
                        </select>
                        @error('room_id') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    {{-- Aircon Submeter Reading --}}
                    <div class="f @error('move_in_reading') has-err @enderror" id="move-in-reading"
                         @if (! $isAirconSelected) hidden @endif>
                        <label for="move_in_reading">Baseline move-in meter reading (kWh) <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                        <input id="move_in_reading" name="move_in_reading" type="number" min="0" step="0.01" inputmode="decimal"
                               value="{{ old('move_in_reading') }}"
                               @if ($isAirconSelected) required @endif>
                        <div class="hint">Required for air-conditioned rooms per Aircon MOA Clause 6. Billed at ₱12.00/kWh.</div>
                        @error('move_in_reading') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    {{-- Basic Room Multi-Entry Declared Appliances Repeater --}}
                    <div id="unmetered-appliance" class="space-y-4 pt-2" @if ($isAirconSelected) hidden @endif>
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 m-0">
                                    Declared Onboarding Appliances (Basic Rooms)
                                </h3>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    For units without submeters, declare standard high-wattage appliances (₱200/mo surcharge).
                                </div>
                            </div>
                            <button type="button" id="btn-add-appliance" class="btn sec" style="min-height: 40px; padding: 0 14px; font-size: 13px;">
                                + Add Declared Appliance
                            </button>
                        </div>

                        {{-- Prohibited device guardrail banner --}}
                        <div id="prohibited-guardrail-banner" class="alert alert-warn" style="display: none; align-items: center; gap: 10px; margin-bottom: 12px; font-size: 13px;">
                            <x-icon name="alert-triangle" size="18" />
                            <span>Refrigerators and TVs are strictly prohibited in Basic rooms per house policy. The tenant must transfer to an Aircon/Metered room to use heavy appliances.</span>
                        </div>

                        <div id="appliance-repeater-list" class="space-y-3">
                            {{-- Repeater rows injected by JavaScript --}}
                        </div>
                        <input type="hidden" name="appliance_name" id="legacy_appliance_name" value="{{ old('appliance_name') }}">
                        @error('appliance_name') <div class="err">{{ $message }}</div> @enderror
                        @error('declared_appliances.*.custom_name') <div class="err">{{ $message }}</div> @enderror
                    </div>

                    {{-- Distinct visual grouping for financial breakdown --}}
                    <div class="border-t border-slate-100 pt-5 mt-5">
                        @include('leases._moa_fields', ['lease' => $lease])
                    </div>
                </div>

                {{-- Section 3: Declared Co-Tenant --}}
                <div class="panel p-6 sm:p-8 space-y-5 sm:space-y-6">
                    <h2>3. Declared Co-Tenant (Optional)</h2>
                    <div class="f @error('co_tenant_name') has-err @enderror">
                        <label for="co_tenant_name">Full name of co-tenant <span class="text-muted" style="font-weight: normal; font-size: 0.85em;">(optional)</span></label>
                        <input id="co_tenant_name" name="co_tenant_name" type="text" maxlength="255" value="{{ old('co_tenant_name') }}">
                        <div class="hint">Maximum 2 occupants per room (principal renter + 1). Leave blank if the tenant lives alone.</div>
                        @error('co_tenant_name') <div class="err">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Section 4: Upfront Move-In Settlement --}}
                <div class="panel p-6 sm:p-8 space-y-5 sm:space-y-6">
                    <h2>4. Upfront Move-In Settlement</h2>
                    <p class="muted" style="margin-bottom: 12px; font-size: 13px;">
                        The initial move-in settlement encompasses the 1-month advance rent plus required security/maintenance deposit components. A paid settlement bill and official payment receipt will automatically be logged.
                    </p>
                    <div class="row">
                        <div class="f @error('mode') @error('payment_mode') has-err @enderror @enderror" style="flex: 1 1 200px;">
                            <label for="payment_mode">Payment Mode <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <select id="payment_mode" name="payment_mode" required>
                                <option value="Cash" @selected(old('payment_mode', old('mode', 'Cash')) === 'Cash')>Cash</option>
                                <option value="GCash" @selected(old('payment_mode', old('mode')) === 'GCash')>GCash</option>
                            </select>
                            <input type="hidden" name="mode" id="mode_alias" value="{{ old('payment_mode', old('mode', 'Cash')) }}">
                            @error('payment_mode') <div class="err">{{ $message }}</div> @enderror
                            @error('mode') <div class="err">{{ $message }}</div> @enderror
                        </div>

                        <div class="f @error('gcash_ref_no') has-err @enderror" id="gcash-ref-container" style="flex: 1 1 260px;"
                             @if (old('payment_mode', old('mode', 'Cash')) !== 'GCash') hidden @endif>
                            <label for="gcash_ref_no">GCash Reference Number (13 digits) <span class="req-star" id="moa_gcash_star" style="color: #dc2626; font-weight: 700; margin-left: 2px; {{ old('payment_mode', old('mode', 'Cash')) === 'GCash' ? '' : 'display: none;' }}" aria-hidden="true">*</span></label>
                            <input id="gcash_ref_no" name="gcash_ref_no" type="text" maxlength="13" inputmode="numeric"
                                   value="{{ old('gcash_ref_no') }}" placeholder="13-digit reference number"
                                   {{ old('payment_mode', old('mode', 'Cash')) === 'GCash' ? 'required' : 'disabled' }}>
                            <div class="hint">Strictly 13 numerical digits from the GCash transaction SMS/receipt.</div>
                            @error('gcash_ref_no') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="form-actions mt-6 flex justify-end items-center gap-3" style="display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                    <a href="{{ route('leases.index') }}" class="btn sec">Cancel</a>
                    <button type="submit" class="btn">Save MOA</button>
                </div>
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

                        // Picking a room fills in its configured rent and advance, toggles meter / appliance inputs, and adjusts min stay.
                        var room = document.getElementById('room_id');
                        var reading = document.getElementById('move-in-reading');
                        var readingInput = document.getElementById('move_in_reading');
                        var appliance = document.getElementById('unmetered-appliance');
                        var minStayInput = document.getElementById('min_stay_months');
                        var minStayBadgeInput = document.getElementById('minStayInput');
                        var minStayText = document.getElementById('min_stay_text');
                        var bannerText = document.getElementById('min-stay-banner-text');

                        room.addEventListener('change', function () {
                            var opt = room.options[room.selectedIndex];
                            var isAircon = opt && (opt.dataset.type === 'Aircon' || opt.dataset.metered === '1');
                            var isMetered = opt && (opt.dataset.metered === '1');

                            if (reading) reading.hidden = !isAircon;
                            if (readingInput) {
                                if (isAircon) {
                                    readingInput.setAttribute('required', 'required');
                                } else {
                                    readingInput.removeAttribute('required');
                                    readingInput.value = '';
                                }
                            }

                            if (appliance) appliance.hidden = isMetered;

                            var minStayMonths = isAircon ? 6 : 4;
                            if (minStayInput) minStayInput.value = minStayMonths;
                            if (minStayBadgeInput) minStayBadgeInput.value = minStayMonths;
                            if (minStayText) minStayText.textContent = minStayMonths + ' Months';
                            if (bannerText) {
                                if (isAircon) {
                                    bannerText.textContent = 'Minimum Stay: 6 Months (MOA Aircon Terms — premature departure forfeits deposit)';
                                } else {
                                    bannerText.textContent = 'Minimum Stay: 4 Months (MOA Basic Terms — premature departure forfeits deposit)';
                                }
                            }

                            var rent = document.getElementById('monthly_rent');
                            var adv = document.getElementById('advance_amount');
                            if (opt && opt.dataset.rent) {
                                rent.value = opt.dataset.rent;
                                adv.value = opt.dataset.rent;
                                rent.dispatchEvent(new Event('input'));
                                rent.dispatchEvent(new Event('change'));
                            }
                        });

                        // Multi-entry declared appliance repeater
                        var applianceList = document.getElementById('appliance-repeater-list');
                        var addApplianceBtn = document.getElementById('btn-add-appliance');
                        var prohibitedBanner = document.getElementById('prohibited-guardrail-banner');
                        var applianceIndex = 0;
                        var defaultApplianceFee = {{ number_format((float) (\App\Models\Setting::current()->appliance_fee ?? 200), 2, '.', '') }};

                        var prohibitedKeywords = ['refrigerator', 'fridge', 'ref', 'television', 'tv', 'aircon', 'air conditioner', 'air-conditioner'];

                        function checkAnyProhibited() {
                            var hasProhibited = false;
                            document.querySelectorAll('.appliance-row').forEach(function (row) {
                                var select = row.querySelector('.device-type-select');
                                var custom = row.querySelector('.custom-name-input');
                                var name = select.value === 'Other Surchargeable Appliance' ? (custom ? custom.value : '') : select.value;
                                var lower = (name || '').toLowerCase().trim();
                                var isRowProhibited = prohibitedKeywords.some(function (kw) {
                                    if (kw === 'ref' || kw === 'tv') {
                                        return new RegExp('\\b' + kw + '\\b', 'i').test(lower);
                                    }
                                    return lower.indexOf(kw) !== -1;
                                });
                                var rowWarning = row.querySelector('.row-prohibited-warning');
                                if (rowWarning) rowWarning.style.display = isRowProhibited ? 'flex' : 'none';
                                if (isRowProhibited) hasProhibited = true;
                            });
                            if (prohibitedBanner) prohibitedBanner.style.display = hasProhibited ? 'flex' : 'none';
                        }

                        function addApplianceRow(type, customName, fee) {
                            var row = document.createElement('div');
                            row.className = 'appliance-row';
                            row.dataset.index = applianceIndex;

                            var selType = type || 'Electric Rice Cooker';
                            var isOther = selType === 'Other Surchargeable Appliance';
                            var curFee = fee !== undefined ? fee : defaultApplianceFee;

                            row.innerHTML = `
                                <div class="appliance-grid">
                                    <div class="col-type">
                                        <label>Device Type</label>
                                        <select name="declared_appliances[${applianceIndex}][device_type]" class="device-type-select w-full">
                                            <option value="Electric Rice Cooker" ${selType === 'Electric Rice Cooker' ? 'selected' : ''}>Electric Rice Cooker</option>
                                            <option value="Electric Kettle" ${selType === 'Electric Kettle' ? 'selected' : ''}>Electric Kettle</option>
                                            <option value="Other Surchargeable Appliance" ${isOther ? 'selected' : ''}>Other Surchargeable Appliance</option>
                                        </select>
                                    </div>
                                    <div class="col-name custom-name-col" style="${isOther ? 'display: block;' : 'display: none;'}">
                                        <label>Specify Appliance</label>
                                        <input type="text" name="declared_appliances[${applianceIndex}][custom_name]" class="custom-name-input w-full" placeholder="e.g. Induction Cooker" value="${customName || ''}">
                                    </div>
                                    <div class="col-fee">
                                        <label>Monthly Fee (₱)</label>
                                        <input type="number" step="0.01" min="0" name="declared_appliances[${applianceIndex}][monthly_fee]" class="w-full no-spin" value="${curFee}">
                                    </div>
                                    <div class="col-action">
                                        <button type="button" class="btn sec remove-row-btn" style="min-height: 44px; color: var(--red); border-color: #fca5a5;" title="Remove this appliance">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                                <div class="row-prohibited-warning alert alert-warn" style="display: none; align-items: center; gap: 8px; margin-top: 10px; font-size: 12px;">
                                    <span>Refrigerators and TVs are strictly prohibited in Basic rooms per house policy. The tenant must transfer to an Aircon/Metered room to use heavy appliances.</span>
                                </div>
                            `;

                            var select = row.querySelector('.device-type-select');
                            var customCol = row.querySelector('.custom-name-col');
                            var customInput = row.querySelector('.custom-name-input');
                            var removeBtn = row.querySelector('.remove-row-btn');

                            select.addEventListener('change', function () {
                                var isCustom = this.value === 'Other Surchargeable Appliance';
                                customCol.style.display = isCustom ? 'block' : 'none';
                                checkAnyProhibited();
                            });

                            customInput.addEventListener('input', checkAnyProhibited);

                            removeBtn.addEventListener('click', function () {
                                row.remove();
                                checkAnyProhibited();
                            });

                            applianceList.appendChild(row);
                            applianceIndex++;
                            checkAnyProhibited();
                        }

                        if (addApplianceBtn) {
                            addApplianceBtn.addEventListener('click', function () {
                                addApplianceRow();
                            });
                        }

                        // Payment mode switch
                        var modeSelect = document.getElementById('payment_mode');
                        var modeAlias = document.getElementById('mode_alias');
                        var gcashContainer = document.getElementById('gcash-ref-container');
                        var gcashInput = document.getElementById('gcash_ref_no');
                        var gcashStar = document.getElementById('moa_gcash_star');

                        function updatePaymentMode(mode) {
                            if (modeAlias) modeAlias.value = mode;
                            var isGcash = (mode === 'GCash');
                            if (gcashContainer) gcashContainer.hidden = !isGcash;
                            if (gcashInput) {
                                gcashInput.disabled = !isGcash;
                                if (isGcash) {
                                    gcashInput.setAttribute('required', 'required');
                                } else {
                                    gcashInput.removeAttribute('required');
                                    gcashInput.value = '';
                                }
                            }
                            if (gcashStar) {
                                gcashStar.style.display = isGcash ? 'inline' : 'none';
                            }
                        }

                        if (modeSelect) {
                            modeSelect.addEventListener('change', function () {
                                updatePaymentMode(this.value);
                            });
                            updatePaymentMode(modeSelect.value);
                        }
                    })();
                </script>
            @endpush
        @endif
    </div>
@endsection
