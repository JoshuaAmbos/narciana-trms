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
                <div class="actions" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    @can('manage-leases')
                        @if ($lease->status === 'Active')
                            <button type="button" class="btn danger" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center; padding: 0 16px; font-weight: 600;" onclick="openEndLeaseModal()">End Lease</button>
                            <a href="{{ route('leases.edit', $lease) }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">Edit MOA</a>
                        @endif
                    @endcan
                    <a href="{{ route('leases.index') }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">&larr; Back to Leases</a>
                </div>
            </h2>

            <dl class="facts">
                <div><dt>Principal renter</dt><dd><b>{{ $lease->tenant->full_name }}</b> <span class="muted">&bull;</span> <a href="{{ route('tenants.show', $lease->tenant) }}" class="small-link">Master profile</a> <span class="muted">&bull;</span> <a href="{{ route('tenants.edit', $lease->tenant) }}" class="small-link">Edit profile</a></dd></div>
                @if ($coTenant = $lease->coTenant())
                    <div><dt>Registered co-tenant</dt><dd><b>{{ $coTenant->full_name }}</b> <span class="muted">(since {{ $coTenant->date_added->format('j M Y') }}{{ $coTenant->date_removed ? ', left '.$coTenant->date_removed->format('j M Y') : '' }})</span></dd></div>
                @endif
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

            @if ($canCancel)
                <form method="POST" action="{{ route('leases.cancel', $lease) }}" class="danger-zone"
                      onsubmit="return confirm('Cancel this MOA? Room {{ $lease->room->room_no }} will be Vacant again. Use this only for an MOA encoded by mistake or cancelled before any bill.')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn danger">Cancel this MOA</button>
                    <span class="muted">Only possible while there are no bills or payments. To conclude a tenancy, use End Lease.</span>
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

    @if ($lease->status === 'Active')
        @can('manage-leases')
            <div id="end-lease-modal" class="modal-backdrop" hidden>
                <div class="modal-box" style="max-width: 480px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="margin: 0;">End Lease &amp; Vacate Room</h3>
                        <button type="button" class="modal-close-btn" onclick="closeEndLeaseModal()">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('leases.end', $lease) }}">
                        @csrf
                        @method('PATCH')

                        <div class="facts" style="margin-bottom: 14px;">
                            <div><dt>Tenant</dt><dd><b>{{ $lease->tenant->full_name }}</b></dd></div>
                            <div><dt>Room</dt><dd><b>Room {{ $lease->room->room_no }}</b> ({{ $lease->room->room_type }})</dd></div>
                        </div>

                        <div class="f" style="margin-bottom: 14px;">
                            <label for="actual_end_date">Actual Move-out Date</label>
                            <input id="actual_end_date" type="date" name="actual_end_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}">
                        </div>

                        <div class="f" style="margin-bottom: 14px;">
                            <label for="termination_notes">Termination Notes (optional)</label>
                            <textarea id="termination_notes" name="termination_notes" rows="2" placeholder="e.g. Keys returned, room vacated without incident."></textarea>
                        </div>

                        <div class="alert alert-warn" style="margin-bottom: 16px; font-size: 13px;">
                            Ending this lease will immediately mark Room {{ $lease->room->room_no }} as Vacant. All physical room inspections and deposit returns are handled offline per MOA terms.
                        </div>

                        <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 8px;">
                            <button type="button" class="btn sec" style="min-height: 44px; padding: 0 16px;" onclick="closeEndLeaseModal()">Cancel</button>
                            <button type="submit" class="btn danger" style="min-height: 44px; padding: 0 16px;">Confirm &amp; Vacate Room</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function openEndLeaseModal() {
                    document.getElementById('end-lease-modal').removeAttribute('hidden');
                }
                function closeEndLeaseModal() {
                    document.getElementById('end-lease-modal').setAttribute('hidden', '');
                }
                document.addEventListener('click', function(e) {
                    const modal = document.getElementById('end-lease-modal');
                    if (modal && e.target === modal) {
                        closeEndLeaseModal();
                    }
                });
            </script>
        @endcan
    @endif
@endsection
