{{-- Shared log/update form. Needs: $concern, $rooms, $currentLease, $users, $channels, $categories (name => description),
     $sharedCategory, $priorities, $statuses, $canSetMoney --}}
@csrf
@php($billed = $concern->exists && $concern->isBilled())

<div class="row">
    <div class="f @error('category') has-err @enderror">
        <label for="category">Category</label>
        @php($categoryValue = old('category', $concern->category ?? array_key_first($categories)))
        <select id="category" name="category" aria-describedby="category-help">
            @foreach ($categories as $name => $help)
                <option value="{{ $name }}" data-help="{{ $help }}" @selected($categoryValue === $name)>{{ $name }}</option>
            @endforeach
        </select>
        <div class="hint category-help" id="category-help">{{ $categories[$categoryValue] ?? '' }}</div>
        @error('category') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('room_id') has-err @enderror">
        <label for="room_id">Room</label>
        {{-- "shared" = no room (a shared area such as the CR or hallway; any category, decision 18). --}}
        @php($roomValue = (string) old('room_id', $concern->exists && ! $concern->room_id ? 'shared' : $concern->room_id))
        <select id="room_id" name="room_id">
            {{-- Starts on a prompt, so "no room" is always a deliberate choice. --}}
            <option value="" data-lease="" disabled @selected($roomValue === '')>Choose a room…</option>
            @foreach ($rooms as $r)
                <option value="{{ $r['room_id'] }}" data-lease="{{ $r['lease_id'] }}" data-tenant="{{ $r['tenant'] }}"
                        @selected($roomValue === (string) $r['room_id'])>{{ $r['label'] }}{{ $r['tenant'] ? ' — '.$r['tenant'] : ' — vacant' }}</option>
            @endforeach
            <option value="shared" data-lease="" @selected($roomValue === 'shared')>No room (shared area)</option>
        </select>
        @error('room_id') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('lease_id') has-err @enderror">
        <label for="lease_id">Tenant</label>
        <select id="lease_id" name="lease_id" @disabled($billed)>
            <option value="">None</option>
            @foreach ($rooms->whereNotNull('lease_id') as $r)
                <option value="{{ $r['lease_id'] }}" @selected((string) old('lease_id', $concern->lease_id) === (string) $r['lease_id'])>{{ $r['tenant'] }} ({{ $r['label'] }})</option>
            @endforeach
            @if ($currentLease && ! $rooms->contains('lease_id', $currentLease->lease_id))
                <option value="{{ $currentLease->lease_id }}" selected>{{ $currentLease->tenant->full_name }} (lease ended)</option>
            @endif
        </select>
        @if ($billed)<input type="hidden" name="lease_id" value="{{ $concern->lease_id }}">@endif
        <div class="hint">Filled in from the room. Needed to charge the tenant.</div>
        @error('lease_id') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="f @error('reported_at') has-err @enderror">
        <label for="reported_at">Reported on</label>
        <input id="reported_at" name="reported_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}"
               value="{{ old('reported_at', $concern->reported_at?->format('Y-m-d\TH:i')) }}">
        @error('reported_at') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f">
        <label for="channel">Reported by</label>
        <select id="channel" name="channel">
            @foreach ($channels as $c)
                <option value="{{ $c }}" @selected(old('channel', $concern->channel) === $c)>{{ $c }}</option>
            @endforeach
        </select>
    </div>
    <div class="f">
        <label for="priority">Priority</label>
        <select id="priority" name="priority">
            @foreach ($priorities as $p)
                <option value="{{ $p }}" @selected(old('priority', $concern->priority) === $p)>{{ $p }}</option>
            @endforeach
        </select>
    </div>
    <div class="f @error('assigned_to') has-err @enderror">
        <label for="assigned_to">Assigned to</label>
        <select id="assigned_to" name="assigned_to">
            @foreach ($users as $u)
                <option value="{{ $u->user_id }}" @selected((string) old('assigned_to', $concern->assigned_to) === (string) $u->user_id)>{{ $u->role }} ({{ $u->full_name }})</option>
            @endforeach
        </select>
        @error('assigned_to') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="f @error('description') has-err @enderror">
    <label for="description">Concern</label>
    <textarea id="description" name="description" rows="3" maxlength="500" placeholder="What is the problem? For a shared area, say where (e.g. 2nd-floor CR).">{{ old('description', $concern->description) }}</textarea>
    @error('description') <div class="err">{{ $message }}</div> @enderror
