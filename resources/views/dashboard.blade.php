@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-shell w-full max-w-full lg:max-w-7xl mx-auto">
    {{-- Part 1: Clean Operations Dashboard Header --}}
    <div class="dashboard-header mb-6">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Operations Dashboard</h1>
        <p class="text-xs text-slate-500 mt-0.5">Live occupancy, billing alerts, and facility status.</p>
    </div>

    {{-- Part 2: KPI Cards Grid (Strict Semantic Palette & Inverted Hierarchy) --}}
    @php
        $showRefundsCard = $isOwner;
        $gridModifier = !$staff ? 'caretaker-grid' : ($showRefundsCard ? 'has-6' : 'has-5');
    @endphp
    <div class="kpi-cards-grid {{ $gridModifier }} grid grid-cols-1 sm:grid-cols-2 {{ $staff ? ($isOwner ? 'lg:grid-cols-3 xl:grid-cols-6' : 'lg:grid-cols-3 xl:grid-cols-5') : 'lg:grid-cols-2' }} gap-4 mb-6">
        @if ($staff)
            {{-- 1. Bills Due in Next 7 Days (Neutral) --}}
            <a href="{{ route('payments.index') }}" class="kpi-metric-card bg-white p-4 rounded-lg border border-slate-200 shadow-sm flex flex-col justify-between hover:border-slate-300 transition text-inherit" aria-label="Bills due in next 7 days">
                <div class="kpi-metric-top flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    <span>Due in Next 7 Days</span>
                    <span class="kpi-icon-box p-1.5 bg-slate-100 text-slate-600 rounded-md">
                        <x-icon name="calendar" size="16" />
                    </span>
                </div>
                <div class="kpi-metric-value text-2xl font-bold text-slate-900 leading-none">{{ $billsDueSoonCount }}</div>
                <div class="kpi-metric-subtext text-xs text-slate-500 mt-2 font-medium">₱{{ number_format($billsDueSoonTotal, 2) }} pending collection</div>
            </a>

            {{-- 2. Overdue Accounts (Actionable / Semantic Alert) --}}
            <a href="{{ route('payments.index') }}" class="kpi-metric-card {{ $overdueCount > 0 ? 'alert-rose border-rose-300 ring-1 ring-rose-200' : 'border-slate-200' }} bg-white p-4 rounded-lg border shadow-sm flex flex-col justify-between hover:border-rose-400 transition text-inherit" aria-label="Overdue accounts">
                <div class="kpi-metric-top flex items-center justify-between text-xs font-semibold {{ $overdueCount > 0 ? 'text-rose-700' : 'text-slate-500' }} uppercase tracking-wider mb-2">
                    <span>Overdue Accounts</span>
                    <span class="kpi-icon-box p-1.5 {{ $overdueCount > 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600' }} rounded-md">
                        <x-icon name="alert-circle" size="16" />
                    </span>
                </div>
                <div class="kpi-metric-value text-2xl font-bold {{ $overdueCount > 0 ? 'text-rose-700' : 'text-slate-900' }} leading-none">{{ $overdueCount }}</div>
                <div class="kpi-metric-subtext text-xs {{ $overdueCount > 0 ? 'text-rose-600' : 'text-slate-500' }} mt-2 font-medium">
                    {{ $overdueCount > 0 ? '₱' . number_format($overdueAmount, 2) . ' past grace period' : 'All accounts settled' }}
                </div>
            </a>

            {{-- 3. Meter Readings Needed (Neutral) --}}
            <a href="{{ route('billing.index') }}" class="kpi-metric-card bg-white p-4 rounded-lg border border-slate-200 shadow-sm flex flex-col justify-between hover:border-slate-300 transition text-inherit" aria-label="Meter readings needed">
                <div class="kpi-metric-top flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    <span>Meter Readings Needed</span>
                    <span class="kpi-icon-box p-1.5 bg-slate-100 text-slate-600 rounded-md">
                        <x-icon name="zap" size="16" />
                    </span>
                </div>
                <div class="kpi-metric-value text-2xl font-bold text-slate-900 leading-none">{{ $readingsNeededCount }}</div>
                <div class="kpi-metric-subtext text-xs text-slate-500 mt-2 font-medium">
                    {{ $readingsNeededCount > 0 ? 'Meter reading needed' : 'All cycles logged' }}
                </div>
            </a>
        @endif

        {{-- 4. Open Concerns (Neutral) --}}
        <a href="{{ route('concerns.index') }}" class="kpi-metric-card bg-white p-4 rounded-lg border border-slate-200 shadow-sm flex flex-col justify-between hover:border-slate-300 transition text-inherit" aria-label="Open concerns">
            <div class="kpi-metric-top flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                <span>Open Concerns</span>
                <span class="kpi-icon-box p-1.5 bg-slate-100 text-slate-600 rounded-md">
                    <x-icon name="wrench" size="16" />
                </span>
            </div>
            <div class="kpi-metric-value text-2xl font-bold text-slate-900 leading-none">{{ $openConcernsCount }}</div>
            <div class="kpi-metric-subtext text-xs text-slate-500 mt-2 font-medium">
                @if ($urgentConcernsCount > 0)
                    Open concerns ({{ $urgentConcernsCount }} urgent)
                @else
                    Open concerns &middot; All routine tickets
                @endif
            </div>
        </a>

        {{-- 5. Vacant Rooms (Neutral) --}}
        <a href="{{ route('rooms.index') }}" class="kpi-metric-card bg-white p-4 rounded-lg border border-slate-200 shadow-sm flex flex-col justify-between hover:border-slate-300 transition text-inherit" aria-label="Vacant rooms">
            <div class="kpi-metric-top flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                <span>Vacant Rooms</span>
                <span class="kpi-icon-box p-1.5 bg-slate-100 text-slate-600 rounded-md">
                    <x-icon name="door-open" size="16" />
                </span>
            </div>
            <div class="kpi-metric-value text-2xl font-bold text-slate-900 leading-none">{{ $vacantRoomsCount }}</div>
            <div class="kpi-metric-subtext text-xs text-slate-500 mt-2 font-medium">Vacant rooms &middot; Available</div>
        </a>

        {{-- 6. Deposit Refunds Awaiting Approval (Owner Only, Actionable / Semantic Alert) --}}
        @if ($isOwner)
            <a href="{{ route('reports.settlements') }}" class="kpi-metric-card {{ $pendingRefundsCount > 0 ? 'alert-amber border-amber-300 ring-1 ring-amber-200' : 'border-slate-200' }} bg-white p-4 rounded-lg border shadow-sm flex flex-col justify-between hover:border-amber-400 transition text-inherit" aria-label="Deposit refunds awaiting approval">
                <div class="kpi-metric-top flex items-center justify-between text-xs font-semibold {{ $pendingRefundsCount > 0 ? 'text-amber-800' : 'text-slate-500' }} uppercase tracking-wider mb-2">
                    <span>Deposit Refunds</span>
                    <span class="kpi-icon-box p-1.5 {{ $pendingRefundsCount > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }} rounded-md">
                        <x-icon name="banknote" size="16" />
                    </span>
                </div>
                <div class="kpi-metric-value text-2xl font-bold {{ $pendingRefundsCount > 0 ? 'text-amber-800' : 'text-slate-900' }} leading-none">{{ $pendingRefundsCount }}</div>
                <div class="kpi-metric-subtext text-xs {{ $pendingRefundsCount > 0 ? 'text-amber-700' : 'text-slate-500' }} mt-2 font-medium">
                    {{ $pendingRefundsCount > 0 ? '₱' . number_format($pendingRefundsTotal, 2) . ' awaiting sign-off' : 'None pending' }}
                </div>
            </a>
        @endif
    </div>

    {{-- Part 3: Actionable Priority Queue (Immediate Action Required) --}}
    @if ($staff)
        <div class="action-queue-section mb-8">
            <div class="action-queue-header flex items-center justify-between mb-3">
                <div class="action-queue-title-wrap flex items-center gap-2">
                    <h2 class="action-queue-title text-sm font-bold uppercase tracking-wider text-slate-800">Immediate Action Required</h2>
                    @if ($urgentActionsCount > 0)
                        <span class="action-queue-badge px-2 py-0.5 text-xs font-bold bg-amber-100 text-amber-900 rounded-full">
                            {{ $urgentActionsCount }}
                        </span>
                    @endif
                </div>
            </div>

            @if ($urgentActionsCount > 0)
                <div class="action-queue-card bg-white rounded-lg border border-slate-200 shadow-sm divide-y divide-slate-100">
                    {{-- 0. MOA Clause 8 Breached Leases (Deposit Exhausted) --}}
                    @foreach ($breachedLeases as $breach)
                        <?php $bLease = $breach['lease']; ?>
                        <div class="action-queue-item p-3.5 sm:px-4 sm:py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition border-l-4 border-l-rose-600 bg-rose-50/40">
                            <div class="action-queue-item-left flex items-start sm:items-center gap-3">
                                <span class="action-tag rose inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-rose-600 text-white shrink-0">
                                    [!] Breach: Deposit Exhausted (Eligible for Termination under MOA Clause 8)
                                </span>
                                <div class="action-details">
                                    <span class="action-room text-sm font-bold text-slate-900">Room {{ $bLease->room->room_no }}</span>
                                    <span class="action-tenant text-sm text-slate-600">&middot; {{ $bLease->occupantsDisplay() }}</span>
                                    <span class="action-meta rose text-xs text-rose-700 font-semibold ml-1">
                                        (Arrears: @peso($breach['total_arrears']) &ge; Deposit: @peso($breach['total_deposit']))
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @can('manage-leases')
                                    <form method="POST" action="{{ route('leases.terminate-clause8', $bLease) }}"
                                          onsubmit="return confirm('Terminate lease for Room {{ $bLease->room->room_no }} under MOA Clause 8?\n\nThis will offset the security deposit against unpaid bills, set the lease to Cancelled, deactivate the tenant, and vacate the room immediately.')">
                                        @csrf
                                        <button type="submit" class="action-btn min-h-[44px] sm:min-h-[36px] px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-md flex items-center justify-center shrink-0 shadow-sm transition">
                                            Terminate Lease (Clause 8)
                                        </button>
                                    </form>
                                @endcan
                                <a href="{{ route('leases.show', $bLease) }}" class="action-btn white min-h-[44px] sm:min-h-[36px] px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-md flex items-center justify-center shrink-0">
                                    View MOA
                                </a>
                            </div>
                        </div>
                    @endforeach

                    {{-- 1. Overdue Accounts Items --}}
                    @foreach ($overdueBills as $bill)
                        <div class="action-queue-item p-3.5 sm:px-4 sm:py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition">
                            <div class="action-queue-item-left flex items-start sm:items-center gap-3">
                                <span class="action-tag rose inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-rose-100 text-rose-800 shrink-0">
                                    Overdue
                                </span>
                                <div class="action-details">
                                    <span class="action-room text-sm font-bold text-slate-900">Room {{ $bill->lease->room->room_no }}</span>
                                    <span class="action-tenant text-sm text-slate-600">&middot; {{ $bill->lease->occupantsDisplay() }}</span>
                                    <span class="action-meta rose text-xs text-rose-600 font-medium ml-1">
                                        (₱{{ number_format($bill->total_amount, 2) }} due on {{ \Carbon\Carbon::parse($bill->due_date)->format('M d') }})
                                    </span>
                                </div>
                            </div>
                            <a href="{{ route('billing.index') }}" class="action-btn dark min-h-[44px] sm:min-h-[36px] px-3.5 py-1.5 bg-slate-900 hover:bg-black text-white text-xs font-semibold rounded-md flex items-center justify-center shrink-0">
                                Record Payment
                            </a>
                        </div>
                    @endforeach

                    {{-- 2. Vacating Rooms Items --}}
                    @foreach ($vacatingRooms as $room)
                        <div class="action-queue-item p-3.5 sm:px-4 sm:py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition">
                            <div class="action-queue-item-left flex items-start sm:items-center gap-3">
                                <span class="action-tag amber inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-amber-100 text-amber-800 shrink-0">
                                    Turnover
                                </span>
                                <div class="action-details">
                                    <span class="action-room text-sm font-bold text-slate-900">Room {{ $room->room_no }}</span>
                                    <span class="action-tenant text-sm text-slate-600">&middot; {{ $room->activeLease?->occupantsDisplay() ?? $room->activeLease?->tenant?->full_name ?? 'Tenant' }}</span>
                                    <span class="action-meta slate text-xs text-slate-500 font-medium ml-1">
                                        ({{ $vacatingRooms->count() }} Room Turnover Alert &middot; Vacating on {{ \Carbon\Carbon::parse($room->expected_vacant_date)->format('M d, Y') }})
                                    </span>
                                </div>
                            </div>
                            <a href="{{ $room->activeLease ? route('leases.show', $room->activeLease) : route('rooms.index') }}" class="action-btn white min-h-[44px] sm:min-h-[36px] px-3.5 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-md flex items-center justify-center shrink-0">
                                View Turnover
                            </a>
                        </div>
                    @endforeach

                    {{-- 3. Meter Readings Needed Items --}}
                    @foreach ($readingsNeeded as $row)
                        <?php $rLease = $row['lease']; $rDue = $row['due_date']; ?>
                        <div class="action-queue-item p-3.5 sm:px-4 sm:py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition">
                            <div class="action-queue-item-left flex items-start sm:items-center gap-3">
                                <span class="action-tag sky inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-sky-100 text-sky-800 shrink-0">
                                    Meter Reading
                                </span>
                                <div class="action-details">
                                    <span class="action-room text-sm font-bold text-slate-900">Room {{ $rLease->room->room_no }}</span>
                                    <span class="action-tenant text-sm text-slate-600">&middot; {{ $rLease->occupantsDisplay() }}</span>
                                    <span class="action-meta sky text-xs text-sky-700 font-medium ml-1">
                                        ({{ $readingsNeeded->count() }} Meter Reading Needed &middot; Cycle due {{ \Carbon\Carbon::parse($rDue)->format('M d') }})
                                    </span>
                                </div>
                            </div>
                            <a href="{{ route('bills.create', ['lease' => $rLease->lease_id]) }}" class="action-btn sky min-h-[44px] sm:min-h-[36px] px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-md flex items-center justify-center shrink-0">
                                Enter Reading
                            </a>
                        </div>
                    @endforeach

                    {{-- 4. Pending Deposit Refund (Owner Only) --}}
                    @if ($isOwner)
                        @foreach ($pendingRefundLeases as $refundItem)
                            @php
                                $refLease = is_array($refundItem) ? ($refundItem['lease'] ?? null) : ($refundItem->lease ?? $refundItem);
                                $refRoomNo = is_array($refundItem) ? ($refundItem['room_no'] ?? $refLease?->room?->room_no) : ($refundItem->room->room_no ?? $refLease?->room?->room_no);
                                $refTenantName = is_array($refundItem) ? ($refundItem['tenant_name'] ?? $refLease?->tenant?->full_name) : ($refundItem->tenant->full_name ?? $refLease?->tenant?->full_name);
                                $refAmount = is_array($refundItem) ? ($refundItem['net_refund'] ?? 0) : ($refundItem->settlement->refund_amount ?? $refundItem->net_refund ?? 0);
                            @endphp
                            <div class="action-queue-item p-3.5 sm:px-4 sm:py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition">
                                <div class="action-queue-item-left flex items-start sm:items-center gap-3">
                                    <span class="action-tag blue inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-blue-100 text-blue-800 shrink-0">
                                        Refund Sign-off
                                    </span>
                                    <div class="action-details">
                                        <span class="action-room text-sm font-bold text-slate-900">Room {{ $refRoomNo }}</span>
                                        <span class="action-tenant text-sm text-slate-600">&middot; {{ $refTenantName }}</span>
                                        <span class="action-meta blue text-xs text-blue-700 font-medium ml-1">
                                            (₱{{ number_format($refAmount, 2) }} awaiting sign-off)
                                        </span>
                                    </div>
                                </div>
                                @if ($refLease)
                                    <a href="{{ route('leases.show', $refLease) }}" class="action-btn blue min-h-[44px] sm:min-h-[36px] px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-md flex items-center justify-center shrink-0">
                                        Approve Refund
                                    </a>
                                @else
                                    <a href="{{ route('reports.settlements') }}" class="action-btn blue min-h-[44px] sm:min-h-[36px] px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-md flex items-center justify-center shrink-0">
                                        Approve Refund
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            @else
                <div class="action-queue-empty p-4 bg-white rounded-lg border border-slate-200 text-center text-xs text-slate-500">
                    All operations up to date. No pending delinquency, turnovers, or approvals.
                </div>
            @endif
        </div>
    @endif

    {{-- Financial Section: Due and overdue this week (Owner & Manager only) --}}
    @if ($staff)
        <div class="panel mb-6">
            <h2>Due and overdue this week <a href="{{ route('payments.index') }}" class="btn sec">Payments</a></h2>
            @if ($toCollect->isEmpty())
                <p class="muted">No bills due in the next 7 days and nothing overdue.</p>
            @else
                {{-- Landscape Tablet & Desktop Ledger Table --}}
                <div class="table-wrap dashboard-table-view">
                    <table class="fit">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>Tenant</th>
                                <th>Due date</th>
                                <th class="r">Amount today</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($toCollect as $row)
                                @php $b = $row['bill']; @endphp
                                <tr class="clickable-row"
                                    onclick="window.location='{{ route('billing.index') }}'"
                                    tabindex="0"
                                    role="button"
                                    aria-label="View billing details for Room {{ $b->lease->room->room_no }}">
                                    <td><b>{{ $b->lease->room->room_no }}</b></td>
                                    <td>
                                        <div class="occupant-display font-medium">
                                            {{ $b->lease->occupantsDisplay() }}
                                        </div>
                                    </td>
                                    <td class="nowrap">{{ $b->due_date->format('j M Y') }}</td>
                                    <td class="money">
                                        @peso($row['total'])
                                        @if ($row['penalty'] > 0)
                                            <span class="sub">incl. penalty @peso($row['penalty'])</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['days_late'] > 0)
                                            <span class="tag t-red">Overdue {{ $row['days_late'] }} {{ Str::plural('day', $row['days_late']) }}</span>
                                        @elseif ($row['days_to_due'] > 0)
                                            <span class="tag t-amber">Due in {{ $row['days_to_due'] }} {{ Str::plural('day', $row['days_to_due']) }}</span>
                                        @elseif ($row['days_to_due'] === 0)
                                            <span class="tag t-amber">Due today</span>
                                        @elseif (!empty($row['in_grace']))
                                            <span class="tag t-amber">Grace Period (Day {{ $row['grace_day'] }} of {{ $row['grace_days'] }})</span>
                                        @else
                                            <span class="tag t-amber">Grace Period</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Portrait Tablet & Mobile Cards --}}
                <div class="dashboard-mobile-cards">
                    @foreach ($toCollect as $row)
                        @php $b = $row['bill']; @endphp
                        <div class="dashboard-touch-card clickable-row"
                             onclick="window.location='{{ route('billing.index') }}'"
                             tabindex="0"
                             role="button"
                             aria-label="View billing for Room {{ $b->lease->room->room_no }}">
                            <div class="touch-card-header">
                                <span class="font-bold">Room {{ $b->lease->room->room_no }}</span>
                                @if ($row['days_late'] > 0)
                                    <span class="tag t-red">Overdue {{ $row['days_late'] }} {{ Str::plural('day', $row['days_late']) }}</span>
                                @elseif ($row['days_to_due'] > 0)
                                    <span class="tag t-amber">Due in {{ $row['days_to_due'] }} {{ Str::plural('day', $row['days_to_due']) }}</span>
                                @elseif ($row['days_to_due'] === 0)
                                    <span class="tag t-amber">Due today</span>
                                @elseif (!empty($row['in_grace']))
                                    <span class="tag t-amber">Grace Period (Day {{ $row['grace_day'] }} of {{ $row['grace_days'] }})</span>
                                @else
                                    <span class="tag t-amber">Grace Period</span>
                                @endif
                            </div>
                            <div class="touch-card-body">
                                <div>
                                    <div class="font-medium text-slate-800">{{ $b->lease->occupantsDisplay() }}</div>
                                    <div class="sub text-xs text-muted">Due {{ $b->due_date->format('j M Y') }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-slate-900">@peso($row['total'])</div>
                                    @if ($row['penalty'] > 0)
                                        <div class="sub text-xs red font-medium">+@peso($row['penalty']) penalty</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Leases Due Soon Without a Bill --}}
            @if ($unbilled->isNotEmpty())
                <h3 class="sub-head mt-4">Due soon, no bill yet</h3>
                <div class="table-wrap dashboard-table-view">
                    <table class="fit">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>Tenant</th>
                                <th>Bill due</th>
                                <th class="r"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unbilled as $row)
                                <tr>
                                    <td><b>{{ $row['lease']->room->room_no }}</b></td>
                                    <td>
                                        <div class="occupant-display font-medium">
                                            {{ $row['lease']->occupantsDisplay() }}
                                        </div>
                                    </td>
                                    <td class="nowrap">{{ $row['due_date']->format('j M Y') }}</td>
                                    <td class="actions r">
                                        <a href="{{ route('bills.create', ['lease' => $row['lease']->lease_id]) }}" class="btn">Generate now</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile View for Unbilled Leases --}}
                <div class="dashboard-mobile-cards">
                    @foreach ($unbilled as $row)
                        <div class="dashboard-touch-card">
                            <div class="touch-card-header">
                                <span class="font-bold">Room {{ $row['lease']->room->room_no }}</span>
                                <span class="tag t-amber">Due {{ $row['due_date']->format('j M') }}</span>
                            </div>
                            <div class="touch-card-body">
                                <div>
                                    <div class="font-medium">{{ $row['lease']->occupantsDisplay() }}</div>
                                    <div class="sub text-xs text-muted">{{ $row['lease']->room->room_type }}</div>
                                </div>
                                <div>
                                    <a href="{{ route('bills.create', ['lease' => $row['lease']->lease_id]) }}" class="btn">Generate now</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Sub-Meter Readings Needed Operational Alert Section --}}
            @if (isset($readingsNeeded) && $readingsNeeded->isNotEmpty())
                <div class="submeter-notice-box mt-4">
                    <div class="submeter-notice-head">
                        <x-icon name="zap" size="16" class="text-sky-600" />
                        <span class="font-semibold text-sky-900">Sub-Meter Readings Needed</span>
                        <span class="sub text-xs text-sky-700">({{ $readingsNeeded->count() }} due within 7 days)</span>
                    </div>
                    <div class="table-wrap dashboard-table-view">
                        <table class="fit">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Occupants</th>
                                    <th>Billing Due</th>
                                    <th class="r">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($readingsNeeded as $row)
                                    <tr class="clickable-row" onclick="window.location='{{ route('bills.create', ['lease' => $row['lease']->lease_id]) }}'">
                                        <td><b>{{ $row['lease']->room->room_no }}</b> <span class="tag t-blue text-xs">Aircon</span></td>
                                        <td>{{ $row['lease']->occupantsDisplay() }}</td>
                                        <td class="nowrap">{{ $row['due_date']->format('j M Y') }}</td>
                                        <td class="r actions">
                                            <a href="{{ route('bills.create', ['lease' => $row['lease']->lease_id]) }}" class="btn sec" style="padding: 4px 10px; font-size: 13px;">Log reading &amp; bill</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- The Two De-cluttered Sub-Panels: Active Concerns & Rooms Overview --}}
    <div class="grid2 stack-tablet">
        {{-- Panel 1: Active Concerns Panel --}}
        <div class="panel dashboard-subpanel" id="concerns-subpanel">
            <h2>Open concerns <a href="{{ route('concerns.create') }}" class="btn sec">+ Log concern</a></h2>

            @if ($concerns->isEmpty())
                <p class="muted">No open concerns.</p>
            @else
                {{-- Desktop / Landscape Tablet View --}}
                <div class="table-wrap dashboard-table-view">
                    <table class="fit" id="concerns-table">
                        <thead>
                            <tr>
                                <th>Reported</th>
                                <th>Where</th>
                                <th>Concern</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($concerns as $index => $concern)
                                <tr class="clickable-row concern-row {{ $concern->priority === 'Urgent' ? 'row-urgent' : '' }}"
                                    data-index="{{ $index }}"
                                    onclick="window.location='{{ route('concerns.show', $concern) }}'"
                                    tabindex="0"
                                    role="button"
                                    aria-label="View concern ticket #{{ $concern->concern_id }}">
                                    <td class="nowrap">{{ $concern->reported_at->format('j M, g:i A') }}</td>
                                    <td><b>{{ $concern->room?->room_no ?? 'Shared area' }}</b></td>
                                    <td>
                                        <a href="{{ route('concerns.show', $concern) }}" onclick="event.stopPropagation()">
                                            {{ Str::limit($concern->description, 50) }}
                                        </a>
                                        <div class="sub">{{ $concern->category }} &middot; {{ $concern->assignedTo->role }}</div>
                                    </td>
                                    <td>
                                        <x-concern-status :concern="$concern" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Portrait Tablet & Mobile Cards View --}}
                <div class="dashboard-mobile-cards" id="concerns-mobile-list">
                    @foreach ($concerns as $index => $concern)
                        <div class="dashboard-touch-card clickable-row concern-mobile-card {{ $concern->priority === 'Urgent' ? 'touch-card-urgent' : '' }}"
                             data-index="{{ $index }}"
                             onclick="window.location='{{ route('concerns.show', $concern) }}'"
                             tabindex="0"
                             role="button"
                             aria-label="View concern ticket #{{ $concern->concern_id }}">
                            <div class="touch-card-header">
                                <span class="font-bold">{{ $concern->room?->room_no ? 'Room '.$concern->room->room_no : 'Shared area' }}</span>
                                <x-concern-status :concern="$concern" />
                            </div>
                            <div class="touch-card-body">
                                <div>
                                    <div class="text-sm font-medium text-slate-800">{{ Str::limit($concern->description, 60) }}</div>
                                    <div class="sub text-xs text-muted">{{ $concern->category }} &middot; {{ $concern->assignedTo->role }}</div>
                                </div>
                                <div class="text-right text-xs text-muted nowrap">
                                    {{ $concern->reported_at->format('j M, g:i A') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination Controls for Concerns --}}
                <div class="panel-pagination-bar" id="concerns-pagination">
                    <span class="sub text-xs" id="concerns-page-label">Showing 1&ndash;5 of {{ $concerns->count() }}</span>
                    <div class="pagination-actions">
                        <button type="button" class="btn sec pagination-btn" id="concerns-prev-btn" disabled>&larr; Prev</button>
                        <button type="button" class="btn sec pagination-btn" id="concerns-next-btn">Next &rarr;</button>
                    </div>
                </div>
            @endif
        </div>

        {{-- Panel 2: Rooms Overview Panel --}}
        <div class="panel dashboard-subpanel" id="rooms-subpanel">
            <h2>Rooms <a href="{{ route('rooms.index') }}" class="btn sec">Rooms</a></h2>

            {{-- Quick Status Filter Chips --}}
            <div class="rooms-chip-filters" role="tablist" aria-label="Room status filter">
                <button type="button" class="room-chip on" data-status="All">
                    All <span class="chip-count">({{ $rooms->count() }})</span>
                </button>
                <button type="button" class="room-chip" data-status="Vacant">
                    Vacant <span class="chip-count">({{ $vacantRoomsCount }})</span>
                </button>
                <button type="button" class="room-chip" data-status="Vacating">
                    Vacating <span class="chip-count">({{ $vacatingRooms->count() }})</span>
                </button>
                <button type="button" class="room-chip" data-status="Occupied">
                    Occupied <span class="chip-count">({{ $occupiedRoomsCount }})</span>
                </button>
            </div>

            {{-- Desktop / Landscape Tablet View --}}
            <div class="table-wrap dashboard-table-view">
                <table class="fit" id="rooms-table">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Status</th>
                            @if ($staff)
                                <th>Occupants</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rooms as $room)
                            @php
                                $isVacating = $room->status === 'Occupied' && $room->expected_vacant_date !== null;
                                $filterStatus = $isVacating ? 'Vacating' : $room->status;
                                $activeLease = $room->relationLoaded('leases') ? $room->leases->firstWhere('status', 'Active') : null;
                            @endphp
                            <tr class="clickable-row room-row"
                                data-room-status="{{ $filterStatus }}"
                                onclick="window.location='{{ route('rooms.index', ['search' => $room->room_no]) }}'"
                                tabindex="0"
                                role="button"
                                aria-label="View room {{ $room->room_no }} details">
                                <td><b>{{ $room->room_no }}</b></td>
                                <td>{{ $room->room_type }}</td>
                                <td>
                                    <x-room-status :room="$room" />
                                    @if ($isVacating)
                                        <span class="tag t-amber" title="Expected move-out: {{ $room->expected_vacant_date->format('j M Y') }}">
                                            Vacating ({{ $room->expected_vacant_date->format('j M') }})
                                        </span>
                                    @endif
                                </td>
                                @if ($staff)
                                    <td>
                                        @if ($activeLease)
                                            <div class="occupant-display font-medium">
                                                {{ $activeLease->occupantsDisplay() }}
                                            </div>
                                        @else
                                            <span class="muted">&mdash;</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Portrait Tablet & Mobile Cards View --}}
            <div class="dashboard-mobile-cards" id="rooms-mobile-list">
                @foreach ($rooms as $room)
                    @php
                        $isVacating = $room->status === 'Occupied' && $room->expected_vacant_date !== null;
                        $filterStatus = $isVacating ? 'Vacating' : $room->status;
                        $activeLease = $room->relationLoaded('leases') ? $room->leases->firstWhere('status', 'Active') : null;
                    @endphp
                    <div class="dashboard-touch-card clickable-row room-mobile-card"
                         data-room-status="{{ $filterStatus }}"
                         onclick="window.location='{{ route('rooms.index', ['search' => $room->room_no]) }}'"
                         tabindex="0"
                         role="button"
                         aria-label="View room {{ $room->room_no }}">
                        <div class="touch-card-header">
                            <span class="font-bold">Room {{ $room->room_no }}</span>
                            <div class="flex items-center gap-1">
                                <x-room-status :room="$room" />
                                @if ($isVacating)
                                    <span class="tag t-amber">Vacating ({{ $room->expected_vacant_date->format('j M') }})</span>
                                @endif
                            </div>
                        </div>
                        <div class="touch-card-body">
                            <div>
                                <span class="sub text-xs text-muted">{{ $room->room_type }} &middot; Max {{ $room->max_occupants }} occupants</span>
                                @if ($staff && $activeLease)
                                    <div class="text-sm font-medium text-slate-800 mt-1">
                                        {{ $activeLease->occupantsDisplay() }}
                                    </div>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="tag t-grey text-xs">View</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination Controls for Rooms --}}
            <div class="panel-pagination-bar" id="rooms-pagination">
                <span class="sub text-xs" id="rooms-page-label">Showing 1&ndash;6 of {{ $rooms->count() }}</span>
                <div class="pagination-actions">
                    <button type="button" class="btn sec pagination-btn" id="rooms-prev-btn" disabled>&larr; Prev</button>
                    <button type="button" class="btn sec pagination-btn" id="rooms-next-btn">Next &rarr;</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Shell & Responsive Layout */
