{{-- Itemized billing statement. Needs: $statement (BillingService::preview or ::statementForBill), $lease --}}
@php($a = $statement['amounts'])
<div class="statement">
    <div class="statement-head">
        <div>
            <b>Narciana House Space Rental</b><br>
            <span class="muted">Km. 6 Carnation St., Buhangin, Davao City</span>
        </div>
        <div class="r">
            <b>Billing statement</b><br>
            <span class="muted">Room {{ $lease->room->room_no }} · {{ $lease->tenant->full_name }}</span>
        </div>
    </div>

    <table class="sum">
        <tr>
            <td>Monthly rent <span class="sub">{{ $statement['rent_from']->format('j M') }} – {{ $statement['rent_to']->format('j M Y') }}</span></td>
            <td class="money">@peso($a['rent_amount'])</td>
        </tr>
        <tr>
            <td>Water ({{ $statement['occupants'] }} {{ Str::plural('occupant', $statement['occupants']) }} × @peso($statement['water_rate']))
                <span class="sub">{{ $statement['period_start']->format('j M') }} – {{ $statement['period_end']->format('j M Y') }}</span></td>
            <td class="money">@peso($a['water_amount'])</td>
        </tr>
        @if ($statement['metered'])
            <tr>
                <td>
                    @if ($statement['reading'])
                        Electricity ({{ number_format($statement['kwh_used'], 2) }} kWh × @peso($statement['kwh_rate']))
                        <span class="sub">Reading {{ number_format((float) $statement['reading']['previous_reading'], 2) }} → {{ number_format((float) $statement['reading']['current_reading'], 2) }} kWh on {{ \Illuminate\Support\Carbon::parse($statement['reading']['reading_date'])->format('j M Y') }} ({{ $statement['reading']['source'] }})</span>
                    @else
                        Electricity
                        <span class="sub amber">Enter the meter reading to compute.</span>
                    @endif
                </td>
                <td class="money">@peso($a['electricity_amount'])</td>
            </tr>
            <tr><td>Appliance surcharges <span class="sub">not charged: room has its own meter</span></td><td class="money">@peso(0)</td></tr>
        @else
            <tr><td>Electricity <span class="sub">included in rent: room has no meter</span></td><td class="money">@peso(0)</td></tr>
            <tr>
                <td>Appliance surcharges ({{ $statement['appliances'] }} × @peso($statement['appliance_fee']))</td>
                <td class="money">@peso($a['appliance_amount'])</td>
            </tr>
        @endif
        <tr><td>Penalty</td><td class="money">@peso($a['penalty_amount'])</td></tr>
        <tr><td>Other charges</td><td class="money">@peso($a['other_charges'])</td></tr>
        <tr class="tot"><td>Total due on {{ $statement['due_date']->format('j M Y') }}</td><td class="money">@peso($a['total_amount'])</td></tr>
    </table>
</div>
