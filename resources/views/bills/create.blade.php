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

            @if ($metered)
                {{-- Step 1: enter the reading and "Show statement" (GET). Step 2: "Save bill" (POST, right). --}}
                <form method="GET" action="{{ route('bills.create') }}" novalidate>
                    <input type="hidden" name="lease" value="{{ $lease->lease_id }}">
                    <div class="row">
                        <div class="f {{ $readingErrors?->has('previous_reading') || $errors->has('previous_reading') ? 'has-err' : '' }}">
                            <label for="previous_reading">Previous reading (kWh)</label>
                            <input id="previous_reading" name="previous_reading" type="number" min="0" step="0.01" inputmode="decimal" value="{{ $input['previous_reading'] }}">
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
                            <label for="current_reading">Current reading (kWh)</label>
                            <input id="current_reading" name="current_reading" type="number" min="0" step="0.01" inputmode="decimal" value="{{ $input['current_reading'] }}" autofocus>
                            @if ($readingErrors?->has('current_reading'))<div class="err">{{ $readingErrors->first('current_reading') }}</div>@endif
                            @error('current_reading')<div class="err">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="f {{ $readingErrors?->has('reading_date') ? 'has-err' : '' }}">
                            <label for="reading_date">Reading date</label>
                            <input id="reading_date" name="reading_date" type="date" max="{{ today()->toDateString() }}" value="{{ $input['reading_date'] }}">
                            @if ($readingErrors?->has('reading_date'))<div class="err">{{ $readingErrors->first('reading_date') }}</div>@endif
                        </div>
                        <div class="f">
                            <label for="source">Source</label>
                            <select id="source" name="source">
                                @foreach ($sources as $source)
                                    <option value="{{ $source }}" @selected($input['source'] === $source)>{{ $source }}</option>
                                @endforeach
                            </select>
                            <div class="hint">Davao Light bill: for a new tenant mid-cycle or a broken meter.</div>
                        </div>
                    </div>
                    <p class="muted">Rate: @peso($statement['kwh_rate']) per kWh (from Settings).</p>
                    <button type="submit" class="btn sec">Show statement</button>
                </form>
            @else
                <p class="muted">This room has no meter: electricity is included in the rent and each appliance is charged
                    @peso($statement['appliance_fee']) a month.</p>
            @endif
        </div>

        <div class="panel">
            <h2>Billing statement <span class="muted">preview</span></h2>
            @include('bills._statement', ['statement' => $statement, 'lease' => $lease])

            @if ($ready)
                <form method="POST" action="{{ route('bills.store') }}" class="form-actions">
                    @csrf
                    <input type="hidden" name="lease_id" value="{{ $lease->lease_id }}">
                    <input type="hidden" name="due_date" value="{{ $statement['due_date']->toDateString() }}">
                    @if ($metered)
                        @foreach (['previous_reading', 'current_reading', 'reading_date', 'source'] as $field)
                            <input type="hidden" name="{{ $field }}" value="{{ $statement['reading'][$field] }}">
                        @endforeach
                    @endif
                    <button type="submit" class="btn">Save bill</button>
                    <a href="{{ route('bills.index') }}" class="btn sec">Cancel</a>
                </form>
            @else
                <p class="muted">Enter the current reading and press "Show statement" to check the bill before saving.</p>
            @endif
        </div>
    </div>
@endsection
