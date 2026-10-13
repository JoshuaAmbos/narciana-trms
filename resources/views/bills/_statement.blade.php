{{-- Itemized billing statement. Needs: $statement (BillingService::preview / finalPreview / statementForBill), $lease --}}
@php($a = $statement['amounts'])
<div class="statement">
    <div class="statement-head">
        <div>
            <b>Narciana House Space Rental</b><br>
            <span class="muted">Km. 6 Carnation St., Buhangin, Davao City</span>
        </div>
        <div class="r">
            <b>{{ ($statement['excess'] ?? null) ? 'Move-out balance' : (($statement['final'] ?? false) ? 'Final billing statement' : 'Billing statement') }}</b><br>
            <span class="muted">Room {{ $lease->room->room_no }} · {{ $lease->tenant->full_name }}</span>
        </div>
    </div>

    @if ($statement['excess'] ?? null)
        {{-- Excess bill: what the tenant still owes after the deposit (decision 16). --}}
        <table class="sum">
            <tr><td>Amount still owed after the move-out settlement
                <span class="sub">Unpaid bills and damage costs above the refundable deposit. See the settlement statement.</span></td>
                <td class="money">@peso($a['other_charges'])</td></tr>
            @if ((float) $a['penalty_amount'] > 0)
                <tr><td>Penalty</td><td class="money">@peso($a['penalty_amount'])</td></tr>
            @endif
            <tr class="tot"><td>Total due on {{ $statement['due_date']->format('j M Y') }}</td><td class="money">@peso($a['total_amount'])</td></tr>
        </table>
    @else
    <table class="sum">
        <tr>
            @if ($statement['final'] ?? false)
                <td>Monthly rent <span class="sub">final bill: this month's rent was already charged ahead</span></td>
            @else
                <td>Monthly rent <span class="sub">{{ $statement['rent_from']->format('j M') }} – {{ $statement['rent_to']->format('j M Y') }}</span></td>
            @endif
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
                <td>
                    Appliance surcharges ({{ $statement['appliances'] }} × @peso($statement['appliance_fee']))
                    @if (!empty($statement['appliance_items']))
                        <span class="sub">{{ implode(', ', array_map(fn($item) => '1× ' . $item, $statement['appliance_items'])) }}</span>
                    @endif
                </td>
                <td class="money">@peso($a['appliance_amount'])</td>
            </tr>
        @endif
        <tr><td>Penalty</td><td class="money">@peso($a['penalty_amount'])</td></tr>
        <tr>
            <td>Other charges
                @foreach ($statement['charges'] ?? [] as $charge)
                    <span class="sub">Tenant Repair: {{ $charge->description }} ({{ $charge->category }}): @peso($charge->cost ?? $charge->repair_cost)</span>
                @endforeach
            </td>
            <td class="money">@peso($a['other_charges'])</td>
        </tr>
        <tr class="tot"><td>Total due on {{ $statement['due_date']->format('j M Y') }}</td><td class="money">@peso($a['total_amount'])</td></tr>
    </table>
    @endif
</div>
