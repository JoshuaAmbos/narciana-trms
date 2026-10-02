{{-- Itemized settlement statement (decision 18). Needs: $moveout, $lease, $figures (SettlementService::figures) --}}
@php($f = $figures)
<div class="statement">
    <div class="statement-head">
        <div><b>Narciana House Space Rental</b><br><span class="muted">Km. 6 Carnation St., Buhangin, Davao City</span></div>
        <div class="r"><b>Move-out settlement</b><br><span class="muted">Room {{ $lease->room->room_no }} · {{ $lease->tenant->full_name }}</span></div>
    </div>
    <table class="sum">
        <tr><td>Moved in / moved out</td>
            <td class="r">{{ $lease->move_in_date->format('j M Y') }} → {{ $moveout->actual_date?->format('j M Y') ?? 'planned '.$moveout->planned_date->format('j M Y') }}</td></tr>

        <tr><td colspan="2"><b>Deposit paid</b></td></tr>
        <tr><td>Maintenance deposit <span class="sub">kept by the owner, never refunded</span></td><td class="money">@peso($lease->deposit_maintenance)</td></tr>
        <tr><td>Damage deposit</td><td class="money">@peso($lease->deposit_damage)</td></tr>
        <tr><td>Bill expenses deposit</td><td class="money">@peso($lease->deposit_bills)</td></tr>
        <tr><td>Door knob and keys deposit</td><td class="money">@peso($lease->deposit_keys)</td></tr>
        <tr class="subtotal"><td>Total deposit</td><td class="money">@peso($f['deposit_total'])</td></tr>
        <tr><td>Less: maintenance deposit kept</td><td class="money">(@peso($lease->deposit_maintenance))</td></tr>
        <tr class="subtotal"><td>Refundable deposit <span class="sub">damage + bills + keys</span></td><td class="money">@peso($f['refundable'])</td></tr>

        <tr><td colspan="2"><b>Less: unpaid bills</b></td></tr>
        @forelse ($f['unpaid'] as $row)
            <tr><td>Bill due {{ $row['bill']->due_date->format('j M Y') }}
                    @if ($row['bill']->bill_id === $moveout->final_bill_id) <span class="sub">final bill (last utilities)</span>@endif
                    @if ($row['penalty'] > 0)<span class="sub">@peso((float) $row['total'] - (float) $row['penalty']) + penalty @peso($row['penalty']) ({{ $row['days_late'] }} {{ Str::plural('day', $row['days_late']) }} late)</span>@endif</td>
                <td class="money">(@peso($row['total']))</td></tr>
        @empty
            <tr><td><span class="sub">none</span></td><td class="money">(@peso(0))</td></tr>
        @endforelse

        <tr><td colspan="2"><b>Less: damaged or missing items</b> <span class="sub">inspection{{ $moveout->inspected_at ? ' of '.$moveout->inspected_at->format('j M Y') : ' not recorded yet' }}</span></td></tr>
        @forelse ($moveout->items as $item)
            <tr><td>{{ $item->description }}</td><td class="money">(@peso($item->cost))</td></tr>
        @empty
            <tr><td><span class="sub">none</span></td><td class="money">(@peso(0))</td></tr>
        @endforelse

        <tr class="subtotal"><td>Balance <span class="sub">refundable deposit − unpaid bills (@peso($f['unpaid_total'])) − damage (@peso($f['damage_total']))</span></td>
            <td class="money">@peso($f['balance'])</td></tr>

        @if ($f['early'])
            <tr><td colspan="2"><span class="tag t-red">Early departure</span>
                <span class="sub">Left before the minimum stay of {{ $lease->min_stay_months }} months: the deposit is forfeited (no refund). Any amount above the deposit is still owed.</span></td></tr>
        @endif

        @if ($f['excess'] > 0)
            <tr class="tot"><td>Excess: amount still owed by the tenant <span class="sub">billed to the tenant; no refund</span></td><td class="money red">@peso($f['excess'])</td></tr>
        @else
            <tr class="tot"><td>Final refund to tenant
                @if ($moveout->refund_due_date)<span class="sub">due by {{ $moveout->refund_due_date->format('j M Y') }}</span>@endif</td>
                <td class="money">@peso($f['refund'])</td></tr>
        @endif
    </table>
</div>
