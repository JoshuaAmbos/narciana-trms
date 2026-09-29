{{-- Shared add/edit room form (Owner). Needs: $room, $types --}}
@csrf

<div class="row">
    <div class="f @error('room_no') has-err @enderror">
        <label for="room_no">Room number</label>
        <input id="room_no" name="room_no" type="text" maxlength="10" required value="{{ old('room_no', $room->room_no) }}">
        @error('room_no') <div class="err">{{ $message }}</div> @enderror
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

@push('scripts')
    <script>
        // Default the meter checkbox from the room type (the Owner can still change it).
        document.getElementById('room_type').addEventListener('change', function () {
            document.getElementById('is_metered').checked = this.value === 'Aircon';
        });
    </script>
@endpush
