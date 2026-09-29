@extends('layouts.app')

@section('title', 'Final bill: Room '.$lease->room->room_no)

@section('content')
    @foreach (['final', 'concerns'] as $key)
        @error($key) <div class="alert alert-err">{{ $message }}</div> @enderror
    @endforeach

    <div class="grid2">
        <div class="panel">
            <h2>Final bill <span class="muted">{{ $lease->tenant->full_name }}, moved out {{ $moveout->actual_date->format('j M Y') }}</span></h2>
            <p class="muted">Rent ₱0.00 (this month's rent was already charged ahead). Water and appliances at the flat monthly
                rates for {{ $statement['period_start']->format('j M') }} – {{ $statement['period_end']->format('j M Y') }}.</p>

            <form method="GET" action="{{ route('moveouts.final-bill.create', $moveout) }}" novalidate>
                @if ($metered)
                    <div class="row">
                        <div class="f {{ $readingErrors?->has('previous_reading') ? 'has-err' : '' }}">
                            <label for="previous_reading">Previous reading (kWh)</label>
                            <input id="previous_reading" name="previous_reading" type="number" min="0" step="0.01" value="{{ $input['previous_reading'] }}">
                            @if ($lastReading)<div class="hint">Last reading: {{ number_format((float) $lastReading->current_reading, 2) }} on {{ $lastReading->reading_date->format('j M Y') }}.</div>@endif
                            @if ($readingErrors?->has('previous_reading'))<div class="err">{{ $readingErrors->first('previous_reading') }}</div>@endif
                        </div>
                        <div class="f {{ $readingErrors?->has('current_reading') ? 'has-err' : '' }}">
                            <label for="current_reading">Final reading at move-out (kWh)</label>
                            <input id="current_reading" name="current_reading" type="number" min="0" step="0.01" value="{{ $input['current_reading'] }}" autofocus>
                            @if ($readingErrors?->has('current_reading'))<div class="err">{{ $readingErrors->first('current_reading') }}</div>@endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="f"><label for="reading_date">Reading date</label>
                            <input id="reading_date" name="reading_date" type="date" max="{{ today()->toDateString() }}" value="{{ $input['reading_date'] }}"></div>
                        <div class="f"><label for="source">Source</label>
                            <select id="source" name="source">
                                @foreach ($sources as $s)<option value="{{ $s }}" @selected($input['source'] === $s)>{{ $s }}</option>@endforeach
                            </select></div>
                    </div>
                @endif

                @if ($chargeable->isNotEmpty())
                    <fieldset class="resolution-box charge-list">
                        <legend>Other charges: concerns charged to the tenant</legend>
                        @foreach ($chargeable as $c)
                            <label><input type="checkbox" name="concerns[]" value="{{ $c->concern_id }}" @checked(in_array($c->concern_id, $ticked, true))>
                                <span>{{ $c->description }} <span class="sub">{{ $c->category }}</span></span> <b class="money">@peso($c->cost)</b></label>
                        @endforeach
                    </fieldset>
                @endif

                @if ($metered || $chargeable->isNotEmpty())
                    <button type="submit" class="btn sec">Show statement</button>
                @endif
            </form>
        </div>

        <div class="panel">
            <h2>Final billing statement <span class="muted">preview</span></h2>
            @include('bills._statement', ['statement' => $statement, 'lease' => $lease])
            @if ($ready)
                <form method="POST" action="{{ route('moveouts.final-bill.store', $moveout) }}" class="form-actions">
                    @csrf
                    @if ($metered)
                        @foreach (['previous_reading', 'current_reading', 'reading_date', 'source'] as $field)
                            <input type="hidden" name="{{ $field }}" value="{{ $statement['reading'][$field] }}">
                        @endforeach
                    @endif
                    @foreach ($statement['charges'] as $c)<input type="hidden" name="concerns[]" value="{{ $c->concern_id }}">@endforeach
                    <button type="submit" class="btn">Save final bill</button>
                    <a href="{{ route('moveouts.show', $moveout) }}" class="btn sec">Cancel</a>
                </form>
            @else
                <p class="muted">Enter the final meter reading and press "Show statement".</p>
            @endif
        </div>
    </div>
@endsection
