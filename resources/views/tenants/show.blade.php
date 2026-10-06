@extends('layouts.app')

@section('title', $tenant->full_name)

@section('content')
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <div>
                <h2>
                    {{ $tenant->full_name }}
                    <span class="tag {{ $tenant->status === 'Active' ? 't-green' : ($tenant->status === 'Blacklisted' ? 't-red' : 't-amber') }}" style="font-size: 12px; vertical-align: middle; margin-left: 8px;">
                        {{ $tenant->status }}
                    </span>
                </h2>
                <div class="muted small">Tenant Master Record #TNT-{{ str_pad($tenant->tenant_id, 4, '0', STR_PAD_LEFT) }} &bull; Registered {{ $tenant->created_at?->format('j M Y') }}</div>
            </div>
            <div class="actions">
                <a href="{{ route('tenants.edit', $tenant) }}" class="btn sec">Edit Profile</a>
                @if (! $tenant->hasActiveLease())
                    <a href="{{ route('leases.create', ['tenant_id' => $tenant->tenant_id]) }}" class="btn">+ Create Lease</a>
                @endif
                <a href="{{ route('tenants.index') }}" class="btn sec">&larr; Back to Directory</a>
            </div>
        </div>

        {{-- Master Identity & Demographic Cards --}}
        <div class="cards" style="margin-bottom: 20px;">
            <div class="card">
                <div class="l">Current Tenancy</div>
                @if ($activeLease)
                    <div class="n" style="font-size: 20px;">Room {{ $activeLease->room->room_no }}</div>
                    <div class="sub">{{ $activeLease->room->room_type }} room &bull; since {{ $activeLease->move_in_date->format('j M Y') }}</div>
                @else
                    <div class="n muted" style="font-size: 18px;">No Active Lease</div>
                    <div class="sub">Eligible for room assignment</div>
                @endif
            </div>
            <div class="card">
                <div class="l">Contact Number</div>
                <div class="n" style="font-size: 20px;">{{ $tenant->contact_no }}</div>
                <div class="sub">Primary phone</div>
            </div>
            <div class="card">
                <div class="l">Government Identification</div>
                <div class="n" style="font-size: 18px;">{{ $tenant->valid_id_type }}</div>
                <div class="sub nowrap">ID No: <b>{{ $tenant->valid_id_no }}</b></div>
            </div>
            <div class="card">
                <div class="l">Lifetime MOAs</div>
                <div class="n" style="font-size: 20px;">{{ $leases->count() }}</div>
                <div class="sub">{{ $leases->where('status', 'Active')->count() }} active, {{ $leases->where('status', 'Ended')->count() }} past</div>
            </div>
        </div>

        {{-- Demographic Facts --}}
        <dl class="facts" style="margin-bottom: 24px;">
            <div>
                <dt>Provincial Address</dt>
                <dd>{{ $tenant->provincial_address ?: 'Not recorded' }}</dd>
            </div>
            <div>
                <dt>Occupation</dt>
                <dd>{{ $tenant->occupation ?: 'Not recorded' }}</dd>
            </div>
            <div>
                <dt>Previous Building / Residence</dt>
                <dd>{{ $tenant->prev_building ?: 'None specified' }}</dd>
            </div>
            <div>
                <dt>System Profile Status</dt>
                <dd><b>{{ $tenant->status }}</b></dd>
            </div>
        </dl>
    </div>

    {{-- Tenancy History (MOA Agreements) --}}
    <div class="panel">
        <h2>
            Tenancy History (MOA Agreements)
            <span class="muted" style="font-size: 13.5px; font-weight: normal; margin-left: 8px;">All contracts associated with this tenant</span>
        </h2>

        @if ($leases->isEmpty())
            <p class="muted">This tenant has no past or current lease contracts yet.
                <a href="{{ route('leases.create', ['tenant_id' => $tenant->tenant_id]) }}">Create their first lease &rarr;</a>
            </p>
        @else
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Contract Ref</th>
                        <th>Room</th>
                        <th>Move-in Date</th>
                        <th>Due Day</th>
                        <th class="r">Monthly Rent</th>
                        <th>Occupants</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    @foreach ($leases as $lease)
                        <tr>
                            <td><b>MOA-{{ str_pad($lease->lease_id, 4, '0', STR_PAD_LEFT) }}</b></td>
                            <td><b>{{ $lease->room->room_no }}</b> <span class="sub nowrap">{{ $lease->room->room_type }}</span></td>
                            <td class="nowrap">{{ $lease->move_in_date->format('j M Y') }}</td>
                            <td>Every {{ $lease->dueDayLabel() }}</td>
                            <td class="money">@peso($lease->monthly_rent)</td>
                            <td>{{ $lease->occupants->whereNull('date_removed')->count() }} heads</td>
                            <td>
                                <x-lease-status :lease="$lease" />
                                @if ($lease->status === 'Active' && $lease->moveout)
                                    <div class="sub">{{ $lease->moveout->actual_date ? 'left '.$lease->moveout->actual_date->format('j M') : 'leaving '.$lease->moveout->planned_date->format('j M') }}</div>
                                @endif
                            </td>
                            <td class="actions">
                                <a href="{{ route('leases.show', $lease) }}" class="btn sec">View MOA</a>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
