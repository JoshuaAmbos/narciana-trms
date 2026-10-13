@extends('layouts.app')

@section('title', 'Generate bill')

@section('content')
    @error('lease') <div class="alert alert-err">{{ $message }}</div> @enderror

    <div class="grid2">
        <div class="panel">
            <h2>Room {{ $lease->room->room_no }} ({{ $lease->room->room_type }}) <span class="muted">{{ $lease->tenant->full_name }}</span></h2>
            <dl class="facts">
                <div><dt>Due date</dt><dd>{{ $statement['due_date']->format('j M Y') }}</dd></div>
                <div><dt>Utilities period</dt><dd>{{ $statement['period_start']->format('j M') }} – {{ $statement['period_end']->format('j M Y') }}</dd></div>
                <div><dt>Occupants in the period</dt><dd>{{ $statement['occupants'] }}</dd></div>
                <div><dt>{{ $metered ? 'Electric meter' : 'Appliances in the period' }}</dt>
                    <dd>{{ $metered ? 'Own meter (billed per kWh)' : $statement['appliances'] }}</dd></div>
            </dl>

            {{-- Step 1: reading and/or other charges, with real-time meter calculation and statement preview. --}}
            <form method="GET" action="{{ route('bills.create') }}" id="billing-create-form" novalidate>
                <input type="hidden" name="lease" value="{{ $lease->lease_id }}">
            @if ($metered)
                    <div class="row">
                        <div class="f {{ $readingErrors?->has('previous_reading') || $errors->has('previous_reading') ? 'has-err' : '' }}">
                            <label for="previous_reading">Previous reading (kWh) <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <input id="previous_reading" name="previous_reading" type="number" min="0" step="0.01" inputmode="decimal"
                                   value="{{ $input['previous_reading'] }}" oninput="updateLiveMeterCalc()" required>
                            <div class="hint">
                                @if ($lastReading)
                                    Last reading: {{ number_format((float) $lastReading->current_reading, 2) }} on {{ $lastReading->reading_date->format('j M Y') }}.
                                @else
                                    No earlier reading for this room: type the reading at move-in.
                                @endif
                            </div>
                            @if ($readingErrors?->has('previous_reading'))<div class="err">{{ $readingErrors->first('previous_reading') }}</div>@endif
                        </div>
                        <div class="f {{ $readingErrors?->has('current_reading') || $errors->has('current_reading') ? 'has-err' : '' }}">
                            <label for="current_reading">Current reading (kWh) <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <input id="current_reading" name="current_reading" type="number" min="0" step="0.01" inputmode="decimal"
                                   value="{{ $input['current_reading'] }}" autofocus oninput="updateLiveMeterCalc()" required>
                            @if ($readingErrors?->has('current_reading'))<div class="err">{{ $readingErrors->first('current_reading') }}</div>@endif
                            @error('current_reading')<div class="err">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="f {{ $readingErrors?->has('reading_date') ? 'has-err' : '' }}">
                            <label for="reading_date">Reading date <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <input id="reading_date" name="reading_date" type="date" max="{{ today()->toDateString() }}"
                                   value="{{ $input['reading_date'] }}" onchange="syncPostInputs()" required>
                            @if ($readingErrors?->has('reading_date'))<div class="err">{{ $readingErrors->first('reading_date') }}</div>@endif
                        </div>
                        <div class="f">
                            <label for="source">Source <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <select id="source" name="source" onchange="syncPostInputs()" required>
                                @foreach ($sources as $source)
                                    <option value="{{ $source }}" @selected($input['source'] === $source)>{{ $source }}</option>
                                @endforeach
                            </select>
                            <div class="hint">Davao Light bill: for a new tenant mid-cycle or a broken meter.</div>
                        </div>
                    </div>

                    {{-- Live Meter Calculation Box in Billing --}}
                    <div class="meter-calc-card" id="meter-calc-box">
                        <h3>⚡ Meter Calculation (Billing)</h3>
                        <div class="calc-grid">
                            <div><span class="muted">Previous:</span> <b id="disp_prev">{{ number_format((float) ($input['previous_reading'] ?? 0), 2) }}</b> kWh</div>
                            <div><span class="muted">Current:</span> <b id="disp_curr">{{ $input['current_reading'] ? number_format((float) $input['current_reading'], 2) : '0.00' }}</b> kWh</div>
                            <div><span class="muted">Consumption:</span> <b id="disp_kwh" class="amber">{{ number_format($statement['kwh_used'] ?? 0, 2) }}</b> kWh</div>
                            <div><span class="muted">Rate:</span> <span>@peso($statement['kwh_rate']) / kWh</span></div>
                            <div class="calc-formula-row">
                                <span class="muted">Electric bill:</span>
                                <span id="disp_formula">{{ number_format($statement['kwh_used'] ?? 0, 2) }} kWh × ₱{{ number_format((float) $statement['kwh_rate'], 2) }}</span> =
                                <b class="money red" id="disp_amount">@peso($statement['amounts']['electricity_amount'])</b>
                            </div>
                        </div>
                        <div id="disp_warning" class="err" style="display: none; margin-top: 8px;">The current reading cannot be lower than the previous reading.</div>
                    </div>
            @else
                <p class="muted">This room has no meter: electricity is included in the rent and each appliance is charged
                    @peso($statement['appliance_fee']) a month.</p>
            @endif

            @if ($chargeable->isNotEmpty())
                <fieldset class="resolution-box charge-list">
                    <legend>Other charges: concerns charged to the tenant</legend>
                    @error('concerns') <div class="err">{{ $message }}</div> @enderror
                    @foreach ($chargeable as $c)
                        <label>
                            <input type="checkbox" name="concerns[]" value="{{ $c->concern_id }}" @checked(in_array($c->concern_id, $ticked, true)) onchange="updateLiveMeterCalc()">
                            <span>{{ $c->description }} <span class="sub">{{ $c->category }} · resolved {{ $c->resolved_at->format('j M Y') }}</span></span>
                            <b class="money">@peso($c->cost)</b>
                        </label>
                    @endforeach
                    <p class="muted">Tick the ones to add to this bill, then press "Show statement". Nothing is added automatically.</p>
                </fieldset>
            @endif

            @if ($metered || $chargeable->isNotEmpty())
                <button type="submit" class="btn sec" id="show-statement-btn">Show statement</button>
            @endif
            </form>
        </div>

        <div class="panel">
            <h2>Billing statement <span class="muted">preview</span></h2>
            <div id="statement-preview-wrapper">
                @include('bills._statement', ['statement' => $statement, 'lease' => $lease])
            </div>

            @if ($ready)
                <form method="POST" action="{{ route('bills.store') }}" class="form-actions" id="post-save-form">
                    @csrf
                    <input type="hidden" name="lease_id" value="{{ $lease->lease_id }}">
                    <input type="hidden" name="due_date" value="{{ $statement['due_date']->toDateString() }}">
                    @if ($metered)
                        <input type="hidden" name="previous_reading" id="post_previous_reading" value="{{ $statement['reading']['previous_reading'] ?? $input['previous_reading'] }}">
                        <input type="hidden" name="current_reading" id="post_current_reading" value="{{ $statement['reading']['current_reading'] ?? $input['current_reading'] }}">
                        <input type="hidden" name="reading_date" id="post_reading_date" value="{{ $statement['reading']['reading_date'] ?? $input['reading_date'] }}">
                        <input type="hidden" name="source" id="post_source" value="{{ $statement['reading']['source'] ?? $input['source'] }}">
                    @endif
                    @foreach ($statement['charges'] as $c)
                        <input type="hidden" name="concerns[]" value="{{ $c->concern_id }}">
                    @endforeach
                    <button type="submit" class="btn" id="save-bill-btn">Save bill</button>
                    <a href="{{ route('bills.index') }}" class="btn sec">Cancel</a>
                </form>
            @else
                <div id="save-form-wrapper" style="display: none;">
                    <form method="POST" action="{{ route('bills.store') }}" class="form-actions" id="post-save-form">
                        @csrf
                        <input type="hidden" name="lease_id" value="{{ $lease->lease_id }}">
                        <input type="hidden" name="due_date" value="{{ $statement['due_date']->toDateString() }}">
                        @if ($metered)
                            <input type="hidden" name="previous_reading" id="post_previous_reading" value="{{ $input['previous_reading'] }}">
                            <input type="hidden" name="current_reading" id="post_current_reading" value="{{ $input['current_reading'] }}">
                            <input type="hidden" name="reading_date" id="post_reading_date" value="{{ $input['reading_date'] }}">
                            <input type="hidden" name="source" id="post_source" value="{{ $input['source'] }}">
                        @endif
                        @foreach ($statement['charges'] as $c)
                            <input type="hidden" name="concerns[]" value="{{ $c->concern_id }}">
                        @endforeach
                        <button type="submit" class="btn" id="save-bill-btn">Save bill</button>
                        <a href="{{ route('bills.index') }}" class="btn sec">Cancel</a>
                    </form>
                </div>
                <p class="muted" id="reading-needed-prompt">Enter the current reading and press "Show statement" to check the bill before saving.</p>
            @endif
        </div>
    </div>

    @if ($metered)
    <script>
        const kwhRate = {{ (float) $statement['kwh_rate'] }};
        const rentAmount = {{ (float) $statement['amounts']['rent_amount'] }};
        const waterAmount = {{ (float) $statement['amounts']['water_amount'] }};
        const applianceAmount = {{ (float) $statement['amounts']['appliance_amount'] }};
        const concernAmount = {{ (float) $statement['amounts']['other_charges'] }};

        function syncPostInputs() {
            const prevInput = document.getElementById('previous_reading');
            const currInput = document.getElementById('current_reading');
            const dateInput = document.getElementById('reading_date');
            const srcInput = document.getElementById('source');

            const postPrev = document.getElementById('post_previous_reading');
            const postCurr = document.getElementById('post_current_reading');
            const postDate = document.getElementById('post_reading_date');
            const postSrc = document.getElementById('post_source');

            if (postPrev && prevInput) postPrev.value = prevInput.value;
            if (postCurr && currInput) postCurr.value = currInput.value;
            if (postDate && dateInput) postDate.value = dateInput.value;
            if (postSrc && srcInput) postSrc.value = srcInput.value;
        }

        function updateLiveMeterCalc() {
            syncPostInputs();
            const prevVal = parseFloat(document.getElementById('previous_reading')?.value) || 0;
            const currInput = document.getElementById('current_reading');
            const currVal = parseFloat(currInput?.value);
            const warningEl = document.getElementById('disp_warning');
            const saveFormWrapper = document.getElementById('save-form-wrapper');
            const readingPrompt = document.getElementById('reading-needed-prompt');
            const saveBillBtn = document.getElementById('save-bill-btn');

            document.getElementById('disp_prev').textContent = prevVal.toFixed(2);

            if (isNaN(currVal)) {
                document.getElementById('disp_curr').textContent = '0.00';
                document.getElementById('disp_kwh').textContent = '0.00';
                document.getElementById('disp_formula').textContent = '0.00 kWh × ₱' + kwhRate.toFixed(2);
                document.getElementById('disp_amount').textContent = '₱0.00';
                if (warningEl) warningEl.style.display = 'none';
                if (saveFormWrapper) saveFormWrapper.style.display = 'none';
                if (readingPrompt) readingPrompt.style.display = 'block';
                return;
            }

            document.getElementById('disp_curr').textContent = currVal.toFixed(2);

            if (currVal < prevVal) {
                if (warningEl) warningEl.style.display = 'block';
                if (currInput) {
                    currInput.style.borderColor = '#dc2626';
                    currInput.closest('.f')?.classList.add('has-err');
                }
                if (saveBillBtn) saveBillBtn.disabled = true;
            } else {
                if (warningEl) warningEl.style.display = 'none';
                if (currInput) {
                    currInput.style.borderColor = '';
                    currInput.closest('.f')?.classList.remove('has-err');
                }
                if (saveBillBtn) saveBillBtn.disabled = false;
            }

            const kwh = Math.max(0, currVal - prevVal);
            const elecAmt = kwh * kwhRate;
            const total = rentAmount + waterAmount + applianceAmount + concernAmount + elecAmt;

            document.getElementById('disp_kwh').textContent = kwh.toFixed(2);
            document.getElementById('disp_formula').textContent = kwh.toFixed(2) + ' kWh × ₱' + kwhRate.toFixed(2);
            document.getElementById('disp_amount').textContent = '₱' + elecAmt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (saveFormWrapper) saveFormWrapper.style.display = 'block';
            if (readingPrompt) readingPrompt.style.display = 'none';
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('current_reading')?.value) {
                updateLiveMeterCalc();
            }
        });
    </script>
    @endif
@endsection
