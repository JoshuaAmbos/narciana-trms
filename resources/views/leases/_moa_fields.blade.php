{{-- MOA dates, rent, advance and the 4 deposit parts (new MOA and edit). Needs: $lease --}}
<div class="row">
    <div class="f @error('moa_date') has-err @enderror">
        <label for="moa_date">MOA signed on</label>
        <input id="moa_date" name="moa_date" type="date" value="{{ old('moa_date', $lease->moa_date?->toDateString()) }}">
        @error('moa_date') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('move_in_date') has-err @enderror">
        <label for="move_in_date">Move-in date</label>
        <input id="move_in_date" name="move_in_date" type="date" value="{{ old('move_in_date', $lease->move_in_date?->toDateString()) }}">
        @error('move_in_date') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('due_day') has-err @enderror">
        <label for="due_day">Contractual due day</label>
        <div style="display: flex; gap: 4px; margin-bottom: 6px; flex-wrap: wrap;">
            @foreach ([10, 15, 20, 25, 30] as $staggeredDay)
                <button type="button" class="btn sec" style="padding: 2px 8px; font-size: 11px;"
                        onclick="document.getElementById('due_day').value = {{ $staggeredDay }}; document.getElementById('due_day').dispatchEvent(new Event('input'));">
                    {{ $staggeredDay }}th
                </button>
            @endforeach
        </div>
        <input id="due_day" name="due_day" type="number" min="1" max="31" inputmode="numeric" value="{{ old('due_day', $lease->due_day) }}">
        @error('due_day') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('min_stay_months') has-err @enderror">
        <label for="min_stay_months">Minimum stay (months)</label>
        <input id="min_stay_months" name="min_stay_months" type="number" min="4" max="60" inputmode="numeric" value="{{ old('min_stay_months', $lease->min_stay_months ?: 4) }}">
        <div class="hint">Contractual minimum stay is 4 months.</div>
        @error('min_stay_months') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="f @error('monthly_rent') has-err @enderror">
        <label for="monthly_rent">Monthly rent (₱)</label>
        <input id="monthly_rent" name="monthly_rent" type="number" min="0.01" step="0.01" inputmode="decimal" value="{{ old('monthly_rent', $lease->monthly_rent) }}">
        @error('monthly_rent') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f @error('advance_amount') has-err @enderror">
        <label for="advance_amount">One-month advance paid (₱)</label>
        <input id="advance_amount" name="advance_amount" type="number" min="0" step="0.01" inputmode="decimal" value="{{ old('advance_amount', $lease->advance_amount) }}">
        <div class="hint">Left blank = same as the monthly rent.</div>
        @error('advance_amount') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="f"><label>Deposit breakdown (₱) — the MOA deposit is one month's rent</label></div>
<div class="row deposits">
    @foreach ([
        'deposit_maintenance' => 'Maintenance (non-refundable)',
        'deposit_damage' => 'Damage',
        'deposit_bills' => 'Bill expenses',
        'deposit_keys' => 'Door knob and keys',
    ] as $field => $label)
        <div class="f @error($field) has-err @enderror">
            <label for="{{ $field }}">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" step="0.01" inputmode="decimal" class="deposit-part"
                   value="{{ old($field, $lease->$field) }}">
            @error($field) <div class="err">{{ $message }}</div> @enderror
        </div>
    @endforeach
</div>
<p class="deposit-total">Total deposit: <b id="deposit-total">₱0.00</b> <span id="deposit-note" class="amber"></span></p>

@push('scripts')
    <script>
        (function () {
            var rent = document.getElementById('monthly_rent');
            var parts = document.querySelectorAll('.deposit-part');
            var totalEl = document.getElementById('deposit-total');
            var noteEl = document.getElementById('deposit-note');
            var peso = function (n) { return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

            function refresh() {
                var total = 0;
                parts.forEach(function (p) { total += parseFloat(p.value) || 0; });
                totalEl.textContent = peso(total);
                var r = parseFloat(rent.value) || 0;
                noteEl.textContent = (r > 0 && Math.abs(total - r) >= 0.005)
                    ? '(one month\'s rent is ' + peso(r) + ' — you can still save)' : '';
            }
            parts.forEach(function (p) { p.addEventListener('input', refresh); });
            rent.addEventListener('input', refresh);

            // Due day snaps to closest staggered cycle day (10, 15, 20, 25, 30) following the move-in day.
            var moveIn = document.getElementById('move_in_date');
            var dueDay = document.getElementById('due_day');
            var dueTouched = false;
            var staggeredDays = [10, 15, 20, 25, 30];
            function snapDueDay(day) {
                return staggeredDays.reduce(function (prev, curr) {
                    return Math.abs(curr - day) < Math.abs(prev - day) ? curr : prev;
                });
            }
            dueDay.addEventListener('input', function () { dueTouched = true; });
            moveIn.addEventListener('change', function () {
                if (!dueTouched && moveIn.value) {
                    var rawDay = parseInt(moveIn.value.slice(8, 10), 10);
                    dueDay.value = snapDueDay(rawDay);
                }
            });

            refresh();
        })();
    </script>
@endpush