.dashboard-shell {
    display: flex;
    flex-direction: column;
    gap: 16px;
    width: 100%;
}

.dashboard-header {
    margin-bottom: 20px;
}
.dashboard-header h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.025em;
    line-height: 1.25;
}
.dashboard-header p {
    margin: 4px 0 0;
    font-size: 13px;
    color: #64748b;
}

/* KPI Cards Grid */
.kpi-cards-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 14px;
    width: 100%;
    margin-bottom: 24px;
}
.kpi-cards-grid.has-6 {
    grid-template-columns: repeat(6, minmax(0, 1fr));
}
.kpi-cards-grid.caretaker-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (max-width: 1200px) {
    .kpi-cards-grid,
    .kpi-cards-grid.has-6 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 800px) {
    .kpi-cards-grid,
    .kpi-cards-grid.has-6,
    .kpi-cards-grid.caretaker-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 480px) {
    .kpi-cards-grid,
    .kpi-cards-grid.has-6,
    .kpi-cards-grid.caretaker-grid {
        grid-template-columns: 1fr;
    }
}

/* KPI Metric Card */
.kpi-metric-card {
    background: #ffffff;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    text-decoration: none;
    color: inherit;
    min-height: 110px;
    box-sizing: border-box;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.kpi-metric-card:hover, .kpi-metric-card:focus-visible {
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.06);
}

