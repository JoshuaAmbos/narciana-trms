@extends('layouts.app')

@section('title', 'Concern History Per Room')

@section('content')
    <form method="GET" action="{{ route('reports.concerns') }}" class="panel no-print report-filters" novalidate>
        <div class="f @error('room') has-err @enderror">
            <label for="room">Room Or Location</label>
            <select id="room" name="room">
                <option value="">All Rooms And Shared Areas</option>
                @foreach ($rooms as $r)
                    <option value="{{ $r->room_id }}" @selected($room === $r->room_id)>Room {{ $r->room_no }}</option>
                @endforeach
                <option value="shared" @selected($room === 'shared')>Shared Area</option>
            </select>
            @error('room') <div class="err">{{ $message }}</div> @enderror
        </div>
        @include('reports._range')
        @include('reports._actions')
    </form>

    <div class="panel printable reports-container">
        @include('reports._head', [
            'title' => 'Concern History Per Room',
            'period' => ($from || $to) ? (($from?->format('j M Y') ?? 'Start') . ' to ' . ($to?->format('j M Y') ?? 'Today')) : 'All Recorded Dates',
        ])

        {{-- Maintenance KPI Cards --}}
        <div class="cards">
            <div class="card">
                <div class="n">{{ $count }}</div>
                <div class="l">Total {{ Str::plural('Concern', $count) }}</div>
            </div>
            <div class="card">
                <div class="n blue">{{ $open }}</div>
                <div class="l">Unresolved Issues</div>
            </div>
            <div class="card">
                <div class="n">@peso($cost)</div>
                <div class="l">Total Repair Cost</div>
            </div>
            <div class="card">
                <div class="n">@peso($charged)</div>
                <div class="l">Charged To Tenants</div>
            </div>
        </div>

        @forelse ($groups as $g)
            <h3 class="sub-head" style="margin-top: 22px;">
                {{ $g['label'] }}
                <span class="muted" style="font-size: 13px; font-weight: normal;">({{ $g['concerns']->count() }} {{ Str::plural('Ticket', $g['concerns']->count()) }})</span>
            </h3>

            @if ($g['repeated']->isNotEmpty())
                <div class="alert alert-warn" style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding: 10px 14px;">
                    <x-icon name="alert-triangle" size="18" />
                    <div>
                        <b>Repeated issue:</b>
                        @foreach ($g['repeated'] as $category => $n){{ $loop->first ? '' : ', ' }}{{ $category }} reported {{ $n }} times @endforeach
                        — consider permanent facility replacement.
                    </div>
                </div>
            @endif

            {{-- Landscape Tablet & Desktop Ledger Table --}}
            <div class="table-wrap report-table-wrap">
                <table class="fit">
                    <thead>
                        <tr>
                            <th>Reported Date</th>
                            <th>Concern Details &amp; Resolution</th>
                            <th>Ticket Status</th>
                            <th class="r">Repair Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($g['concerns'] as $c)
                            <tr class="report-row-clickable"
                                onclick="window.location='{{ route('concerns.show', $c) }}'"
                                tabindex="0"
                                role="button"
                                aria-label="View concern ticket for {{ $c->category }}">
                                <td class="nowrap">
                                    <b>{{ $c->reported_at->format('j M Y') }}</b>
                                    <span class="sub">{{ $c->priority }} · {{ $c->channel }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('concerns.show', $c) }}" style="font-weight: 700;">{{ $c->category }}</a>:
                                    <span>{{ $c->description }}</span>
                                    @if ($c->resolution)
                                        <div class="sub" style="color: var(--green); font-weight: 500; margin-top: 2px;">
                                            Resolution: {{ $c->resolution }}
                                        </div>
                                    @endif
                                    @if ($c->lease)
                                        <span class="sub">Tenant: {{ $c->lease->tenant->full_name }}</span>
                                    @endif
                                </td>
                                <td>
                                    <x-concern-status :concern="$c" />
                                    @if ($c->resolved_at)
                                        <span class="sub nowrap">{{ $c->resolved_at->format('j M Y') }}</span>
                                    @endif
                                </td>
                                <td class="money">
                                    @if ($c->cost !== null)
                                        <b>@peso($c->cost)</b>
                                    @else
                                        <span class="muted">–</span>
                                    @endif
                                    @if ($c->charged_to_tenant)
                                        <span class="sub" style="color: var(--amber);">
                                            Charged{{ $c->bill ? ', Billed '.$c->bill->due_date->format('j M') : ', Pending Bill' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr class="tot-row">
                            <td colspan="3">Location Total Cost (Tenant-Charged: @peso($g['charged']))</td>
                            <td class="money">@peso($g['cost'])</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Portrait Tablet (~712px - 800px) & Mobile Stacked Cards --}}
            <div class="report-cards">
                @foreach ($g['concerns'] as $c)
                    <div class="report-card"
                         onclick="window.location='{{ route('concerns.show', $c) }}'"
                         tabindex="0"
                         role="button"
                         aria-label="View concern ticket">
                        <div class="report-card-head">
                            <div>
                                <div class="report-card-title">{{ $c->category }}</div>
                                <div class="report-card-sub">
                                    {{ $c->reported_at->format('j M Y') }} · {{ $c->priority }}
                                    @if ($c->lease) · {{ $c->lease->tenant->full_name }} @endif
                                </div>
                            </div>
                            <div class="report-card-badge">
                                <x-concern-status :concern="$c" />
                                <span class="report-chevron"><x-icon name="chevron-right" size="18" /></span>
                            </div>
                        </div>
                        <div class="report-card-desc">{{ $c->description }}</div>
                        @if ($c->resolution)
                            <div style="font-size: 12px; color: #1e4620; background: #eef7ee; padding: 6px 10px; border-radius: 6px;">
                                <b>Resolution:</b> {{ $c->resolution }}
                            </div>
                        @endif
                        <div class="report-card-foot">
                            <span>
                                @if ($c->charged_to_tenant)
                                    <span class="tag t-amber">Charged to Tenant</span>
                                @else
                                    <span class="muted">Property Expense</span>
                                @endif
                            </span>
                            <b>@if ($c->cost !== null) @peso($c->cost) @else – @endif</b>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="muted">No concerns recorded in this period.</p>
        @endforelse
    </div>
@endsection
