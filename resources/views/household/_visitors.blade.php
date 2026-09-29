{{-- Visitors panel with the MOA two-day rule. Needs: $household (from HouseholdService::summary) --}}
@php($bag = $errors->getBag('visitor'))
<div class="panel" id="visitors">
    <h2>Visitors <span class="muted">MOA: up to 2 days</span></h2>

    @if ($household['visitors']->isEmpty())
        <p class="muted">No visitors logged.</p>
    @else
        <div class="table-wrap">
            <table>
                <tr><th>Visitor</th><th>Arrived</th><th>Left</th><th>Stay</th><th></th></tr>
                @foreach ($household['visitors'] as $row)
                    @php($v = $row['visitor'])
                    <tr>
                        <td>{{ $v->visitor_name }}<div class="sub">{{ $v->relationship }}</div></td>
                        <td class="nowrap">{{ $v->arrival_date->format('j M Y') }}</td>
                        <td class="nowrap">{{ $v->departure_date?->format('j M Y') ?? 'Still here' }}</td>
                        <td>
                            @if ($row['over'])
                                <span class="tag t-red">Over 2 days ({{ $row['days'] }})</span>
                            @else
                                <span class="tag t-green">{{ $row['days'] }} {{ Str::plural('day', $row['days']) }}</span>
                            @endif
                        </td>
                        <td class="actions">
                            @if (! $v->departure_date && $household['can_manage'])
                                <form method="POST" action="{{ route('visitors.depart', $v) }}" class="inline-end">
                                    @csrf @method('PATCH')
                                    <input type="date" name="departure_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" aria-label="Departure date">
                                    <button type="submit" class="btn sec">Left</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($household['can_manage'])
        <form method="POST" action="{{ route('leases.visitors.store', $household['lease_id']) }}" class="add-row" novalidate>
            @csrf
            <div class="row">
                <div class="f {{ $bag->has('visitor_name') ? 'has-err' : '' }}">
                    <label for="visitor_name">Visitor's name</label>
                    <input id="visitor_name" name="visitor_name" type="text" maxlength="255" value="{{ old('visitor_name') }}">
                    @if ($bag->has('visitor_name'))<div class="err">{{ $bag->first('visitor_name') }}</div>@endif
                </div>
                <div class="f {{ $bag->has('relationship') ? 'has-err' : '' }}">
                    <label for="relationship">Relationship to tenant</label>
                    <select id="relationship" name="relationship">
                        @foreach (\App\Models\Visitor::RELATIONSHIPS as $rel)
                            <option value="{{ $rel }}" @selected(old('relationship') === $rel)>{{ $rel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="f {{ $bag->has('arrival_date') ? 'has-err' : '' }}">
                    <label for="arrival_date">Arrived</label>
                    <input id="arrival_date" name="arrival_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('arrival_date', today()->toDateString()) }}">
                    @if ($bag->has('arrival_date'))<div class="err">{{ $bag->first('arrival_date') }}</div>@endif
                </div>
                <div class="f {{ $bag->has('departure_date') ? 'has-err' : '' }}">
                    <label for="departure_date">Left (if already gone)</label>
                    <input id="departure_date" name="departure_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('departure_date') }}">
                    @if ($bag->has('departure_date'))<div class="err">{{ $bag->first('departure_date') }}</div>@endif
                </div>
            </div>
            <button type="submit" class="btn sec">+ Log visitor</button>
        </form>
    @endif
</div>