.kpi-metric-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-bottom: 8px;
}

.kpi-icon-box {
    padding: 6px;
    background: #f1f5f9;
    color: #475569;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    flex-shrink: 0;
}
.kpi-icon-box svg {
    display: block;
    width: 16px;
    height: 16px;
}

.kpi-metric-value {
    font-size: 26px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1;
}

.kpi-metric-subtext {
    font-size: 12px;
    color: #64748b;
    margin-top: 8px;
    font-weight: 500;
    line-height: 1.35;
}

/* Semantic Alert Card Styling */
.kpi-metric-card.alert-rose {
    border-color: #fda4af !important;
    box-shadow: 0 0 0 1px #fecdd3, 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.kpi-metric-card.alert-rose:hover {
    border-color: #fb7185 !important;
}
.kpi-metric-card.alert-rose .kpi-metric-top {
    color: #be123c;
}
.kpi-metric-card.alert-rose .kpi-icon-box {
    background: #ffe4e6;
    color: #be123c;
}
.kpi-metric-card.alert-rose .kpi-metric-value {
    color: #be123c;
}
.kpi-metric-card.alert-rose .kpi-metric-subtext {
    color: #e11d48;
}

.kpi-metric-card.alert-amber {
    border-color: #fcd34d !important;
    box-shadow: 0 0 0 1px #fde68a, 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.kpi-metric-card.alert-amber:hover {
    border-color: #fbbf24 !important;
}
.kpi-metric-card.alert-amber .kpi-metric-top {
    color: #92400e;
}
.kpi-metric-card.alert-amber .kpi-icon-box {
    background: #fef3c7;
    color: #92400e;
}
.kpi-metric-card.alert-amber .kpi-metric-value {
    color: #92400e;
}
.kpi-metric-card.alert-amber .kpi-metric-subtext {
    color: #b45309;
}

