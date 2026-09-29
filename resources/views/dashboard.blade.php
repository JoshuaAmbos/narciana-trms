@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="cards">
        <div class="card"><div class="n amber">–</div><div class="l">Bills due in the next 7 days</div></div>
        <div class="card"><div class="n red">–</div><div class="l">Overdue tenants</div></div>
        <div class="card"><div class="n blue">–</div><div class="l">Open concerns</div></div>
        <div class="card"><div class="n green">{{ $vacantCount }}</div><div class="l">Vacant rooms</div></div>
    </div>

    <div class="grid3">
        <div class="panel">
            <h2>Rooms</h2>
            @if ($rooms->isEmpty())
                <p class="muted">No rooms yet. For local testing run <code>php artisan db:seed --class=DemoSeeder</code>.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <tr><th>Room</th><th>Type</th><th>Meter</th><th class="r">Monthly rent</th><th>Status</th></tr>
                        @foreach ($rooms as $room)
                            <tr>
                                <td>{{ $room->room_no }}</td>
                                <td>{{ $room->room_type }}</td>
                                <td>{{ $room->is_metered ? 'Own meter' : 'None' }}</td>
                                <td class="money">@peso($room->monthly_rent)</td>
                                <td><x-room-status :room="$room" /></td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>

        <div>
            <div class="panel">
                <h2>Setup check (Module 1)</h2>
                <table class="sum">
                    <tr><td>Database</td><td class="r"><span class="tag t-green">Connected</span></td></tr>
                    <tr><td>Staff accounts</td><td class="r">{{ $userCount }}</td></tr>
                    <tr><td>Rooms</td><td class="r">{{ $roomCount }}</td></tr>
                    <tr><td>Settings row</td><td class="r">{!! $setting ? '<span class="tag t-green">Present</span>' : '<span class="tag t-red">Missing</span>' !!}</td></tr>
                </table>
            </div>

            @if ($setting)
                <div class="panel">
                    <h2>Rates and rules</h2>
                    <table class="sum">
                        <tr><td>Water per adult</td><td class="money">@peso($setting->water_rate)</td></tr>
                        <tr><td>Fee per appliance</td><td class="money">@peso($setting->appliance_fee)</td></tr>
                        <tr><td>Electricity per kWh</td><td class="money">@peso($setting->kwh_rate)</td></tr>
                        <tr><td>Grace period</td><td class="r">{{ $setting->grace_days }} days</td></tr>
                        <tr><td>Penalty</td><td class="r">{{ $setting->penalty_rate }}% ({{ $setting->penalty_basis }})</td></tr>
                        <tr><td>Refund after move-out</td><td class="r">{{ $setting->refund_days }} days</td></tr>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
