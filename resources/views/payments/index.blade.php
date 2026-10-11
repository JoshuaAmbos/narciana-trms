@extends('layouts.app')

@section('title', 'Payments')

@section('content')
    @error('bill') <div class="alert alert-err">{{ $message }}</div> @enderror

    {{-- SECTION 1: Collections Panel --}}
    <div class="panel printable mb-6">
        <h2>
            Collections, {{ $month->format('F Y') }}
            <span class="month-nav no-print">
                <a href="{{ route('payments.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn sec">‹</a>
                <a href="{{ route('payments.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn sec">›</a>
                <button type="button" class="btn sec" onclick="window.print()">Print</button>
            </span>
        </h2>

        <div class="cards">
            <div class="card"><div class="n">@peso($totals['All'])</div><div class="l">Collected in {{ $month->format('F Y') }}</div></div>
            <div class="card"><div class="n">@peso($totals['Cash'])</div><div class="l">Cash</div></div>
            <div class="card"><div class="n">@peso($totals['GCash'])</div><div class="l">GCash</div></div>
            <div class="card">
                <div class="n">{{ $collections->count() }}</div>
                <div class="l">
                    {{ Str::plural('payment', $collections->count()) }}
                    @foreach ($byReceiver as $role => $amount) · {{ $role }} @peso($amount) @endforeach
                </div>
            </div>
        </div>

        @if ($collections->isEmpty())
            <p class="muted">No payments in {{ $month->format('F Y') }}.</p>
        @else
            <div class="collections-controls-wrap">
                {{-- Search Bar (flexes on landscape) --}}
                <div class="collections-search-box">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" 
                        id="collections-search-input"
                        class="search-input" 
                        placeholder="Search collections by room, tenant, ACK no., mode, or role..." 
                        autocomplete="off">

                    <button type="button" 
                            id="collections-search-clear" 
                            class="search-clear-btn collections-search-clear-btn"
                            title="Clear search"
                            style="display: none;">
                        &times;
                    </button>
                </div>

                {{-- Mode Filter Chips --}}
                <div class="collections-chips-wrap">
                    <span class="mode-label">Mode:</span>
                    <button type="button" class="chip on collections-filter-chip" data-mode="All">All</button>
                    <button type="button" class="chip collections-filter-chip" data-mode="Cash">Cash</button>
                    <button type="button" class="chip collections-filter-chip" data-mode="GCash">GCash</button>
                </div>
            </div>

            <div class="table-wrap">
                <table id="collections-table">
                    <thead>
                        <tr><th>Date</th><th>ACK no.</th><th>Room</th><th>Tenant</th><th>Mode</th><th>Received by</th><th class="r">Amount</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($collections as $p)
                            <tr class="collection-row" data-mode="{{ $p->mode }}" data-search="{{ strtolower($p->bill->lease->room->room_no . ' ' . $p->bill->lease->tenant->full_name . ' ' . $p->ack_no . ' ' . $p->mode . ' ' . ($p->gcash_ref_no ?? '') . ' ' . $p->user->role) }}">
                                <td class="nowrap">{{ $p->payment_date->format('j M') }}</td>
                                <td><a href="{{ route('payments.show', $p) }}">{{ $p->ack_no }}</a></td>
                                <td><b>{{ $p->bill->lease->room->room_no }}</b></td>
                                <td>{{ $p->bill->lease->tenant->full_name }}</td>
                                <td>{{ $p->mode }}@if ($p->gcash_ref_no)<span class="sub nowrap">ref. {{ $p->gcash_ref_no }}</span>@endif</td>
                                <td>{{ $p->user->role }}</td>
                                <td class="money">@peso($p->amount)</td>
                            </tr>
                        @endforeach
                        <tr class="tot-row" id="collections-total-row">
                            <td colspan="6">Total (Cash @peso($totals['Cash']) · GCash @peso($totals['GCash']))</td>
                            <td class="money">@peso($totals['All'])</td>
                        </tr>
                    </tbody>
                </table>
                <p id="collections-no-match" class="muted" style="display: none; padding: 12px; text-align: center;">No collections matching your filter criteria.</p>
            </div>
        @endif
    </div>

    {{-- SECTION 2: Bills to Collect Panel --}}
    <div class="panel no-print">
        <h2>Bills to collect <span class="muted">Oldest first · full payment only</span></h2>
        
        {{-- Filter Group & Search Bar for Bills to Collect --}}
        @if ($toCollect->isNotEmpty() || $breachedLeases->isNotEmpty())
            <div class="to-collect-controls-wrap no-print">
                {{-- Search Input (Full-width on Mobile/Portrait, Expands on Tablet Landscape) --}}
                <div class="to-collect-search-box">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" 
                        id="to-collect-search-input"
                        class="search-input" 
                        placeholder="Search bills to collect by room, tenant, or status..." 
                        autocomplete="off">

                    <button type="button" 
                            id="to-collect-search-clear" 
                            class="search-clear-btn to-collect-search-clear-btn"
                            title="Clear search"
                            style="display: none;">
                        &times;
                    </button>
                </div>

                {{-- Status Filter Chips (Below on Portrait, Inline on Landscape) --}}
                <div class="to-collect-chips-wrap">
                    <span class="status-label">Status:</span>
                    <button type="button" class="chip on to-collect-filter-chip" data-status="All">All</button>
                    <button type="button" class="chip to-collect-filter-chip" data-status="Grace">Grace Period</button>
                    <button type="button" class="chip to-collect-filter-chip" data-status="Overdue">Overdue</button>
                </div>
            </div>
        @endif

        {{-- Deposit Exhausted Alert Section --}}
        @if ($breachedLeases->isNotEmpty())
            <div id="breach-panel-container" class="breach-panel" style="margin-bottom: 16px; margin-top: 16px; border: 1px solid #fda4af; background: #fff1f2; border-radius: 8px; overflow: hidden;">
                <div style="background: #ffe4e6; padding: 10px 16px; font-weight: 700; font-size: 13px; color: #9f1239; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span>Deposit Exhausted</span>
                        <span class="px-2 py-0.5 text-xs font-semibold bg-rose-100 text-rose-700 rounded-full" style="background: #fee2e2; color: #be123c; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">
                            {{ $breachedLeases->count() }} Requires Attention
                        </span>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column;">
                    @foreach ($breachedLeases as $breach)
                        @php($bLease =$breach['lease'])
                        <div class="breach-row cursor-pointer hover:bg-rose-50/60 transition-colors"
                             style="min-height: 44px; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #fecdd3; cursor: pointer;"
                             onclick="openBreachModal({{ $bLease->lease_id }})"
                             data-modal-target="breach-modal"
                             data-lease-id="{{ $bLease->lease_id }}"
                             data-search="{{ strtolower($bLease->room->room_no . ' ' . $bLease->tenant->full_name . ' breach deposit buffer exhausted') }}"
                             role="button"
                             tabindex="0">
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1;">
                                <span class="tag t-red" style="font-weight: 600; padding: 2px 8px; border-radius: 4px;">Room {{ $bLease->room->room_no }}</span>
                                <b style="color: #0f172a;">{{ $bLease->tenant->full_name }}</b>
                                <span style="font-size: 13px; color: #475569;">
                                    @peso($breach['total_arrears']) arrears &middot; @peso($breach['total_deposit']) deposit
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="text-xs font-medium text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200" style="background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">Exhausted</span>
                                <svg class="w-4 h-4 text-slate-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #94a3b8; flex-shrink: 0;"><path d="m9 18 6-6-6-6"/></svg>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($toCollect->isEmpty())
            <p class="muted">Every bill is paid.</p>
        @else
            {{-- Tablet Landscape (~1024px – 1280px) & Desktop: Clean Table with Clickable Rows --}}
            <div class="table-wrap to-collect-table-wrap">
                <table id="to-collect-table">
                    <thead>
                        <tr><th>Due date</th><th>Room</th><th>Tenant</th><th class="r">Bill</th><th>Status</th><th class="r">Penalty so far</th><th class="r">Due if paid today</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($toCollect as $row)
                            @foreach ([$row['bill']] as $b)
                                @foreach ([\Carbon\Carbon::parse($b->due_date)->startOfDay()] as $dueDate)
                                    @foreach ([today()->startOfDay()] as $today)
                                        @foreach ([(int) ($row['today']['days_late'] ?? 0)] as $daysLate)
                                            @foreach ([$daysLate > 0] as $isOverdue)
                                                @foreach ([$dueDate->gt($today)] as $isFuture)
                                                    @foreach ([$dueDate->equalTo($today)] as $isDueToday)
                                                        @foreach ([(int) $today->diffInDays($dueDate, false)] as $daysUntilDue)
                                                            @foreach ([!$isFuture && !$isDueToday && !$isOverdue] as $inGrace)
                                                                @foreach ([$isOverdue ? 'Overdue' : ($inGrace ? 'Grace' : 'Upcoming')] as $statusType)
                                                                    <tr class="to-collect-row" 
                                                                        data-status="{{ $statusType }}" 
                                                                        data-search="{{ strtolower($b->lease->room->room_no . ' ' . $b->lease->tenant->full_name . ' ' . $statusType) }}"
                                                                        onclick="openRecordPaymentModal({{ $b->bill_id }})"
                                                                        onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault(); openRecordPaymentModal({{ $b->bill_id }});}"
                                                                        tabindex="0"
                                                                        role="button"
                                                                        aria-label="Record payment for Room {{ $b->lease->room->room_no }} - {{ $b->lease->tenant->full_name }}"
                                                                        @if (!empty($row['is_breached'])) style="background: #fff5f5;" @endif>
                                                                        <td class="nowrap">{{ $dueDate->format('j M Y') }}</td>
                                                                        <td><b>{{ $b->lease->room->room_no }}</b></td>
                                                                        <td>{{ $b->lease->tenant->full_name }}</td>
                                                                        <td class="money">@peso((float) $b->total_amount - (float) $b->penalty_amount)</td>
                                                                        <td>
                                                                            @if ($isOverdue)
                                                                                <span class="tag t-red">Overdue {{ $daysLate }} {{ Str::plural('day', $daysLate) }}</span>
                                                                            @elseif ($isFuture)
                                                                                <span class="tag t-amber">Due in {{ $daysUntilDue }} {{ Str::plural('day', $daysUntilDue) }}</span>
                                                                            @elseif ($isDueToday)
                                                                                <span class="tag t-amber">Due today</span>
                                                                            @elseif (!empty($row['today']['in_grace']))
                                                                                <span class="tag t-amber">Grace Period (Day {{ $row['today']['grace_day'] ?? 1 }} of {{ $row['today']['grace_days'] ?? 3 }})</span>
                                                                            @elseif ($inGrace)
                                                                                <span class="tag t-amber">In grace period</span>
                                                                            @else
                                                                                <span class="tag t-amber">Unpaid</span>
                                                                            @endif

                                                                            @if (!empty($row['is_breached']))
                                                                                <div>
                                                                                    <span class="inline-block mt-1 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded" style="display: inline-block; margin-top: 4px; padding: 2px 6px; font-size: 11px; font-weight: 600; color: #be123c; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px;">
                                                                                        Deposit Exhausted
                                                                                    </span>
                                                                                </div>
                                                                            @endif
                                                                        </td>
                                                                        <td class="money">@peso($row['today']['penalty'] ?? 0)</td>
                                                                        <td class="money"><b>@peso($row['today']['total'] ?? $b->total_amount)</b></td>
                                                                    </tr>
                                                                @endforeach
                                                            @endforeach
                                                        @endforeach
                                                    @endforeach
                                                @endforeach
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Tablet Portrait (~712px – 800px) & Mobile: Stacked Touch-Friendly Cards --}}
            <div class="to-collect-cards" id="toCollectCardsContainer">
                @foreach ($toCollect as $row)
                    @foreach ([$row['bill']] as $b)
                        @foreach ([\Carbon\Carbon::parse($b->due_date)->startOfDay()] as $dueDate)
                            @foreach ([today()->startOfDay()] as $today)
                                @foreach ([(int) ($row['today']['days_late'] ?? 0)] as $daysLate)
                                    @foreach ([$daysLate > 0] as $isOverdue)
                                        @foreach ([$dueDate->gt($today)] as $isFuture)
                                            @foreach ([$dueDate->equalTo($today)] as $isDueToday)
                                                @foreach ([(int) $today->diffInDays($dueDate, false)] as $daysUntilDue)
                                                    @foreach ([!$isFuture && !$isDueToday && !$isOverdue] as $inGrace)
                                                        @foreach ([$isOverdue ? 'Overdue' : ($inGrace ? 'Grace' : 'Upcoming')] as $statusType)
                                                            <div class="to-collect-card"
                                                                 data-status="{{ $statusType }}"
                                                                 data-search="{{ strtolower($b->lease->room->room_no . ' ' . $b->lease->tenant->full_name . ' ' . $statusType) }}"
                                                                 onclick="openRecordPaymentModal({{ $b->bill_id }})"
                                                                 onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault(); openRecordPaymentModal({{ $b->bill_id }});}"
                                                                 tabindex="0"
                                                                 role="button"
                                                                 aria-label="Record payment for Room {{ $b->lease->room->room_no }} - {{ $b->lease->tenant->full_name }}"
                                                                 @if (!empty($row['is_breached'])) style="background: #fff5f5;" @endif>
                                                                <div class="to-collect-card-head">
                                                                    <div>
                                                                        <span class="to-collect-card-room">Room {{ $b->lease->room->room_no }}</span>
                                                                        <div class="to-collect-card-tenant">{{ $b->lease->tenant->full_name }}</div>
                                                                    </div>
                                                                    <div>
                                                                        @if ($isOverdue)
                                                                            <span class="tag t-red">Overdue {{ $daysLate }} {{ Str::plural('day', $daysLate) }}</span>
                                                                        @elseif ($isFuture)
                                                                            <span class="tag t-amber">Due in {{ $daysUntilDue }} {{ Str::plural('day', $daysUntilDue) }}</span>
                                                                        @elseif ($isDueToday)
                                                                            <span class="tag t-amber">Due today</span>
                                                                        @elseif (!empty($row['today']['in_grace']))
                                                                            <span class="tag t-amber">Grace Period (Day {{ $row['today']['grace_day'] ?? 1 }} of {{ $row['today']['grace_days'] ?? 3 }})</span>
                                                                        @elseif ($inGrace)
                                                                            <span class="tag t-amber">In grace period</span>
                                                                        @else
                                                                            <span class="tag t-amber">Unpaid</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <div class="to-collect-card-body">
                                                                    <span class="muted">Due {{ $dueDate->format('j M Y') }}</span>
                                                                    <span class="money"><b>@peso($row['today']['total'] ?? $b->total_amount)</b></span>
                                                                </div>
                                                                @if (!empty($row['is_breached']))
                                                                    <div>
                                                                        <span class="inline-block px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded" style="display: inline-block; padding: 2px 6px; font-size: 11px; font-weight: 600; color: #be123c; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px;">
                                                                            Deposit Exhausted
                                                                        </span>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    @endforeach
                                                @endforeach
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                @endforeach
                            @endforeach
                        @endforeach
                    @endforeach
                @endforeach
            </div>

            <p id="to-collect-no-match" class="muted" style="display: none; padding: 12px; text-align: center;">No bills matching your filter criteria.</p>
        @endif
    </div>

    {{-- Detail Modal for Deposit Exhausted Breach --}}
    <div id="breachDetailModal" class="modal-backdrop" hidden>
        <div class="modal-box" style="max-width: 520px;">
            <div class="modal-header">
                <div>
                    <h2 id="bdm_title" style="margin: 0; font-size: 18px; font-weight: 700; color: #0f172a;">Deposit Buffer Exhausted &middot; Room <span id="bdm_room">-</span></h2>
                    <div id="bdm_tenant" class="sub muted" style="margin-top: 4px; font-size: 13px; color: #64748b;">Tenant: -</div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeBreachModal()" aria-label="Close modal">&times;</button>
            </div>

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                    <h3 style="margin: 0 0 12px 0; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700;">Financial Breakdown</h3>
                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #475569;">Unpaid Rent &amp; Utilities:</span>
                            <b id="bdm_unpaid_bills">₱0.00</b>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #475569;">Accrued Late Penalties:</span>
                            <b id="bdm_penalties">₱0.00</b>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px dashed #cbd5e1;">
                            <span style="font-weight: 700; color: #0f172a;">Total Outstanding Arrears:</span>
                            <b class="money red" id="bdm_total_arrears" style="font-size: 15px; color: #dc2626;">₱0.00</b>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #475569;">Held Security Deposit:</span>
                            <b id="bdm_security_deposit">₱0.00</b>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid #e2e8f0;">
                            <span style="font-weight: 700; color: #b91c1c;">Net Deficit:</span>
                            <b class="money red" id="bdm_net_deficit" style="font-size: 16px; font-weight: 700; color: #b91c1c;">₱0.00</b>
                        </div>
                    </div>
                </div>

                <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #9f1239; line-height: 1.5;">
                    <p style="margin: 0;">This tenant's unpaid obligations have completely consumed their security deposit buffer. Continued non-payment leaves the account with zero security coverage.</p>
                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0; flex-wrap: wrap;">
                    <button type="button" class="btn sec" onclick="closeBreachModal()" style="min-height: 44px; padding: 0 16px;">Cancel / Close</button>
                    <a id="bdm_lease_link" href="#" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center; padding: 0 16px;">View Lease Details</a>
                    @can('manage-leases')
                        <form id="bdm_terminate_form" method="POST" action="" onsubmit="return confirm('End tenancy and vacate Room ' + document.getElementById('bdm_room').textContent + '?\n\nThis will offset the security deposit against unpaid obligations, cancel the lease, and vacate the room.')">
                            @csrf
                            <button type="submit" class="btn danger" style="min-height: 44px; padding: 0 16px; display: inline-flex; align-items: center; font-weight: 600;">
                                End Tenancy &amp; Vacate Room
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- Settlement Modal for Bills to Collect (#recordPaymentModal) --}}
    <div id="recordPaymentModal" class="modal-backdrop" hidden>
        <div class="modal-box">
            <div class="modal-header">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="header-icon"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                    <span>Record Payment</span>
                </h2>
                <button type="button" class="modal-close-btn" onclick="closeRecordPaymentModal()" aria-label="Close modal">&times;</button>
            </div>

            <form method="POST" action="{{ route('payments.store') }}" id="record-payment-form" novalidate>
                @csrf
                <input type="hidden" name="bill_id" id="rpm_bill_id">

                <div class="modal-summary-grid">
                    <div><span class="summary-lbl">Room</span><b id="rpm_room_display">-</b></div>
                    <div><span class="summary-lbl">Tenant</span><b id="rpm_tenant_display">-</b></div>
                    <div><span class="summary-lbl">Bill Due</span><b id="rpm_due_display">-</b></div>
                    <div><span class="summary-lbl">Status</span><span id="rpm_status_display">-</span></div>
                </div>

                <table class="sum amount-box" style="margin: 14px 0;">
                    <tr>
                        <td>Bill Total</td>
                        <td class="money" id="rpm_base_amount">₱0.00</td>
                    </tr>
                    <tr>
                        <td>
                            Late Penalty
                            <span class="sub red" id="rpm_late_sub" style="display: none;"></span>
                        </td>
                        <td class="money" id="rpm_penalty_amount">₱0.00</td>
                    </tr>
                    <tr class="tot">
                        <td>Total to Receive</td>
                        <td class="money" id="rpm_total_due">₱0.00</td>
                    </tr>
                </table>

                <div class="row">
                    <div class="f">
                        <label for="rpm_payment_date">Payment date <span class="req">*</span></label>
                        <input id="rpm_payment_date" name="payment_date" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="f">
                        <label for="rpm_mode">Payment mode <span class="req">*</span></label>
                        <select id="rpm_mode" name="mode" onchange="toggleRpmMode(this.value)">
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                        </select>
                    </div>
                </div>

                <div class="f" id="rpm_gcash_wrap" style="display: none;">
                    <label for="rpm_gcash_ref_no">GCash Reference No. (13 digits) <span class="req">*</span></label>
                    <input id="rpm_gcash_ref_no" name="gcash_ref_no" type="text" inputmode="numeric" maxlength="20" placeholder="e.g. 1001234567890">
                    <div class="hint">Exactly 13 digits from the GCash transaction receipt.</div>
                </div>

                <div class="f">
                    <label for="rpm_amount">Amount received (₱) <span class="req">*</span></label>
                    <input id="rpm_amount" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" required autocomplete="off">
                    <div class="hint" id="rpm_amount_hint">Full payment required: exactly <b id="rpm_hint_amount">₱0.00</b> (no partial payments).</div>
                </div>

                <div class="form-actions" style="margin-top: 18px;">
                    <button type="submit" class="btn" id="rpm_submit_btn">Save &amp; Issue Acknowledgment</button>
                    <button type="button" class="btn sec" onclick="closeRecordPaymentModal()">Cancel</button>
                </div>
                <p class="muted small" style="margin-top: 10px;">Received by: <b>{{ auth()->user()->role }}</b> (you).</p>
            </form>
        </div>
    </div>

    <style>
        .breach-row {
            transition: background-color 0.15s ease;
        }
        .breach-row:hover {
            background-color: rgba(254, 205, 211, 0.4) !important;
        }
        .search-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }
        @media (max-width: 768px) {
            .breach-row {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 8px !important;
            }
        }
    </style>

    <script>
        const breachedLeasesData = @json($breachedLeasesMap ?? []);
        const toCollectBillsData = @json($toCollectMap ?? []);
        window.billsData = toCollectBillsData;

        function openBreachModal(leaseId) {
            const data = breachedLeasesData[leaseId];
            if (!data) return;

            document.getElementById('bdm_room').textContent = data.room_no;
            document.getElementById('bdm_tenant').textContent = 'Tenant: ' + data.tenant_name;
            document.getElementById('bdm_unpaid_bills').textContent = formatPeso(data.unpaid_bills_sum);
            document.getElementById('bdm_penalties').textContent = formatPeso(data.penalties_sum);
            document.getElementById('bdm_total_arrears').textContent = formatPeso(data.total_arrears);
            document.getElementById('bdm_security_deposit').textContent = formatPeso(data.security_deposit);
            document.getElementById('bdm_net_deficit').textContent = formatPeso(data.net_deficit);
            document.getElementById('bdm_lease_link').href = data.lease_url;

            const terminateForm = document.getElementById('bdm_terminate_form');
            if (terminateForm) {
                terminateForm.action = data.terminate_url;
            }

            const modal = document.getElementById('breachDetailModal');
            if (modal) {
                modal.removeAttribute('hidden');
            }
        }

        function closeBreachModal() {
            const modal = document.getElementById('breachDetailModal');
            if (modal) {
                modal.setAttribute('hidden', '');
            }
        }

        function openRecordPaymentModal(billId) {
            const bill = toCollectBillsData[billId];
            if (!bill) return;

            if (!bill.can_pay) {
                alert('Room ' + bill.room_no + ': please pay the older bill for this room first.');
                return;
            }

            document.getElementById('rpm_bill_id').value = bill.bill_id;
            document.getElementById('rpm_room_display').textContent = 'Room ' + bill.room_no;
            document.getElementById('rpm_tenant_display').textContent = bill.tenant;
            document.getElementById('rpm_due_display').textContent = bill.due_date_fmt;
            document.getElementById('rpm_status_display').innerHTML = bill.status === 'Overdue'
                ? '<span class="tag t-red">Overdue</span>'
                : '<span class="tag t-amber">Unpaid</span>';

            document.getElementById('rpm_base_amount').textContent = formatPeso(bill.base_amount);
            document.getElementById('rpm_penalty_amount').textContent = formatPeso(bill.penalty);
            document.getElementById('rpm_total_due').textContent = formatPeso(bill.total);

            const lateSub = document.getElementById('rpm_late_sub');
            if (bill.days_late > 0) {
                lateSub.textContent = `(${bill.days_late} day${bill.days_late === 1 ? '' : 's'} late after grace period)`;
                lateSub.style.display = 'inline';
            } else {
                lateSub.style.display = 'none';
            }

            document.getElementById('rpm_amount').value = Number(bill.total).toFixed(2);
            document.getElementById('rpm_hint_amount').textContent = formatPeso(bill.total);

            document.getElementById('rpm_mode').value = 'Cash';
            toggleRpmMode('Cash');

            const modal = document.getElementById('recordPaymentModal');
            if (modal) {
                modal.removeAttribute('hidden');
            }
        }

        function closeRecordPaymentModal() {
            const modal = document.getElementById('recordPaymentModal');
            if (modal) {
                modal.setAttribute('hidden', '');
            }
        }

        function toggleRpmMode(mode) {
            const gcashWrap = document.getElementById('rpm_gcash_wrap');
            const gcashInput = document.getElementById('rpm_gcash_ref_no');
            if (!gcashWrap || !gcashInput) return;
            if (mode === 'GCash') {
                gcashWrap.style.display = 'block';
                gcashInput.required = true;
                setTimeout(() => gcashInput.focus(), 50);
            } else {
                gcashWrap.style.display = 'none';
                gcashInput.required = false;
                gcashInput.value = '';
            }
        }

        function formatPeso(amount) {
            return '₱' + Number(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Close on backdrop click
        document.addEventListener('click', function(e) {
            const breachModal = document.getElementById('breachDetailModal');
            if (breachModal && !breachModal.hasAttribute('hidden') && e.target === breachModal) {
                closeBreachModal();
            }
            const recordModal = document.getElementById('recordPaymentModal');
            if (recordModal && !recordModal.hasAttribute('hidden') && e.target === recordModal) {
                closeRecordPaymentModal();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBreachModal();
                closeRecordPaymentModal();
            }
        });

        // =========================================================================
        // Option 2 Pure Client-Side Real-Time Filter Controller
        // =========================================================================
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Collections Controller
            const collectionRows = Array.from(document.querySelectorAll('tr.collection-row'));
            const collectionsTotalRow = document.getElementById('collections-total-row');
            const collectionChips = Array.from(document.querySelectorAll('.collections-filter-chip'));
            const collectionsSearchInput = document.getElementById('collections-search-input');
            const collectionsClearBtn = document.getElementById('collections-search-clear');
            const collectionsNoMatch = document.getElementById('collections-no-match');
            let activeCollectionMode = 'All';

            function filterCollections() {
                const query = (collectionsSearchInput ? collectionsSearchInput.value : '').toLowerCase().trim();
                let matchCount = 0;

                collectionRows.forEach(row => {
                    const rowMode = row.getAttribute('data-mode') || 'All';
                    const modeMatches = (activeCollectionMode === 'All') || (rowMode === activeCollectionMode);
                    const searchData = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
                    const textMatches = !query || searchData.includes(query);

                    const visible = modeMatches && textMatches;
                    row.style.display = visible ? '' : 'none';
                    if (visible) matchCount++;
                });

                if (collectionsClearBtn) {
                    collectionsClearBtn.style.display = query ? 'inline-flex' : 'none';
                }
                if (collectionsTotalRow) {
                    collectionsTotalRow.style.display = matchCount > 0 ? '' : 'none';
                }
                if (collectionsNoMatch) {
                    collectionsNoMatch.style.display = (collectionRows.length > 0 && matchCount === 0) ? 'block' : 'none';
                }
            }

            if (collectionsSearchInput) {
                collectionsSearchInput.addEventListener('input', filterCollections);
                collectionsSearchInput.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
            }
            if (collectionsClearBtn && collectionsSearchInput) {
                collectionsClearBtn.addEventListener('click', () => {
                    collectionsSearchInput.value = '';
                    filterCollections();
                    collectionsSearchInput.focus();
                });
            }
            collectionChips.forEach(chip => {
                chip.addEventListener('click', function() {
                    collectionChips.forEach(c => c.classList.remove('on'));
                    this.classList.add('on');
                    activeCollectionMode = this.getAttribute('data-mode') || 'All';
                    filterCollections();
                });
            });

            // 2. Bills to Collect Controller
            const toCollectRows = Array.from(document.querySelectorAll('tr.to-collect-row'));
            const toCollectCards = Array.from(document.querySelectorAll('.to-collect-card'));
            const breachRows = Array.from(document.querySelectorAll('.breach-row'));
            const breachContainer = document.getElementById('breach-panel-container');
            const toCollectChips = Array.from(document.querySelectorAll('.to-collect-filter-chip'));
            const toCollectSearchInput = document.getElementById('to-collect-search-input');
            const toCollectClearBtn = document.getElementById('to-collect-search-clear');
            const toCollectNoMatch = document.getElementById('to-collect-no-match');
            let activeToCollectStatus = 'All';

            function filterToCollect() {
                const query = (toCollectSearchInput ? toCollectSearchInput.value : '').toLowerCase().trim();
                let matchCount = 0;

                toCollectRows.forEach(row => {
                    const rowStatus = row.getAttribute('data-status') || 'All';
                    const statusMatches = (activeToCollectStatus === 'All') || (rowStatus === activeToCollectStatus);
                    const searchData = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
                    const textMatches = !query || searchData.includes(query);

                    const visible = statusMatches && textMatches;
                    row.style.display = visible ? '' : 'none';
                    if (visible) matchCount++;
                });

                toCollectCards.forEach(card => {
                    const cardStatus = card.getAttribute('data-status') || 'All';
                    const statusMatches = (activeToCollectStatus === 'All') || (cardStatus === activeToCollectStatus);
                    const searchData = (card.getAttribute('data-search') || card.textContent || '').toLowerCase();
                    const textMatches = !query || searchData.includes(query);

                    const visible = statusMatches && textMatches;
                    card.style.display = visible ? 'flex' : 'none';
                });

                // Filter breaches
                let visibleBreaches = 0;
                const canShowBreach = (activeToCollectStatus === 'All' || activeToCollectStatus === 'Overdue');
                breachRows.forEach(row => {
                    const searchData = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
                    const textMatches = !query || searchData.includes(query);
                    const isVisible = canShowBreach && textMatches;
                    row.style.display = isVisible ? 'flex' : 'none';
                    if (isVisible) visibleBreaches++;
                });

                if (breachContainer) {
                    breachContainer.style.display = (breachRows.length > 0 && visibleBreaches > 0) ? 'block' : 'none';
                }

                if (toCollectClearBtn) {
                    toCollectClearBtn.style.display = query ? 'inline-flex' : 'none';
                }

                if (toCollectNoMatch) {
                    const hasItems = toCollectRows.length > 0 || breachRows.length > 0;
                    const totalVisible = matchCount + visibleBreaches;
                    toCollectNoMatch.style.display = (hasItems && totalVisible === 0) ? 'block' : 'none';
                }
            }

            if (toCollectSearchInput) {
                toCollectSearchInput.addEventListener('input', filterToCollect);
                toCollectSearchInput.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
            }
            if (toCollectClearBtn && toCollectSearchInput) {
                toCollectClearBtn.addEventListener('click', () => {
                    toCollectSearchInput.value = '';
                    filterToCollect();
                    toCollectSearchInput.focus();
                });
            }
            toCollectChips.forEach(chip => {
                chip.addEventListener('click', function() {
                    toCollectChips.forEach(c => c.classList.remove('on'));
                    this.classList.add('on');
                    activeToCollectStatus = this.getAttribute('data-status') || 'All';
                    filterToCollect();
                });
            });
        });
    </script>
@endsection