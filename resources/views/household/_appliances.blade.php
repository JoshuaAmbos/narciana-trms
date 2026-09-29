{{-- Appliances panel. Needs: $household (from HouseholdService::summary) --}}
@php($bag = $errors->getBag('appliance'))
<div class="panel" id="appliances">
    <h2>Appliances <span class="muted">Room {{ $household['room_no'] }}</span></h2>

    @if ($household['room_metered'])
        <p class="muted">This room has its own electric meter, so appliances are not charged (electricity is billed per kWh).</p>
    @else
        <p class="muted">Each appliance is charged a flat @peso($household['appliance_fee']) a month.</p>
        @php($current = $household['appliances']->whereNull('date_removed'))
        @if ($household['appliances']->isEmpty())
            <p class="muted">No appliances recorded.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Appliance</th><th>Since</th><th>Source</th><th class="r">Per month</th><th></th></tr>
                    @foreach ($household['appliances'] as $appliance)
                        <tr class="{{ $appliance->date_removed ? 'removed' : '' }}">
                            <td>{{ $appliance->appliance_name }}</td>
                            <td class="nowrap">
                                {{ $appliance->date_added->format('j M Y') }}
                                @if ($appliance->date_removed)<div class="sub">removed {{ $appliance->date_removed->format('j M Y') }}</div>@endif
                            </td>
                            <td>{{ $appliance->source }}</td>
                            <td class="money">@peso($appliance->monthly_fee)</td>
                            <td class="actions">
                                @if (! $appliance->date_removed && $household['can_manage'])
                                    <form method="POST" action="{{ route('appliances.remove', $appliance) }}" class="inline-end"
                                          onsubmit="return confirm('Mark {{ $appliance->appliance_name }} as removed?')">
                                        @csrf @method('PATCH')
                                        <input type="date" name="date_removed" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" aria-label="Date removed">
                                        <button type="submit" class="btn sec">Remove</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
            <p class="muted">{{ $current->count() }} current {{ Str::plural('appliance', $current->count()) }}:
                @peso($current->count() * $household['appliance_fee']) a month.</p>
        @endif

        @if ($household['can_manage'])
            <form method="POST" action="{{ route('leases.appliances.store', $household['lease_id']) }}" class="add-row" novalidate>
                @csrf
                <div class="row">
                    <div class="f {{ $bag->has('appliance_name') ? 'has-err' : '' }}">
                        <label for="appliance_name">Appliance</label>
                        <input id="appliance_name" name="appliance_name" type="text" maxlength="100" list="appliance-names"
                               placeholder="e.g. Rice cooker" value="{{ old('appliance_name') }}">
                        <datalist id="appliance-names">
                            @foreach (['Rice cooker', 'Electric kettle', 'Electric fan (extra)', 'Television', 'Refrigerator', 'Laptop', 'Hair dryer', 'Flat iron', 'Induction cooker'] as $name)
                                <option value="{{ $name }}">
                            @endforeach
                        </datalist>
                        @if ($bag->has('appliance_name'))<div class="err">{{ $bag->first('appliance_name') }}</div>@endif
                    </div>
                    <div class="f {{ $bag->has('source') ? 'has-err' : '' }}">
                        <label for="source">How it was found</label>
                        <select id="source" name="source">
                            @foreach (\App\Models\Appliance::SOURCES as $source)
                                <option value="{{ $source }}" @selected(old('source', 'Declared') === $source)>{{ $source === 'CCTV' ? 'Seen on CCTV' : 'Declared by tenant' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="f {{ $bag->has('date_added') ? 'has-err' : '' }}">
                        <label for="appliance_date">Since</label>
                        <input id="appliance_date" name="date_added" type="date" max="{{ today()->toDateString() }}" value="{{ old('date_added', today()->toDateString()) }}">
                        @if ($bag->has('date_added'))<div class="err">{{ $bag->first('date_added') }}</div>@endif
                    </div>
                </div>
                @if ($bag->has('date_removed'))<div class="alert alert-err">{{ $bag->first('date_removed') }}</div>@endif
                <button type="submit" class="btn sec">+ Add appliance</button>
            </form>
        @endif
    @endif
</div>
