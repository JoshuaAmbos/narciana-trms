@extends('layouts.app')

@section('title', 'Billing')

@section('content')
    @error('lease') <div class="alert alert-err">{{ $message }}</div> @enderror

    <div class="panel">
        <h2>To bill <span class="muted">Due within {{ \App\Services\BillingService::DAYS_AHEAD }} days or already due</span></h2>
        @if ($toBill->isEmpty())
            <p class="muted">Nothing to bill right now.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Due date</th><th>Room</th><th>Tenant</th><th>Meter</th><th></th></tr>
                    @foreach ($toBill as $row)
                        <tr>
                            <td class="nowrap">
                                {{ $row['due_date']->format('j M Y') }}
                                @if ($row['due_date']->lt(today()))
                                    <span class="tag t-red">late to bill</span>
                                @endif
                            </td>
                            <td><b>{{ $row['lease']->room->room_no }}</b> <span class="sub nowrap">{{ $row['lease']->room->room_type }}</span></td>
                            <td>{{ $row['lease']->tenant->full_name }}</td>
                            <td>{{ $row['lease']->room->is_metered ? 'Reading needed' : 'No meter' }}</td>
                            <td class="actions"><a href="{{ route('bills.create', ['lease' => $row['lease']->lease_id]) }}" class="btn">Generate</a></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    <div class="panel">
        <h2>
            Bills due in {{ $month->format('F Y') }}
            <span class="month-nav">
                <a href="{{ route('bills.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn sec">‹</a>
                <a href="{{ route('bills.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn sec">›</a>
                <a href="{{ route('meter-readings.index') }}" class="btn sec">Meter readings</a>
            </span>
        </h2>
        @if ($bills->isEmpty())
            <p class="muted">No bills due in {{ $month->format('F Y') }}.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Due</th><th>Room</th><th>Tenant</th><th class="r">Rent</th><th class="r">Water</th>
                        <th class="r">Electricity / Appliances</th><th class="r">Total</th><th>Status</th><th></th>
                    </tr>
                    @foreach ($bills as $bill)
                        <tr>
                            <td class="nowrap">{{ $bill->due_date->format('j M') }}</td>
                            <td><b>{{ $bill->lease->room->room_no }}</b></td>
                            <td>{{ $bill->lease->tenant->full_name }}</td>
                            <td class="money">@peso($bill->rent_amount)</td>
                            <td class="money">@peso($bill->water_amount)</td>
                            <td class="money">@peso((float) $bill->electricity_amount + (float) $bill->appliance_amount)</td>
                            <td class="money"><b>@peso($bill->total_amount)</b></td>
                            <td><x-bill-status :status="$bill->status" /></td>
                            <td class="actions"><a href="{{ route('bills.show', $bill) }}" class="btn sec">Statement</a></td>
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td colspan="6">{{ $totals['count'] }} {{ Str::plural('bill', $totals['count']) }}</td><td class="money"><b>@peso($totals['amount'])</b></td><td colspan="2"></td></tr>
                </table>
            </div>
        @endif
    </div>
@endsection
