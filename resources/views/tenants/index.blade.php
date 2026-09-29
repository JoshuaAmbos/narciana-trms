@extends('layouts.app')

@section('title', 'Tenants & MOA')

@section('content')
    <div class="panel">
        <h2>
            Leases (MOA)
            <a href="{{ route('leases.create') }}" class="btn">+ Encode new MOA</a>
        </h2>

        <div class="filters">
            <span class="muted">Show:</span>
            @foreach ($statuses as $s)
                <a href="{{ route('tenants.index', ['status' => $s]) }}" class="chip {{ $status === $s ? 'on' : '' }}">{{ $s }}</a>
            @endforeach
        </div>

        @if ($leases->isEmpty())
            <p class="muted">No {{ strtolower($status) }} leases.
                @if ($status === 'Active') Use "+ Encode new MOA" when a tenant signs. @endif</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Room</th><th>Tenant</th><th>Contact</th><th>Move-in</th><th>Due day</th>
                        <th class="r">Rent</th><th>Occupants</th><th></th>
                    </tr>
                    @foreach ($leases as $lease)
                        <tr>
                            <td><b>{{ $lease->room->room_no }}</b><div class="sub nowrap">{{ $lease->room->room_type }}</div></td>
                            <td>{{ $lease->tenant->full_name }}</td>
                            <td>{{ $lease->tenant->contact_no }}</td>
                            <td class="nowrap">{{ $lease->move_in_date->format('j M Y') }}</td>
                            <td>Every {{ $lease->dueDayLabel() }}</td>
                            <td class="money">@peso($lease->monthly_rent)</td>
                            <td>{{ $lease->active_occupants_count }}</td>
                            <td class="actions"><a href="{{ route('leases.show', $lease) }}" class="btn sec">Open</a></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
