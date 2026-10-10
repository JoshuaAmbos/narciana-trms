@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    @include('settings._tabs')

    <div class="panel form-panel">
        <h2>Rates and rules</h2>
        <p class="muted">
            Bills already generated keep their amounts. New values apply only to bills generated after you save.
            The late penalty is worked out when a bill is paid, using the rules in force on the payment date.
        </p>

        <form method="POST" action="{{ route('settings.update') }}" novalidate>
            @csrf @method('PUT')

            <h3 class="sub-head">Standard room baseline rates</h3>
            <div class="row">
                <div class="f @error('standard_basic_rent') has-err @enderror">
                    <label for="standard_basic_rent">Standard Basic room monthly rate (₱)</label>
                    <input id="standard_basic_rent" name="standard_basic_rent" type="number" step="0.01" min="0" inputmode="decimal"
                           value="{{ old('standard_basic_rent', $setting->standard_basic_rent) }}">
                    <div class="hint">Baseline rent for new Basic rooms (default ₱3,000.00).</div>
                    @error('standard_basic_rent') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('standard_aircon_rent') has-err @enderror">
                    <label for="standard_aircon_rent">Standard Aircon room monthly rate (₱)</label>
                    <input id="standard_aircon_rent" name="standard_aircon_rent" type="number" step="0.01" min="0" inputmode="decimal"
                           value="{{ old('standard_aircon_rent', $setting->standard_aircon_rent) }}">
                    <div class="hint">Baseline rent for new Aircon rooms (default ₱3,600.00).</div>
                    @error('standard_aircon_rent') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('default_room_capacity') has-err @enderror">
                    <label for="default_room_capacity">Default room capacity ceiling</label>
                    <input id="default_room_capacity" name="default_room_capacity" type="number" step="1" min="1" max="10" inputmode="numeric"
                           value="{{ old('default_room_capacity', $setting->default_room_capacity ?? 2) }}">
                    <div class="hint">Global ceiling for room occupants (default 2). Newly added rooms will default to this capacity.</div>
                    @error('default_room_capacity') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>

            <h3 class="sub-head">Monthly charges</h3>
            <div class="row">
                @foreach (['water_rate' => ['Water rate per occupant (₱)', 'Per occupant per month.'],
                           'kwh_rate' => ['Electricity rate per kWh (₱)', 'Aircon (metered) rooms.'],
                           'appliance_fee' => ['Appliance fee (₱)', 'Per extra appliance per month, basic rooms.']] as $field => [$label, $hint])
                    <div class="f @error($field) has-err @enderror">
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number" step="0.01" min="0" inputmode="decimal"
                               value="{{ old($field, $setting->{$field}) }}">
                        <div class="hint">{{ $hint }}</div>
                        @error($field) <div class="err">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>

            <h3 class="sub-head">Late payment penalty</h3>
            <div class="row">
                <div class="f @error('penalty_basis') has-err @enderror">
                    <label for="penalty_basis">Penalty basis</label>
                    <select id="penalty_basis" name="penalty_basis">
                        @foreach ($bases as $basis)
                            <option value="{{ $basis }}" @selected(old('penalty_basis', $setting->penalty_basis) === $basis)>{{ $basis }}</option>
                        @endforeach
                    </select>
                    <div class="hint">Per day of rent: rent × rate % × days late. Flat %: bill total × rate %, once.</div>
                    @error('penalty_basis') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('penalty_rate') has-err @enderror">
                    <label for="penalty_rate">Penalty rate (%)</label>
                    <input id="penalty_rate" name="penalty_rate" type="number" step="0.01" min="0" max="100" inputmode="decimal"
                           value="{{ old('penalty_rate', $setting->penalty_rate) }}">
                    @error('penalty_rate') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div class="f @error('grace_days') has-err @enderror">
                    <label for="grace_days">Grace period (days)</label>
                    <input id="grace_days" name="grace_days" type="number" step="1" min="0" max="31" inputmode="numeric"
                           value="{{ old('grace_days', $setting->grace_days) }}">
                    <div class="hint">Days after the due date before a bill is late.</div>
                    @error('grace_days') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>

            <h3 class="sub-head">Move-out</h3>
            <div class="row">
                <div class="f @error('refund_days') has-err @enderror">
                    <label for="refund_days">Refund deposit within (days)</label>
                    <input id="refund_days" name="refund_days" type="number" step="1" min="0" max="60" inputmode="numeric"
                           value="{{ old('refund_days', $setting->refund_days) }}">
                    <div class="hint">Days after the actual move-out date.</div>
                    @error('refund_days') <div class="err">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save settings</button>
            </div>
        </form>
        <p class="muted small">
            Last changed by {{ $setting->updatedBy?->full_name ?? '—' }}{{ $setting->updatedBy ? ' ('.$setting->updatedBy->role.')' : '' }}.
            Every change is recorded in the <a href="{{ route('audit-log.index', ['action' => 'Settings changed']) }}">audit log</a>.
        </p>
    </div>

    <div class="panel form-panel">
        <h2>Fixed rules <span class="muted">set by the MOA and the system, not changed here</span></h2>
        <dl class="facts">
            @foreach ($fixedRules as [$label, $value])
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    </div>
@endsection