</div>

@include('concerns._photo_field', ['field' => 'photo', 'kind' => 'reported', 'column' => 'photo_path',
    'label' => 'Photo of the problem (optional)', 'hint' => 'Proof of the problem when it was reported.'])

<fieldset class="resolution-box">
    <legend>Progress</legend>
    <div class="row">
        <div class="f">
            <label for="status">Status</label>
            <select id="status" name="status" @disabled($billed)>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(old('status', $concern->status) === $s)>{{ $s }}</option>
                @endforeach
            </select>
            @if ($billed)<input type="hidden" name="status" value="{{ $concern->status }}">@endif
        </div>
        <div class="f @error('resolved_at') has-err @enderror">
            <label for="resolved_at">Resolved on</label>
            <input id="resolved_at" name="resolved_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}"
                   value="{{ old('resolved_at', $concern->resolved_at?->format('Y-m-d\TH:i')) }}">
            <div class="hint">Left blank = now, when the status is Resolved.</div>
            @error('resolved_at') <div class="err">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="f @error('resolution') has-err @enderror">
        <label for="resolution">How it was resolved</label>
        <textarea id="resolution" name="resolution" rows="2" maxlength="500">{{ old('resolution', $concern->resolution) }}</textarea>
        @error('resolution') <div class="err">{{ $message }}</div> @enderror
    </div>

    @include('concerns._photo_field', ['field' => 'resolution_photo', 'kind' => 'resolved', 'column' => 'resolution_photo_path',
        'label' => 'Photo after the fix (optional)', 'hint' => 'Proof it was fixed. Add it when you mark the concern Resolved.'])

    <div class="row">
        <div class="f @error('cost') has-err @enderror">
            <label for="cost">Cost of repair (₱)</label>
            <input id="cost" name="cost" type="number" min="0" step="0.01" inputmode="decimal" value="{{ old('cost', $concern->cost) }}"
                   @disabled(! $canSetMoney || $billed)>
            @error('cost') <div class="err">{{ $message }}</div> @enderror
        </div>
        <div class="f check @error('charged_to_tenant') has-err @enderror">
            <label><input type="checkbox" name="charged_to_tenant" value="1" @checked(old('charged_to_tenant', $concern->charged_to_tenant))
                          @disabled(! $canSetMoney || $billed)> Charge the cost to the tenant</label>
            @error('charged_to_tenant') <div class="err">{{ $message }}</div> @enderror
        </div>
    </div>
    @unless ($canSetMoney)
        <p class="muted">Only the Owner or Manager can set the cost and whether it is charged to the tenant.</p>
    @endunless
    @if ($billed)
        <p class="muted">Already charged on the bill due {{ $concern->bill->due_date->format('j M Y') }}: status, cost and charge can no longer be changed.</p>
        <input type="hidden" name="cost" value="{{ $concern->cost }}">
        <input type="hidden" name="charged_to_tenant" value="{{ $concern->charged_to_tenant ? 1 : 0 }}">
    @endif
</fieldset>

@push('scripts')
    <script>
        // Picking a room fills in its current tenant (or none for a vacant room / shared area).
        var roomSelect = document.getElementById('room_id');
        roomSelect.addEventListener('change', function () {
            var lease = this.options[this.selectedIndex].dataset.lease || '';
            var select = document.getElementById('lease_id');
            if (!select.disabled) { select.value = lease; }
        });

        // Show what the chosen category covers; Cleanliness is always a shared area.
        document.getElementById('category').addEventListener('change', function () {
            document.getElementById('category-help').textContent = this.options[this.selectedIndex].dataset.help || '';
            if (this.value === @json($sharedCategory) && !roomSelect.disabled) {
                roomSelect.value = 'shared';
                roomSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
@endpush
