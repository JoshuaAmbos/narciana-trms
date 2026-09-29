@extends('layouts.app')

@section('title', 'Tenants & MOA')

{{-- Caretaker version: only room number and principal tenant name (decisions 10 and 12). --}}
@section('content')
    <div class="panel form-panel">
        <h2>Occupied rooms</h2>
        <p class="muted">Record appliances (rooms without a meter) and visitors here.</p>
        @if ($rows->isEmpty())
            <p class="muted">No rooms are leased right now.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Room</th><th>Tenant</th><th></th></tr>
                    @foreach ($rows as $row)
                        <tr>
                            <td><b>{{ $row['room_no'] }}</b></td>
                            <td>{{ $row['tenant_name'] }}</td>
                            <td class="actions">
                                <a href="{{ route('leases.household', $row['lease_id']) }}" class="btn sec">Appliances &amp; visitors</a>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
