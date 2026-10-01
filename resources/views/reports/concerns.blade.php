@extends('layouts.app')

@section('title', 'Concern history')

@section('content')
    <form method="GET" action="{{ route('reports.concerns') }}" class="panel no-print report-filters" novalidate>
        <div class="f @error('room') has-err @enderror">
            <label for="room">Room</label>
            <select id="room" name="room">
                <option value="">All rooms and shared areas</option>
                @foreach ($rooms as $r)
                    <option value="{{ $r->room_id }}" @selected($room === $r->room_id)>Room {{ $r->room_no }}</option>
                @endforeach
                <option value="shared" @selected($room === 'shared')>Shared area</option>
            </select>
            @error('room') <div class="err">{{ $message }}</div> @enderror
        </div>
        @include('reports._range')
        @include('reports._actions')
    </form>

    <div class="panel printable">
        @include('reports._head', [
            'title' => 'Concern history per room',
            'period' => ($from || $to) ? ($from?->format('j M Y') ?? 'Start').' to '.($to?->format('j M Y') ?? 'today') : 'All dates',
        ])

        <div class="cards">
            <div class="card"><div class="n">{{ $count }}</div><div class="l">{{ Str::plural('Concern', $count) }}</div></div>
            <div class="card"><div class="n blue">{{ $open }}</div><div class="l">Not resolved yet</div></div>
            <div class="card"><div class="n">@peso($cost)</div><div class="l">Repair cost</div></div>
            <div class="card"><div class="n">@peso($charged)</div><div class="l">Charged to tenants</div></div>
        </div>

        @forelse ($groups as $g)
            <h3 class="sub-head">{{ $g['label'] }}
                <span class="muted">{{ $g['concerns']->count() }} {{ Str::plural('concern', $g['concerns']->count()) }}</span></h3>
            @if ($g['repeated']->isNotEmpty())
                <div class="repeat-note">Repeated issue:
                    @foreach ($g['repeated'] as $category => $n){{ $loop->first ? '' : ', ' }}{{ $category }} reported {{ $n }} times @endforeach</div>
            @endif
            <div class="table-wrap">
                <table class="fit">
                    <tr><th>Reported</th><th>Concern</th><th>Status</th><th class="r">Cost</th></tr>
                    @foreach ($g['concerns'] as $c)
                        <tr>
                            <td class="nowrap">{{ $c->reported_at->format('j M Y') }}<span class="sub">{{ $c->priority }} · {{ $c->channel }}</span></td>
                            <td><a href="{{ route('concerns.show', $c) }}">{{ $c->category }}</a>: {{ $c->description }}
                                @if ($c->resolution)<span class="sub">Resolved: {{ $c->resolution }}</span>@endif
                                @if ($c->lease)<span class="sub">Tenant: {{ $c->lease->tenant->full_name }}</span>@endif</td>
                            <td><x-concern-status :concern="$c" />
                                @if ($c->resolved_at)<span class="sub nowrap">{{ $c->resolved_at->format('j M Y') }}</span>@endif</td>
                            <td class="money">@if ($c->cost !== null)@peso($c->cost)@else<span class="muted">–</span>@endif
                                @if ($c->charged_to_tenant)<span class="sub">charged{{ $c->bill ? ', billed '.$c->bill->due_date->format('j M') : ', not billed yet' }}</span>@endif</td>
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td colspan="3">Total cost (charged to tenant: @peso($g['charged']))</td><td class="money">@peso($g['cost'])</td></tr>
                </table>
            </div>
        @empty
            <p class="muted">No concerns in this period.</p>
        @endforelse
    </div>
@endsection
