@extends('layouts.app')

@section('title', 'Inquiries and room demand')

@section('content')
    <form method="GET" action="{{ route('reports.inquiries') }}" class="panel no-print report-filters" novalidate>
        @include('reports._range')
        @include('reports._actions')
    </form>

    <div class="panel printable">
        @include('reports._head', ['title' => 'Inquiries and room demand', 'period' => $from->format('j M Y').' to '.$to->format('j M Y')])

        <div class="cards">
            <div class="card"><div class="n">{{ $total }}</div><div class="l">{{ Str::plural('Inquiry', $total) }}</div></div>
            <div class="card"><div class="n green">{{ $statusTotals['Rented'] }}</div><div class="l">Rented</div></div>
            <div class="card"><div class="n amber">{{ $referred }}</div><div class="l">Referred elsewhere</div></div>
            <div class="card"><div class="n blue">{{ $statusTotals['Waiting'] }}</div><div class="l">Still waiting</div></div>
        </div>

        @if ($total === 0)
            <p class="muted">No inquiries in this period.</p>
        @else
            <h3 class="sub-head">By room type asked for</h3>
            <div class="table-wrap">
                <table class="fit">
                    <tr><th>Room type</th><th class="r">Inquiries</th>@foreach ($statusTotals as $status => $n)<th class="r">{{ $status }}</th>@endforeach</tr>
                    @foreach ($byType as $type => $row)
                        <tr>
                            <td><b>{{ $type }}</b></td>
                            <td class="r"><b>{{ $row['total'] }}</b></td>
                            @foreach ($row['statuses'] as $n)<td class="r">{{ $n }}</td>@endforeach
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td>Total</td><td class="r">{{ $total }}</td>@foreach ($statusTotals as $n)<td class="r">{{ $n }}</td>@endforeach</tr>
                </table>
            </div>

            <div class="grid2 report-grid">
                <div>
                    <h3 class="sub-head">Most asked for</h3>
                    <table class="sum">
                        @foreach ($ranked as $type => $n)
                            <tr><td>{{ $loop->iteration }}. {{ $type }}</td><td class="r">{{ $n }} ({{ round($n * 100 / $total) }}%)</td></tr>
                        @endforeach
                    </table>
                </div>
                <div>
                    <h3 class="sub-head">How they asked</h3>
                    <table class="sum">
                        @foreach ($channels as $channel => $n)
                            <tr><td>{{ $channel }}</td><td class="r">{{ $n }}</td></tr>
                        @endforeach
                        <tr><td>Through a representative</td><td class="r">{{ $via_representative }}</td></tr>
                    </table>
                </div>
            </div>
        @endif

        <h3 class="sub-head">Demand today <span class="muted">prospects waiting (any date) and rooms</span></h3>
        <div class="table-wrap">
            <table class="fit">
                <tr><th>Room type</th><th class="r">Waiting</th><th class="r">Rooms</th><th class="r">Vacant</th><th class="r">Occupied</th></tr>
                @foreach ($now as $type => $row)
                    <tr>
                        <td><b>{{ $type === 'Any' ? 'Any (all rooms)' : $type }}</b></td>
                        <td class="r">{{ $row['waiting'] }}</td>
                        <td class="r">{{ $row['rooms'] }}</td>
                        <td class="r">{{ $row['vacant'] }}</td>
                        <td class="r">{{ $row['occupied'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endsection
