{{-- Intake modal form: staff capture general prospect preferences without pre-assigning a room. --}}
<div id="log-inquiry-modal" class="modal-backdrop" @if(!$errors->any()) hidden @endif>
    <div class="modal-box">
        <h2 style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
            <span>Log an inquiry</span>
            <button type="button" class="modal-close-btn" onclick="closeLogInquiryModal()">&times;</button>
        </h2>
        <form method="POST" action="{{ route('inquiries.store') }}" novalidate>
            @csrf
            <div class="row">
                <div class="f @error('prospect_name') has-err @enderror">
                    <label for="prospect_name">Name <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                    <input id="prospect_name" name="prospect_name" type="text" maxlength="255" required
                           value="{{ old('prospect_name') }}">
                    @error('prospect_name') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('contact_no') has-err @enderror">
                    <label for="contact_no">Contact number <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                    <input id="contact_no" name="contact_no" type="tel" maxlength="15" placeholder="09XXXXXXXXX" required
                           value="{{ old('contact_no') }}">
                    @error('contact_no') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row">
                <div class="f @error('preferred_type') has-err @enderror">
                    <label for="preferred_type">Room type wanted <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                    <select id="preferred_type" name="preferred_type" required>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('preferred_type', 'Any') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('preferred_type') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('no_of_occupants') has-err @enderror">
                    <label for="no_of_occupants">Occupants <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                    <input id="no_of_occupants" name="no_of_occupants" type="number" min="1" max="2" inputmode="numeric" required
                           value="{{ old('no_of_occupants', 1) }}">
                    @error('no_of_occupants') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('channel') has-err @enderror">
                    <label for="channel">Channel <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                    <select id="channel" name="channel" required>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel }}" @selected(old('channel', 'Walk-in') === $channel)>{{ $channel }}</option>
                        @endforeach
                    </select>
                    @error('channel') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('inquiry_date') has-err @enderror">
                    <label for="inquiry_date">Date <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                    <input id="inquiry_date" name="inquiry_date" type="date" max="{{ today()->toDateString() }}" required
                           value="{{ old('inquiry_date', today()->toDateString()) }}">
                    @error('inquiry_date') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="f check">
                <label>
                    <input type="checkbox" name="wants_contact" value="1" @checked(old('wants_contact'))>
                    Wants to be contacted when a room is available
                </label>
            </div>

            <div class="f @error('remarks') has-err @enderror">
                <label for="remarks">Remarks <span class="text-muted" style="font-weight: normal; font-size: 0.85em;">(optional)</span></label>
                <input id="remarks" name="remarks" type="text" maxlength="255" value="{{ old('remarks') }}"
                       placeholder="e.g. Viewing rooms, interested in 2nd floor, representative details, notes...">
                @error('remarks') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div class="form-actions" style="margin-top: 14px; display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn sec" onclick="closeLogInquiryModal()">Cancel</button>
                <button type="submit" class="btn">Save inquiry</button>
            </div>
        </form>
    </div>
</div>
