@extends('layouts.app')

@section('title', 'Inquiries And Room Demand')

@section('content')
    <form method="GET" action="{{ route('reports.inquiries') }}" class="panel no-print report-filters" novalidate>
        @include('reports._range')
        @include('reports._actions')
    </form>

    <div class="panel printable reports-container">
        @include('reports._head', [
            'title' => 'Inquiries And Room Demand',
            'period' => $from->format('j M Y').' to '.$to->format('j M Y'),
        ])

        {{-- Demand KPI Cards --}}
        <div class="cards">
            <div class="card">
                <div class="n">{{ $total }}</div>
                <div class="l">Total {{ Str::plural('Inquiry', $total) }}</div>
            </div>
            <div class="card">
                <div class="n green">{{ $statusTotals['Rented'] }}</div>
                <div class="l">Converted &amp; Rented</div>
            </div>
            <div class="card">
                <div class="n amber">{{ $referred }}</div>
                <div class="l">Referred elsewhere</div>
            </div>
            <div class="card">
                <div class="n blue">{{ $statusTotals['Waiting'] }}</div>
                <div class="l">Still Waiting List</div>
            </div>
        </div>

        @if ($total === 0)
            <p class="muted">No prospective inquiries recorded in this period.</p>
        @else
            {{-- Documented 2x4 Demand Conversion Matrix --}}
            <h3 class="sub-head" style="margin-top: 22px;">Room Demand Conversion Matrix</h3>
            <div class="table-wrap report-table-wrap">
                <table class="fit">
                    <thead>
                        <tr>
                            <th>Room Type</th>
                            <th class="r">Total Inquiries</th>
                            <th class="r">Rented</th>
                            <th class="r">Referred</th>
                            <th class="r">Waiting</th>
                            <th class="r">Declined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (['Aircon', 'Basic'] as $type)
                            @php($row = $byType[$type] ?? ['total' => 0, 'statuses' => ['Rented' => 0, 'Referred' => 0, 'Waiting' => 0, 'Declined' => 0]])
                            <tr>
                                <td><b>{{ $type }} Room</b></td>
                                <td class="r"><b>{{ $row['total'] }}</b></td>
                                <td class="r" style="color: var(--green); font-weight: 600;">{{ $row['statuses']['Rented'] ?? 0 }}</td>
                                <td class="r" style="color: var(--amber); font-weight: 600;">{{ $row['statuses']['Referred'] ?? 0 }}</td>
                                <td class="r" style="color: var(--navy); font-weight: 600;">{{ $row['statuses']['Waiting'] ?? 0 }}</td>
                                <td class="r" style="color: var(--red);">{{ $row['statuses']['Declined'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                        @if (isset($byType['Any']) && $byType['Any']['total'] > 0)
                            <tr>
                                <td><b>Any / Flexible</b></td>
                                <td class="r"><b>{{ $byType['Any']['total'] }}</b></td>
                                <td class="r">{{ $byType['Any']['statuses']['Rented'] ?? 0 }}</td>
                                <td class="r">{{ $byType['Any']['statuses']['Referred'] ?? 0 }}</td>
                                <td class="r">{{ $byType['Any']['statuses']['Waiting'] ?? 0 }}</td>
                                <td class="r">{{ $byType['Any']['statuses']['Declined'] ?? 0 }}</td>
                            </tr>
                        @endif
                        <tr class="tot-row">
                            <td>Total Demand</td>
                            <td class="r">{{ $total }}</td>
                            <td class="r">{{ $statusTotals['Rented'] ?? 0 }}</td>
                            <td class="r">{{ $statusTotals['Referred'] ?? 0 }}</td>
                            <td class="r">{{ $statusTotals['Waiting'] ?? 0 }}</td>
                            <td class="r">{{ $statusTotals['Declined'] ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Portrait / Mobile Cards for Matrix --}}
            <div class="report-cards">
                @foreach (['Aircon', 'Basic'] as $type)
                    @php($row = $byType[$type] ?? ['total' => 0, 'statuses' => ['Rented' => 0, 'Referred' => 0, 'Waiting' => 0, 'Declined' => 0]])
                    <div class="report-card">
                        <div class="report-card-head">
                            <div class="report-card-title">{{ $type }} Room Demand</div>
                            <span class="tag t-sub">{{ $row['total'] }} Total</span>
                        </div>
                        <div class="report-card-grid">
                            <div class="report-card-metric">
                                <span class="lbl">Rented:</span>
                                <span class="val" style="color: var(--green);">{{ $row['statuses']['Rented'] ?? 0 }}</span>
                            </div>
                            <div class="report-card-metric">
                                <span class="lbl">Referred:</span>
                                <span class="val" style="color: var(--amber);">{{ $row['statuses']['Referred'] ?? 0 }}</span>
                            </div>
                            <div class="report-card-metric">
                                <span class="lbl">Waiting:</span>
                                <span class="val" style="color: var(--navy);">{{ $row['statuses']['Waiting'] ?? 0 }}</span>
                            </div>
                            <div class="report-card-metric">
                                <span class="lbl">Declined:</span>
                                <span class="val" style="color: var(--red);">{{ $row['statuses']['Declined'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="grid2 report-grid" style="margin-top: 18px;">
                <div>
                    <h3 class="sub-head">Most asked for</h3>
                    <table class="sum">
                        @foreach ($ranked as $type => $n)
                            <tr>
                                <td><b>{{ $loop->iteration }}.</b> {{ $type }} Setup</td>
                                <td class="r"><b>{{ $n }}</b> ({{ round($n * 100 / $total) }}%)</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div>
                    <h3 class="sub-head">Inquiry Intake Channels</h3>
                    <table class="sum">
                        @foreach ($channels as $channel => $n)
                            <tr>
                                <td>{{ $channel }} Intake</td>
                                <td class="r"><b>{{ $n }}</b></td>
                            </tr>
                        @endforeach
                        <tr>
                            <td>Via Representative</td>
                            <td class="r"><b>{{ $via_representative }}</b></td>
                        </tr>
                    </table>
                </div>
            </div>
        @endif

        <h3 class="sub-head" style="margin-top: 24px;">
            Current Room Availability vs. Active Demand
            <span class="muted" style="font-size: 13px; font-weight: normal;">(Prospects currently in waiting queue vs. physical room inventory)</span>
        </h3>
        <div class="table-wrap report-table-wrap">
            <table class="fit">
                <thead>
                    <tr>
                        <th>Room Setup</th>
                        <th class="r">Active Waiting List</th>
                        <th class="r">Total Rooms</th>
                        <th class="r">Vacant Rooms</th>
                        <th class="r">Occupied Rooms</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($now as $type => $row)
                        <tr>
                            <td><b>{{ $type === 'Any' ? 'Any (All Inventory)' : $type.' Room' }}</b></td>
                            <td class="r" style="color: var(--navy); font-weight: 700;">{{ $row['waiting'] }}</td>
                            <td class="r">{{ $row['rooms'] }}</td>
                            <td class="r" style="color: var(--green); font-weight: 600;">{{ $row['vacant'] }}</td>
                            <td class="r">{{ $row['occupied'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards for Availability vs Demand --}}
        <div class="report-cards">
            @foreach ($now as $type => $row)
                <div class="report-card">
                    <div class="report-card-head">
                        <div class="report-card-title">{{ $type === 'Any' ? 'Overall Capacity' : $type.' Room Capacity' }}</div>
                        <span class="tag t-blue">{{ $row['waiting'] }} Waiting</span>
                    </div>
                    <div class="report-card-grid">
                        <div class="report-card-metric">
                            <span class="lbl">Total Units:</span>
                            <span class="val">{{ $row['rooms'] }}</span>
                        </div>
                        <div class="report-card-metric">
                            <span class="lbl">Vacant:</span>
                            <span class="val" style="color: var(--green);">{{ $row['vacant'] }}</span>
                        </div>
                        <div class="report-card-metric">
                            <span class="lbl">Occupied:</span>
                            <span class="val">{{ $row['occupied'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
