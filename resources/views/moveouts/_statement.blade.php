{{-- Itemized settlement statement. Needs: $moveout, $lease, $figures (SettlementService::figures) --}}
@php($f = $figures)
<div class="statement">
    <div class="statement-head">
        <div><b>Narciana House Space Rental</b><br><span class="muted">Km. 6 Carnation St., Buhangin, Davao City</span></div>
        <div class="r"><b>Move-out settlement</b><br><span class="muted">Room {{ $lease->room->room_no }} · {{ $lease->tenant->full_name }}</span></div>
    </div>
    <table class="sum">
        <tr><td>Moved in / moved out</td>
            <td class="r">{{ $lease->move_in_date->format('j M Y') }} → {{ $moveout->actual_date?->format('j M Y') ?? 'planned '.$moveout->planned_date->format('j M Y') }}</td></tr>
        <tr><td>Total deposit</td><td class="money">@peso($f['deposit_total'])</td></tr>
        <tr><td>Less: maintenance deposit <span class="sub">never refunded</span></td><td class="money">(@peso($lease->deposit_maintenance))</td></tr>
        <tr class="subtotal"><td>Refundable deposit <span class="sub">damage @peso($lease->deposit_damage) + bills @peso($lease->deposit_bills) + keys @peso($lease->deposit_keys)</span></td>
            <td class="money">@peso($f['refundable'])</td></tr>

        <tr><td>Less: unpaid bills
                @forelse ($f['unpaid'] as $row)
                    <span class="sub">Bill due {{ $row['bill']->due_date->format('j M Y') }}: @peso((float) $row['bill']->total_amount - (float) $row['bill']->penalty_amount)
                        @if ($row['penalty'] > 0) + penalty @peso($row['penalty']) ({{ $row['days_late'] }} {{ Str::plural('day', $row['days_late']) }} late)@endif
                        @if ($row['bill']->finalOf) — final bill @endif</span>
                @empty
                    <span class="sub">none</span>
                @endforelse
            </td>
            <td class="money">(@peso($f['unpaid_total']))</td></tr>

        <tr><td>Less: damage ({{ $moveout->items->count() }} {{ Str::plural('item', $moveout->items->count()) }})
                @foreach ($moveout->items as $item)
                    <span class="sub">{{ $item->description }}: @peso($item->cost)</span>
                @endforeach
            </td>
            <td class="money">(@peso($f['damage_total']))</td></tr>

        <tr class="subtotal"><td>Balance</td><td class="money">@peso($f['balance'])</td></tr>

        @if ($f['early'])
            <tr><td colspan="2"><span class="tag t-red">Early departure</span>
                <span class="sub">Left before the minimum stay of {{ $lease->min_stay_months }} months: the deposit is forfeited (no refund). Any amount above the deposit is still owed.</span></td></tr>
        @endif

        @if ($f['excess'] > 0)
            <tr class="tot"><td>Amount still owed by the tenant</td><td class="money red">@peso($f['excess'])</td></tr>
        @else
            <tr class="tot"><td>Refund to tenant
                @if ($moveout->refund_due_date)<span class="sub">due by {{ $moveout->refund_due_date->format('j M Y') }}</span>@endif</td>
                <td class="money">@peso($f['refund'])</td></tr>
        @endif
    </table>
</div>
