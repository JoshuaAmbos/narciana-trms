{{-- Inquiry form fields, shared by "Log an inquiry" (inquiries/index) and "Update inquiry" (inquiries/edit).
     Needs: $inquiry, $rooms, $holds, $types, $channels, $statuses --}}
@csrf

<div class="row">
    <div class="f @error('prospect_name') has-err @enderror">
        <label for="prospect_name">Name</label>
        <input id="prospect_name" name="prospect_name" type="text" maxlength="255" required
               value="{{ old('prospect_name', $inquiry->prospect_name) }}">
        @error('prospect_name') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('contact_no') has-err @enderror">
        <label for="contact_no">Contact number</label>
        <input id="contact_no" name="contact_no" type="tel" maxlength="15" placeholder="09XXXXXXXXX" required
               value="{{ old('contact_no', $inquiry->contact_no) }}">
        @error('contact_no') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="f @error('preferred_type') has-err @enderror">
        <label for="preferred_type">Room type wanted</label>
        <select id="preferred_type" name="preferred_type" required>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('preferred_type', $inquiry->preferred_type ?? 'Any') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        @error('preferred_type') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('no_of_occupants') has-err @enderror">
        <label for="no_of_occupants">Occupants</label>
        <input id="no_of_occupants" name="no_of_occupants" type="number" min="1" max="2" inputmode="numeric" required
               value="{{ old('no_of_occupants', $inquiry->no_of_occupants) }}">
        @error('no_of_occupants') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('channel') has-err @enderror">
        <label for="channel">Channel</label>
        <select id="channel" name="channel" required>
            @foreach ($channels as $channel)
                <option value="{{ $channel }}" @selected(old('channel', $inquiry->channel ?? 'Walk-in') === $channel)>{{ $channel }}</option>
            @endforeach
        </select>
        @error('channel') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('inquiry_date') has-err @enderror">
        <label for="inquiry_date">Date</label>
        <input id="inquiry_date" name="inquiry_date" type="date" max="{{ today()->toDateString() }}" required
               value="{{ old('inquiry_date', $inquiry->inquiry_date?->toDateString()) }}">
        @error('inquiry_date') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="f check">
    <label>
        <input type="checkbox" name="wants_contact" value="1"
               @checked(old('wants_contact', $inquiry->wants_contact))>
        Wants to be contacted when a room is available
    </label>
</div>

<div class="row">
    <div class="f @error('status') has-err @enderror">
        <label for="status">Status</label>
        <select id="status" name="status" required onchange="const dg = document.getElementById('edit_declined_reason_group'); if (dg) dg.style.display = this.value === 'Declined' ? 'block' : 'none';">
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $inquiry->status ?? 'Waiting') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

@if (isset($declinedReasons))
    <div class="f" id="edit_declined_reason_group" style="{{ old('status', $inquiry->status ?? 'Waiting') === 'Declined' ? '' : 'display: none;' }}">
        <label for="edit_declined_reason">Reason for decline</label>
        <select id="edit_declined_reason" name="declined_reason" onchange="if(this.value && this.value !== 'Other (custom note)') document.getElementById('remarks').value = this.value">
            <option value="">-- Select structured reason --</option>
            @foreach ($declinedReasons as $reason)
                <option value="{{ $reason }}">{{ $reason }}</option>
            @endforeach
        </select>
        <div class="hint">Selecting a structured reason automatically updates the remarks field.</div>
    </div>
@endif

<div class="f @error('remarks') has-err @enderror">
    <label for="remarks">Remarks</label>
    <input id="remarks" name="remarks" type="text" maxlength="255" value="{{ old('remarks', $inquiry->remarks) }}">
    @error('remarks') <div class="err">{{ $message }}</div> @enderror
</div>
