@extends('layouts.app')

@section('title', 'MOA: Room '.$lease->room->room_no)

@section('content')
    @php($occBag = $errors->getBag('occupant'))
    @error('lease') <div class="alert alert-err">{{ $message }}</div> @enderror

    <div class="grid3">
        <div class="panel">
            <h2>
                <span>MOA details: Room {{ $lease->room->room_no }}
                    <x-lease-status :lease="$lease" />
                </span>
                @if ($lease->status === 'Active')
                    <a href="{{ route('leases.edit', $lease) }}" class="btn sec">Edit MOA</a>
                @endif
                <a href="{{ route('leases.index') }}" class="btn sec">&larr; Back to Leases</a>
            </h2>

            <dl class="facts">
                <div><dt>Principal renter</dt><dd><b>{{ $lease->tenant->full_name }}</b> <span class="muted">&bull;</span> <a href="{{ route('tenants.show', $lease->tenant) }}" class="small-link">Master profile</a> <span class="muted">&bull;</span> <a href="{{ route('tenants.edit', $lease->tenant) }}" class="small-link">Edit profile</a></dd></div>
                <div><dt>Contact number</dt><dd>{{ $lease->tenant->contact_no }}</dd></div>
                <div><dt>Valid ID</dt><dd>{{ $lease->tenant->valid_id_type }} {{ $lease->tenant->valid_id_no }}</dd></div>
                <div><dt>Provincial address</dt><dd>{{ $lease->tenant->provincial_address ?? 'Not recorded' }}</dd></div>
                <div><dt>Occupation</dt><dd>{{ $lease->tenant->occupation ?? 'Not recorded' }}</dd></div>
                <div><dt>Previous apartment/room</dt><dd>{{ $lease->tenant->prev_building ?? 'None given' }}</dd></div>
                <div><dt>Room</dt><dd>{{ $lease->room->room_type }}, {{ $lease->room->is_metered ? 'own meter' : 'no meter' }}</dd></div>
                <div><dt>MOA signed</dt><dd>{{ $lease->moa_date->format('j M Y') }}</dd></div>
                <div><dt>Move-in date</dt><dd>{{ $lease->move_in_date->format('j M Y') }}</dd></div>
                <div><dt>Monthly due date</dt><dd>Every {{ $lease->dueDayLabel() }}</dd></div>
                <div><dt>Minimum stay</dt><dd>{{ $lease->min_stay_months }} months
                    <span class="sub">until {{ $lease->move_in_date->copy()->addMonthsNoOverflow($lease->min_stay_months)->format('j M Y') }}</span></dd></div>
                <div><dt>Monthly rent</dt><dd>@peso($lease->monthly_rent)</dd></div>
                <div><dt>Advance paid</dt><dd>@peso($lease->advance_amount)</dd></div>
            </dl>

            <div class="table-wrap">
                <table>
                    <tr><th>Maintenance (non-refundable)</th><th>Damage</th><th>Bill expenses</th><th>Door knob and keys</th><th class="r">Total deposit</th></tr>
                    <tr>
                        <td class="money">@peso($lease->deposit_maintenance)</td>
                        <td class="money">@peso($lease->deposit_damage)</td>
                        <td class="money">@peso($lease->deposit_bills)</td>
                        <td class="money">@peso($lease->deposit_keys)</td>
                        <td class="money"><b>@peso($lease->depositTotal())</b></td>
                    </tr>
                </table>
            </div>
            <p class="muted">Encoded by the {{ $lease->user->role }}.</p>

            <div class="moveout-link">
                @if ($lease->moveout)
                    Move-out: <x-moveout-status :moveout="$lease->moveout" />
                    <a href="{{ route('moveouts.show', $lease->moveout) }}" class="btn sec">Open the move-out settlement</a>
                @elseif ($lease->status === 'Active')
                    <a href="{{ route('moveouts.create', ['lease' => $lease->lease_id]) }}" class="btn sec">Give move-out notice</a>
                @endif
            </div>

            @if ($canCancel)
                <form method="POST" action="{{ route('leases.cancel', $lease) }}" class="danger-zone"
                      onsubmit="return confirm('Cancel this MOA? Room {{ $lease->room->room_no }} will be Vacant again. Use this only for an MOA encoded by mistake or cancelled before any bill.')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn danger">Cancel this MOA</button>
                    <span class="muted">Only possible while there are no bills or payments. A normal move-out is done in Move-out Settlement.</span>
                </form>
            @endif
        </div>

        <div class="panel" id="occupants">
            <h2>Occupants <span class="muted">max {{ $maxOccupants }}</span></h2>
            <table>
                @foreach ($lease->occupants as $occupant)
                    <tr class="{{ $occupant->date_removed ? 'removed' : '' }}">
                        <td>
                            {{ $occupant->full_name }}
                            <div class="sub">
                                {{ $occupant->is_principal ? 'Principal renter' : 'Co-tenant' }} · since {{ $occupant->date_added->format('j M Y') }}
                                @if ($occupant->date_removed) · left {{ $occupant->date_removed->format('j M Y') }} @endif
                            </div>
                        </td>
                        <td class="actions">
                            @if (! $occupant->is_principal && ! $occupant->date_removed && $lease->status === 'Active')
                                <form method="POST" action="{{ route('occupants.remove', $occupant) }}" class="inline-end"
                                      onsubmit="return confirm('Mark {{ $occupant->full_name }} as no longer staying?')">
                                    @csrf @method('PATCH')
                                    <input type="date" name="date_removed" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" aria-label="Date left">
                                    <button type="submit" class="btn sec">Left</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
            @if ($occBag->any())<div class="alert alert-err">{{ $occBag->first() }}</div>@endif

            @if ($lease->status === 'Active' && $lease->occupants->whereNull('date_removed')->count() < $maxOccupants)
                <form method="POST" action="{{ route('leases.occupants.store', $lease) }}" class="add-row" novalidate>
                    @csrf
                    <div class="f"><label for="occ_name">Add co-tenant</label>
                        <input id="occ_name" name="full_name" type="text" maxlength="255" value="{{ old('full_name') }}"></div>
                    <div class="f"><label for="occ_date">Staying since</label>
                        <input id="occ_date" name="date_added" type="date" max="{{ today()->toDateString() }}" value="{{ old('date_added', today()->toDateString()) }}"></div>
                    <button type="submit" class="btn sec">+ Add occupant</button>
                </form>
            @elseif ($lease->status === 'Active')
                <p class="muted">The room is full ({{ $maxOccupants }} {{ Str::plural('occupant', $maxOccupants) }}).</p>
            @endif
            <p class="muted">Water is billed per occupant staying during the month.</p>
        </div>
    </div>

    @include('household._appliances')
    @include('household._visitors')
@endsection
