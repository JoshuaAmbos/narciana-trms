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
        <input type="checkbox" name="via_representative" value="1"
               @checked(old('via_representative', $inquiry->via_representative))>
        Inquiry made by a representative (for someone else)
    </label>
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
        <select id="status" name="status" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $inquiry->status ?? 'Waiting') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('room_id') has-err @enderror">
        <label for="room_id">Room shown or rented</label>
        <select id="room_id" name="room_id">
            <option value="">None</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->room_id }}" @selected((string) old('room_id', $inquiry->room_id) === (string) $room->room_id)>
                    Room {{ $room->room_no }} ({{ $room->room_type }}, max {{ $room->max_occupants }}, {{ $room->status }}){{ isset($holds[$room->room_id]) ? ' – rented to '.$holds[$room->room_id]->prospect_name.', MOA pending' : '' }}
                </option>
            @endforeach
        </select>
        <div class="hint">Required when the outcome is Rented. Only a Vacant or Vacating room that nobody else has rented can be chosen.</div>
        @error('room_id') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="f @error('remarks') has-err @enderror">
    <label for="remarks">Remarks</label>
    <input id="remarks" name="remarks" type="text" maxlength="255" value="{{ old('remarks', $inquiry->remarks) }}">
    @error('remarks') <div class="err">{{ $message }}</div> @enderror
</div>
