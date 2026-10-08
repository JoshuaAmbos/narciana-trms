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
        <select id="due_day" name="due_day" required>
            @foreach ([10, 15, 20, 25, 30] as $staggeredDay)
                <option value="{{ $staggeredDay }}" @selected((int) old('due_day', $lease->due_day) === $staggeredDay)>
                    {{ $staggeredDay }}th
                </option>
            @endforeach
        </select>
        @error('due_day') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="f">
        <label>Contractual minimum stay</label>
        @php($isAirconLease = (bool) (isset($lease->room) && ($lease->room->room_type === 'Aircon' || $lease->room->is_metered)))
        @php($initialStay = (int) old('min_stay_months', $lease->min_stay_months ?: ($isAirconLease ? 6 : 4)))
        <input type="hidden" name="min_stay_months" id="min_stay_months" value="{{ $initialStay }}">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 7px 12px; font-size: 13px; color: #334155; display: flex; align-items: center; gap: 8px;">
            <span id="min_stay_text" style="font-weight: 600; color: #1e293b;">{{ $initialStay }} Months</span>
            <span class="muted" style="font-size: 12px;">(Mandatory lock-in period)</span>
        </div>
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
        <input id="advance_amount" name="advance_amount" type="number" min="0" step="0.01" inputmode="decimal" readonly
               style="background-color: #f8fafc; cursor: not-allowed;"
               value="{{ old('advance_amount', $lease->advance_amount ?: $lease->monthly_rent) }}">
        @error('advance_amount') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>

<div class="f" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
    <label style="margin-bottom: 0;">Deposit breakdown (₱)</label>
    <button type="button" class="btn sec" id="btn-split-deposits" style="padding: 2px 8px; font-size: 11px;">
        Auto-split 1-Month Deposit
    </button>
</div>
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
                    ? '(Must equal one month\'s rent of ' + peso(r) + ' per MOA Clause 6)' : '';
            }
            parts.forEach(function (p) { p.addEventListener('input', refresh); });
            rent.addEventListener('input', function () {
                var adv = document.getElementById('advance_amount');
                if (adv) adv.value = rent.value;
                refresh();
            });

            function autoSplitDeposits() {
                var r = parseFloat(rent.value) || 0;
                if (r <= 0) return;
                var maint = Math.round(r * 0.30 * 100) / 100;
                var dam = Math.round(r * 0.40 * 100) / 100;
                var bills = Math.round(r * 0.20 * 100) / 100;
                var keys = Math.round((r - maint - dam - bills) * 100) / 100;
                var mInput = document.getElementById('deposit_maintenance');
                var dInput = document.getElementById('deposit_damage');
                var bInput = document.getElementById('deposit_bills');
                var kInput = document.getElementById('deposit_keys');
                if (mInput) mInput.value = maint.toFixed(2);
                if (dInput) dInput.value = dam.toFixed(2);
                if (bInput) bInput.value = bills.toFixed(2);
                if (kInput) kInput.value = keys.toFixed(2);
                refresh();
            }
            var splitBtn = document.getElementById('btn-split-deposits');
            if (splitBtn) splitBtn.addEventListener('click', autoSplitDeposits);

            rent.addEventListener('change', function () {
                var total = 0;
                parts.forEach(function (p) { total += parseFloat(p.value) || 0; });
                if (total === 0) {
                    autoSplitDeposits();
                }
            });

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
            if (dueDay) dueDay.addEventListener('change', function () { dueTouched = true; });
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