/* Action Required Queue Section */
.action-queue-section {
    margin-bottom: 24px;
}
.action-queue-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.action-queue-title-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}
.action-queue-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #1e293b;
    margin: 0;
}
.action-queue-badge {
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 700;
    background: #fef3c7;
    color: #78350f;
    border-radius: 9999px;
}

.action-queue-card {
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.action-queue-item {
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid #f1f5f9;
    transition: background-color 0.12s ease;
}
.action-queue-item:last-child {
    border-bottom: none;
}
.action-queue-item:hover {
    background: #f8fafc;
}

@media (max-width: 640px) {
    .action-queue-item {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
}

.action-queue-item-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}
@media (max-width: 640px) {
    .action-queue-item-left {
        align-items: flex-start;
    }
}

.action-tag {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
    line-height: 1.25;
}
.action-tag.rose { background: #ffe4e6; color: #9f1239; }
.action-tag.amber { background: #fef3c7; color: #92400e; }
.action-tag.sky { background: #e0f2fe; color: #0369a1; }
.action-tag.blue { background: #dbeafe; color: #1e40af; }

.action-details {
    font-size: 13.5px;
    line-height: 1.4;
}
.action-room {
    font-weight: 700;
    color: #0f172a;
}
.action-tenant {
    color: #475569;
}
.action-meta {
    font-size: 12px;
    font-weight: 500;
    margin-left: 4px;
}
.action-meta.rose { color: #e11d48; }
.action-meta.slate { color: #64748b; }
.action-meta.sky { color: #0284c7; }
.action-meta.blue { color: #2563eb; }

/* Buttons in queue */
.action-btn {
    min-height: 44px;
    padding: 8px 16px;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    flex-shrink: 0;
    cursor: pointer;
    box-sizing: border-box;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}
@media (min-width: 640px) {
    .action-btn {
        min-height: 36px;
        padding: 6px 14px;
    }
}

.action-btn.dark {
    background: #0f172a;
    color: #ffffff;
    border: 1px solid #0f172a;
}
.action-btn.dark:hover {
    background: #000000;
    border-color: #000000;
}

.action-btn.white {
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.action-btn.white:hover {
    background: #f8fafc;
    border-color: #94a3b8;
}

.action-btn.sky {
    background: #0284c7;
    color: #ffffff;
    border: 1px solid #0284c7;
}
.action-btn.sky:hover {
    background: #0369a1;
    border-color: #0369a1;
}

.action-btn.blue {
    background: #2563eb;
    color: #ffffff;
    border: 1px solid #2563eb;
}
.action-btn.blue:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}

.action-queue-empty {
    padding: 16px 20px;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    text-align: center;
    font-size: 13px;
    color: #64748b;
}

/* Sub-meter notice box */
.submeter-notice-box {
    margin-top: 14px;
    padding: 12px 14px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 8px;
}

.submeter-notice-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

/* Clickable rows & Touch Targets */
.clickable-row {
    cursor: pointer;
    transition: background-color 0.12s ease;
}

.clickable-row:hover, .clickable-row:focus-visible {
    background-color: var(--head-bg, #f1f5f9) !important;
}

.row-urgent {
    background-color: #fff1f2 !important;
}

.row-urgent td:first-child {
    border-left: 3px solid #ef4444;
}

/* Chips Filter Bar */
.rooms-chip-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}

.room-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--border, #cbd5e1);
    background: var(--panel, #ffffff);
    color: var(--text, #334155);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    min-height: 44px;
    transition: all 0.12s ease;
}

@media (min-width: 640px) {
    .room-chip {
        min-height: 36px;
    }
}

.room-chip:hover {
    background: #f8fafc;
    border-color: #94a3b8;
}

.room-chip.on {
    background: var(--navy-2, #1e293b);
    color: #ffffff;
    border-color: var(--navy-2, #1e293b);
}

.room-chip .chip-count {
    font-size: 11px;
    opacity: 0.85;
}

/* Pagination bar */
.panel-pagination-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid var(--border, #e2e8f0);
}

.pagination-actions {
    display: flex;
    gap: 8px;
}

.pagination-btn {
    min-height: 44px !important;
    padding: 6px 14px !important;
    font-size: 12px !important;
}

@media (min-width: 640px) {
    .pagination-btn {
        min-height: 34px !important;
        padding: 4px 12px !important;
    }
}

.pagination-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

/* Responsive Table vs Mobile Cards */
@media (min-width: 801px) {
    .dashboard-mobile-cards {
        display: none !important;
    }
    .dashboard-table-view {
        display: block !important;
    }
}

@media (max-width: 800px) {
    .dashboard-mobile-cards {
        display: flex !important;
        flex-direction: column;
        gap: 10px;
    }
    .dashboard-table-view {
        display: none !important;
    }
}

/* Mobile Touch Cards */
.dashboard-touch-card {
    background: var(--panel, #ffffff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 8px;
    padding: 12px 14px;
    min-height: 48px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.touch-card-urgent {
    border-left: 4px solid #ef4444;
    background: #fff5f5;
}

.touch-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.touch-card-body {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 10px;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // -------------------------------------------------------------
    // Active Concerns Panel Pagination (5 tickets per page)
    // -------------------------------------------------------------
    const concernRows = Array.from(document.querySelectorAll('#concerns-table tbody tr.concern-row'));
    const concernCards = Array.from(document.querySelectorAll('#concerns-mobile-list .concern-mobile-card'));
    const concernsPrevBtn = document.getElementById('concerns-prev-btn');
    const concernsNextBtn = document.getElementById('concerns-next-btn');
    const concernsPageLabel = document.getElementById('concerns-page-label');

    const CONCERNS_PER_PAGE = 5;
    let concernsCurrentPage = 1;
    const concernsTotal = concernRows.length;
    const concernsTotalPages = Math.ceil(concernsTotal / CONCERNS_PER_PAGE) || 1;

    function renderConcernsPage(page) {
        concernsCurrentPage = page;
        const start = (page - 1) * CONCERNS_PER_PAGE;
        const end = start + CONCERNS_PER_PAGE;

        concernRows.forEach((row, i) => {
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });

        concernCards.forEach((card, i) => {
            card.style.display = (i >= start && i < end) ? '' : 'none';
        });

        if (concernsPageLabel) {
            const displayStart = concernsTotal === 0 ? 0 : start + 1;
            const displayEnd = Math.min(end, concernsTotal);
            concernsPageLabel.textContent = `Showing ${displayStart}–${displayEnd} of ${concernsTotal}`;
        }

        if (concernsPrevBtn) concernsPrevBtn.disabled = (concernsCurrentPage <= 1);
        if (concernsNextBtn) concernsNextBtn.disabled = (concernsCurrentPage >= concernsTotalPages);
    }

    if (concernsPrevBtn && concernsNextBtn && concernsTotal > 0) {
        concernsPrevBtn.addEventListener('click', () => {
            if (concernsCurrentPage > 1) renderConcernsPage(concernsCurrentPage - 1);
        });

        concernsNextBtn.addEventListener('click', () => {
            if (concernsCurrentPage < concernsTotalPages) renderConcernsPage(concernsCurrentPage + 1);
        });

        renderConcernsPage(1);
    }

    // -------------------------------------------------------------
    // Rooms Overview Panel Filtering & Pagination (6 rooms per page)
    // -------------------------------------------------------------
    const roomRows = Array.from(document.querySelectorAll('#rooms-table tbody tr.room-row'));
    const roomCards = Array.from(document.querySelectorAll('#rooms-mobile-list .room-mobile-card'));
    const roomChips = Array.from(document.querySelectorAll('.rooms-chip-filters .room-chip'));
    const roomsPrevBtn = document.getElementById('rooms-prev-btn');
    const roomsNextBtn = document.getElementById('rooms-next-btn');
    const roomsPageLabel = document.getElementById('rooms-page-label');

    const ROOMS_PER_PAGE = 6;
    let roomsCurrentFilter = 'All';
    let roomsCurrentPage = 1;

    function getFilteredRooms() {
        return roomRows.map((row, index) => {
            const status = row.getAttribute('data-room-status');
            const matches = (roomsCurrentFilter === 'All') || (status === roomsCurrentFilter);
            return { index, matches, status };
        }).filter(item => item.matches);
    }

    function renderRoomsPage(page) {
        roomsCurrentPage = page;
        const filtered = getFilteredRooms();
        const total = filtered.length;
        const totalPages = Math.ceil(total / ROOMS_PER_PAGE) || 1;

        const start = (page - 1) * ROOMS_PER_PAGE;
        const end = start + ROOMS_PER_PAGE;
        const visibleIndices = new Set(filtered.slice(start, end).map(item => item.index));

        roomRows.forEach((row, i) => {
            row.style.display = visibleIndices.has(i) ? '' : 'none';
        });

        roomCards.forEach((card, i) => {
            card.style.display = visibleIndices.has(i) ? '' : 'none';
        });

        if (roomsPageLabel) {
            const displayStart = total === 0 ? 0 : start + 1;
            const displayEnd = Math.min(end, total);
            roomsPageLabel.textContent = `Showing ${displayStart}–${displayEnd} of ${total}`;
        }

        if (roomsPrevBtn) roomsPrevBtn.disabled = (roomsCurrentPage <= 1);
        if (roomsNextBtn) roomsNextBtn.disabled = (roomsCurrentPage >= totalPages);
    }

    roomChips.forEach(chip => {
        chip.addEventListener('click', function() {
            roomChips.forEach(c => c.classList.remove('on'));
            this.classList.add('on');
            roomsCurrentFilter = this.getAttribute('data-status') || 'All';
            renderRoomsPage(1);
        });
    });

    if (roomsPrevBtn && roomsNextBtn && roomRows.length > 0) {
        roomsPrevBtn.addEventListener('click', () => {
            if (roomsCurrentPage > 1) renderRoomsPage(roomsCurrentPage - 1);
        });

        roomsNextBtn.addEventListener('click', () => {
            const filtered = getFilteredRooms();
            const totalPages = Math.ceil(filtered.length / ROOMS_PER_PAGE) || 1;
            if (roomsCurrentPage < totalPages) renderRoomsPage(roomsCurrentPage + 1);
        });

        renderRoomsPage(1);
    }
});
</script>
@endpush
@endsection
