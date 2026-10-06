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
                    <label for="room_id">Room (Vacant rooms only)</label>
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

                    // Picking a room fills in its rent (and the advance) if still empty.
                    var room = document.getElementById('room_id');
                    room.addEventListener('change', function () {
                        var opt = room.options[room.selectedIndex];
                        document.getElementById('move-in-reading').hidden = !(opt && opt.dataset.metered === '1');
                        var rent = document.getElementById('monthly_rent');
                        var adv = document.getElementById('advance_amount');
                        if (opt && opt.dataset.rent) {
                            if (!rent.value) { rent.value = opt.dataset.rent; }
                            if (!adv.value) { adv.value = opt.dataset.rent; }
                            rent.dispatchEvent(new Event('input'));
                        }
                    });
                })();
            </script>
        @endpush
    @endif
@endsection
