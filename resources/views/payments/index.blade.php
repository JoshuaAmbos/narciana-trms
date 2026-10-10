@extends('layouts.app')

@section('title', 'Payments')

@section('content')
    @error('bill') <div class="alert alert-err">{{ $message }}</div> @enderror

    <div class="panel printable">
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
            <div class="card"><div class="n">{{ $collections->count() }}</div><div class="l">{{ Str::plural('payment', $collections->count()) }}
                @foreach ($byReceiver as $role => $amount) · {{ $role }} @peso($amount) @endforeach</div></div>
        </div>

        @if ($collections->isEmpty())
            <p class="muted">No payments in {{ $month->format('F Y') }}.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Date</th><th>ACK no.</th><th>Room</th><th>Tenant</th><th>Mode</th><th>Received by</th><th class="r">Amount</th></tr>
                    @foreach ($collections as $p)
                        <tr>
                            <td class="nowrap">{{ $p->payment_date->format('j M') }}</td>
                            <td><a href="{{ route('payments.show', $p) }}">{{ $p->ack_no }}</a></td>
                            <td><b>{{ $p->bill->lease->room->room_no }}</b></td>
                            <td>{{ $p->bill->lease->tenant->full_name }}</td>
                            <td>{{ $p->mode }}@if ($p->gcash_ref_no)<span class="sub nowrap">ref. {{ $p->gcash_ref_no }}</span>@endif</td>
                            <td>{{ $p->user->role }}</td>
                            <td class="money">@peso($p->amount)</td>
                        </tr>
                    @endforeach
                    <tr class="tot-row"><td colspan="6">Total (Cash @peso($totals['Cash']) · GCash @peso($totals['GCash']))</td><td class="money">@peso($totals['All'])</td></tr>
                </table>
            </div>
        @endif
    </div>

    <div class="panel no-print">
        <h2>Bills to collect <span class="muted">Oldest first · full payment only</span></h2>
        @if ($breachedLeases->isNotEmpty())
            <div class="breach-panel" style="margin-bottom: 20px; border: 1px solid #fda4af; background: #fff1f2; border-radius: 8px; overflow: hidden;">
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
                        @php($bLease = $breach['lease'])
                        <div class="breach-row cursor-pointer hover:bg-rose-50/60 transition-colors"
                             style="min-height: 44px; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #fecdd3; cursor: pointer;"
                             onclick="openBreachModal({{ $bLease->lease_id }})"
                             data-modal-target="breach-modal"
                             data-lease-id="{{ $bLease->lease_id }}"
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
                <table>
                    <tr><th>Due date</th><th>Room</th><th>Tenant</th><th class="r">Bill</th><th>Status</th><th class="r">Penalty so far</th><th class="r">Due if paid today</th><th class="r">Action</th></tr>
                    @foreach ($toCollect as $row)
                        @php($b = $row['bill'])
                        <tr @if (!empty($row['is_breached'])) style="background: #fff5f5;" @endif>
                            <td class="nowrap">{{ $b->due_date->format('j M Y') }}</td>
                            <td><b>{{ $b->lease->room->room_no }}</b></td>
                            <td>{{ $b->lease->tenant->full_name }}</td>
                            <td class="money">@peso((float) $b->total_amount - (float) $b->penalty_amount)</td>
                            <td>
                                @if ($row['today']['days_late'] > 0)
                                    <span class="tag t-red">Overdue {{ $row['today']['days_late'] }} {{ Str::plural('day', $row['today']['days_late']) }}</span>
                                @elseif ($b->due_date->gt(today()))
                                    <span class="tag t-amber">Due in {{ today()->diffInDays($b->due_date) }} {{ Str::plural('day', today()->diffInDays($b->due_date)) }}</span>
                                @elseif ($b->due_date->isToday())
                                    <span class="tag t-amber">Due today</span>
                                @elseif (!empty($row['today']['in_grace']))
                                    <span class="tag t-amber">Grace Period (Day {{ $row['today']['grace_day'] }} of {{ $row['today']['grace_days'] }})</span>
                                @else
                                    <span class="tag t-amber">In grace period</span>
                                @endif

                                @if (!empty($row['is_breached']))
                                    <div>
                                        <span class="inline-block mt-1 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded" style="display: inline-block; margin-top: 4px; padding: 2px 6px; font-size: 11px; font-weight: 600; color: #be123c; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px;">
                                            Deposit Exhausted
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="money">@peso($row['today']['penalty'])</td>
                            <td class="money"><b>@peso($row['today']['total'])</b></td>
                            <td class="actions r">
                                @if ($row['can_pay'])
                                    <a href="{{ route('payments.create', ['bill' => $b->bill_id]) }}" class="btn" style="min-height: 44px; display: inline-flex; align-items: center;">Record payment</a>
                                @else
                                    <span class="muted small">older bill first</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
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
                {{-- Financial Breakdown (Clean Summary Card) --}}
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

                {{-- Explanation Callout --}}
                <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #9f1239; line-height: 1.5;">
                    <p style="margin: 0;">This tenant's unpaid obligations have completely consumed their security deposit buffer. Continued non-payment leaves the account with zero security coverage.</p>
                </div>

                {{-- Action Buttons (Footer) --}}
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
    </script>
@endsection
