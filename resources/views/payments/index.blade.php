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
            {{-- Filter Group & Real-Time Search Bar for Collections --}}
            <div class="collections-controls-wrap no-print">
                {{-- Search Bar (Full width on portrait/mobile, flexes on landscape) --}}
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
                            class="collections-search-clear-btn"
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

            {{-- Collections Pagination Bar --}}
            <div class="panel-pagination-bar no-print" id="collections-pagination-bar">
                <span class="sub text-xs" id="collections-page-label">Showing 1–5 of {{ $collections->count() }}</span>
                <div class="pagination-actions">
                    <button type="button" class="btn sec pagination-btn" id="collections-prev-btn" disabled>&larr; Prev</button>
                    <button type="button" class="btn sec pagination-btn" id="collections-next-btn">Next &rarr;</button>
                </div>
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
                            class="to-collect-search-clear-btn"
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
            <div class="table-wrap">
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
                <p id="to-collect-no-match" class="muted" style="display: none; padding: 12px; text-align: center;">No bills matching your filter criteria.</p>
            </div>

            {{-- Bills to Collect Pagination Bar --}}
            <div class="panel-pagination-bar no-print" id="to-collect-pagination-bar">
                <span class="sub text-xs" id="to-collect-page-label">Showing 1–5 of {{ $toCollect->count() }}</span>
                <div class="pagination-actions">
                    <button type="button" class="btn sec pagination-btn" id="to-collect-prev-btn" disabled>&larr; Prev</button>
                    <button type="button" class="btn sec pagination-btn" id="to-collect-next-btn">Next &rarr;</button>
                </div>
            </div>
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
            min-height: 34px !important;
            padding: 4px 12px !important;
            font-size: 12px !important;
        }
        .pagination-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        @media (max-width: 768px) {
            .breach-row {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 8px !important;
            }
            .pagination-btn {
                min-height: 44px !important;
                padding: 6px 14px !important;
            }
        }
    </style>

    <script>
        const breachedLeasesData = @json($breachedLeasesMap ?? []);

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

        function formatPeso(amount) {
            return '₱' + Number(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Close on backdrop click
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('breachDetailModal');
            if (modal && !modal.hasAttribute('hidden') && e.target === modal) {
                closeBreachModal();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBreachModal();
            }
        });

        // =========================================================================
        // Client-Side Filtering, Searching, & Pagination Controller
        // =========================================================================
        document.addEventListener('DOMContentLoaded', function() {
            const PAGE_SIZE = 5;

            function setupPaginatedList(config) {
                let currentPage = 1;
                let activeCategory = 'All';
                let activeQuery = '';

                function getMatchingItems() {
                    return config.items.filter(item => {
                        const categoryVal = config.getCategory ? config.getCategory(item) : 'All';
                        const matchesCategory = (activeCategory === 'All') || (categoryVal === activeCategory);

                        const text = (item.getAttribute('data-search') || item.textContent || '').toLowerCase();
                        const matchesText = (activeQuery === '') || text.includes(activeQuery);

                        return matchesCategory && matchesText;
                    });
                }

                function updateView() {
                    const filtered = getMatchingItems();
                    const total = filtered.length;
                    const totalPages = Math.ceil(total / PAGE_SIZE) || 1;
                    if (currentPage > totalPages) currentPage = totalPages;

                    const start = (currentPage - 1) * PAGE_SIZE;
                    const end = start + PAGE_SIZE;

                    config.items.forEach(item => {
                        const isInSlice = filtered.slice(start, end).includes(item);
                        item.style.display = isInSlice ? (config.displayType || '') : 'none';
                    });

                    if (config.pageLabel) {
                        const startDisplay = total === 0 ? 0 : start + 1;
                        const endDisplay = Math.min(end, total);
                        config.pageLabel.textContent = `Showing ${startDisplay}–${endDisplay} of ${total}`;
                    }

                    if (config.prevBtn) config.prevBtn.disabled = (currentPage <= 1);
                    if (config.nextBtn) config.nextBtn.disabled = (currentPage >= totalPages);

                    if (config.noMatchEl) {
                        config.noMatchEl.style.display = (config.items.length > 0 && total === 0) ? 'block' : 'none';
                    }

                    if (config.onViewUpdated) {
                        config.onViewUpdated(filtered, activeCategory, activeQuery);
                    }
                }

                // Filter chip click handler
                if (config.chips && config.chips.length > 0) {
                    config.chips.forEach(chip => {
                        chip.addEventListener('click', function() {
                            config.chips.forEach(c => c.classList.remove('on'));
                            this.classList.add('on');
                            activeCategory = this.getAttribute(config.chipDataAttr) || 'All';
                            currentPage = 1;
                            updateView();
                        });
                    });
                }

                // Search input listener
                if (config.searchInput) {
                    let debounceTimer = null;
                    config.searchInput.addEventListener('input', function() {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(() => {
                            activeQuery = (this.value || '').toLowerCase().trim();
                            currentPage = 1;
                            updateView();
                            if (config.clearBtn) {
                                config.clearBtn.style.display = activeQuery ? 'inline-flex' : 'none';
                            }
                        }, 120);
                    });

                    config.searchInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') e.preventDefault();
                    });
                }

                // Clear search button
                if (config.clearBtn && config.searchInput) {
                    config.clearBtn.addEventListener('click', function() {
                        config.searchInput.value = '';
                        activeQuery = '';
                        currentPage = 1;
                        updateView();
                        this.style.display = 'none';
                        config.searchInput.focus();
                    });
                }

                // Pagination buttons
                if (config.prevBtn) {
                    config.prevBtn.addEventListener('click', () => {
                        if (currentPage > 1) {
                            currentPage--;
                            updateView();
                        }
                    });
                }

                if (config.nextBtn) {
                    config.nextBtn.addEventListener('click', () => {
                        const total = getMatchingItems().length;
                        const totalPages = Math.ceil(total / PAGE_SIZE) || 1;
                        if (currentPage < totalPages) {
                            currentPage++;
                            updateView();
                        }
                    });
                }

                updateView();
            }

            // 1. Collections Controller
            const collectionRows = Array.from(document.querySelectorAll('tr.collection-row'));
            const collectionsTotalRow = document.getElementById('collections-total-row');
            const collectionChips = Array.from(document.querySelectorAll('.collections-filter-chip'));

            setupPaginatedList({
                items: collectionRows,
                chips: collectionChips,
                chipDataAttr: 'data-mode',
                getCategory: (row) => row.getAttribute('data-mode'),
                searchInput: document.getElementById('collections-search-input'),
                clearBtn: document.getElementById('collections-search-clear'),
                prevBtn: document.getElementById('collections-prev-btn'),
                nextBtn: document.getElementById('collections-next-btn'),
                pageLabel: document.getElementById('collections-page-label'),
                noMatchEl: document.getElementById('collections-no-match'),
                onViewUpdated: function(filtered) {
                    if (collectionsTotalRow) {
                        collectionsTotalRow.style.display = filtered.length > 0 ? '' : 'none';
                    }
                }
            });

            // 2. Bills to Collect Controller
            const toCollectRows = Array.from(document.querySelectorAll('tr.to-collect-row'));
            const breachRows = Array.from(document.querySelectorAll('.breach-row'));
            const breachContainer = document.getElementById('breach-panel-container');
            const toCollectChips = Array.from(document.querySelectorAll('.to-collect-filter-chip'));

            setupPaginatedList({
                items: toCollectRows,
                chips: toCollectChips,
                chipDataAttr: 'data-status',
                getCategory: (row) => row.getAttribute('data-status'),
                searchInput: document.getElementById('to-collect-search-input'),
                clearBtn: document.getElementById('to-collect-search-clear'),
                prevBtn: document.getElementById('to-collect-prev-btn'),
                nextBtn: document.getElementById('to-collect-next-btn'),
                pageLabel: document.getElementById('to-collect-page-label'),
                noMatchEl: document.getElementById('to-collect-no-match'),
                onViewUpdated: function(filtered, activeStatus, activeText) {
                    let visibleBreaches = 0;
                    const canShowBreach = (activeStatus === 'All' || activeStatus === 'Overdue');

                    breachRows.forEach(row => {
                        const matchesText = (activeText === '') || row.textContent.toLowerCase().includes(activeText);
                        const isVisible = canShowBreach && matchesText;
                        row.style.display = isVisible ? 'flex' : 'none';
                        if (isVisible) visibleBreaches++;
                    });

                    if (breachContainer) {
                        breachContainer.style.display = (breachRows.length > 0 && visibleBreaches > 0) ? 'block' : 'none';
                    }
                }
            });
        });
    </script>
@endsection