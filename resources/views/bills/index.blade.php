@extends('layouts.app')

@section('title', 'Billing')

@section('content')
    @error('lease') <div class="alert alert-err">{{ $message }}</div> @enderror
    @error('current_reading') <div class="alert alert-err">{{ $message }}</div> @enderror
    @error('previous_reading') <div class="alert alert-err">{{ $message }}</div> @enderror
    @error('reading_date') <div class="alert alert-err">{{ $message }}</div> @enderror
    @error('amount') <div class="alert alert-err">{{ $message }}</div> @enderror
    @error('bill') <div class="alert alert-err">{{ $message }}</div> @enderror

    {{-- System improvement: Monthly financial & utility summary metrics --}}
    <div class="cards">
        <div class="card">
            <div class="n">@peso($stats['total_amount'])</div>
            <div class="l">Total Billed ({{ $stats['total_count'] }} {{ Str::plural('bill', $stats['total_count']) }})</div>
        </div>
        <div class="card">
            <div class="n green">@peso($stats['paid_amount'])</div>
            <div class="l">Collected ({{ $stats['paid_count'] }} paid)</div>
        </div>
        <div class="card">
            <div class="n {{ $stats['overdue_count'] > 0 ? 'red' : 'amber' }}">@peso($stats['unpaid_amount'])</div>
            <div class="l">Outstanding ({{ $stats['overdue_count'] }} overdue)</div>
        </div>
        <div class="card">
            <div class="n">@peso($stats['total_electricity'])</div>
            <div class="l">Electric Bills ({{ number_format($stats['total_kwh'], 1) }} kWh metered)</div>
        </div>
    </div>

    {{-- Section 1: To bill --}}
    <div class="panel">
        <h2>To Bill <span class="muted">(Due Within {{ \App\Services\BillingService::DAYS_AHEAD }} Days or Overdue)</span></h2>
        @if ($toBill->isEmpty())
            <p class="muted">Nothing to bill right now.</p>
        @else
            <div class="table-wrap">
                <table class="to-bill-table">
                    <thead>
                        <tr>
                            <th>Due Date</th>
                            <th>Room &amp; Setup</th>
                            <th>Tenant</th>
                            <th>Meter Status</th>
                            <th class="actions text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($toBill as $row)
                            <tr class="to-bill-row">
                                <td class="nowrap">
                                    {{ $row['due_date']->format('j M Y') }}
                                    @if ($row['due_date']->lt(today()))
                                        <span class="tag t-red">Overdue</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="javascript:void(0)" onclick="openGenerateBillModal({{ $row['lease']->lease_id }})" class="font-semibold text-blue-600 hover:underline">
                                        Room {{ $row['lease']->room->room_no }}
                                    </a>
                                    <span class="sub nowrap">{{ $row['lease']->room->room_type }}</span>
                                </td>
                                <td>{{ $row['lease']->tenant->full_name }}</td>
                                <td>
                                    @if ($row['lease']->room->is_metered)
                                        <span class="tag t-amber">Reading Needed</span>
                                        <div class="sub nowrap">
                                            Last: <b>{{ number_format((float) ($row['last_reading']?->current_reading ?? 0), 2) }}</b> kWh
                                            @if ($row['last_reading'])
                                                <span class="muted">({{ $row['last_reading']->reading_date->format('j M') }})</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="tag t-sub">No Meter</span>
                                        <div class="sub muted nowrap">Appliances tallied</div>
                                    @endif
                                </td>
                                <td class="actions">
                                    <div class="table-action-group">
                                        <a href="{{ route('bills.create', ['lease' => $row['lease']->lease_id]) }}"
                                           class="btn btn-sm btn-generate"
                                           data-lease-id="{{ $row['lease']->lease_id }}"
                                           onclick="openGenerateBillModal({{ $row['lease']->lease_id }}); return false;">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" x2="12" y1="18" y2="12"/><line x1="9" x2="15" y1="15" y2="15"/></svg>
                                            Generate
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Section 2: Bills due in month --}} 
    {{-- TODO: Implement better month picker --}}
    <div class="panel">
        <h2>
            Bills Due in {{ $month->format('F Y') }}
            <span class="month-nav">
                <a href="{{ route('bills.index', ['month' => $month->copy()->subMonth()->format('Y-m'), 'status' => $statusFilter, 'search' => $searchQuery]) }}" class="btn sec" aria-label="Previous month" title="Previous month">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </a>
                <a href="{{ route('bills.index', ['month' => today()->format('Y-m'), 'status' => $statusFilter]) }}" class="btn sec" title="Current month">Today</a>
                <a href="{{ route('bills.index', ['month' => $month->copy()->addMonth()->format('Y-m'), 'status' => $statusFilter, 'search' => $searchQuery]) }}" class="btn sec" aria-label="Next month" title="Next month">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="{{ route('meter-readings.index') }}" class="btn sec">
                    Meter Readings
                </a>
            </span>
        </h2>

        {{-- Filter & Search Bar --}}
        <div class="billing-filter-bar no-print">
            <div class="billing-controls-wrap">
                <div class="billing-search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="billing-search-input" class="search-input" placeholder="Search room or tenant..." value="{{ $searchQuery ?? request('search') }}" autocomplete="off">
                    <button type="button" id="billing-search-clear" class="search-clear-btn" style="display: {{ ($searchQuery ?? request('search')) ? 'inline-flex' : 'none' }};">&times;</button>
                </div>

                <div class="billing-chips-wrap filter-groups-row">
                    <div class="filter-group-item">
                        <span class="status-label filter-label">Status:</span>
                        @foreach (['All', 'Unpaid', 'Overdue', 'Paid'] as $st)
                            <a href="{{ route('bills.index', ['month' => $month->format('Y-m'), 'status' => $st]) }}"
                               class="chip {{ $statusFilter === $st ? 'on' : '' }}">{{ $st }}</a>
                        @endforeach
                    </div>
                    @if (($statusFilter ?? 'All') !== 'All')
                        <a href="{{ route('bills.index', ['month' => $month->format('Y-m')]) }}" class="btn sec reset-filter-btn">Reset</a>
                    @endif
                </div>
            </div>
        </div>

        @if ($bills->isEmpty())
            <p class="muted">No bills due in {{ $month->format('F Y') }} {{ $searchQuery ? "matching '{$searchQuery}'" : '' }}.</p>
        @else
            {{-- Tablet Landscape & Desktop: 8-Column Tabular Ledger --}}
            <div class="table-wrap bills-table-wrap">
                <table class="bills-ledger-table">
                    <thead>
                        <tr>
                            <th>Due Date</th>
                            <th>Room &amp; Setup</th>
                            <th>Tenant(s)</th>
                            <th class="r">Rent</th>
                            <th class="r">Water</th>
                            <th class="r">Electricity / Appliances</th>
                            <th class="r">Total</th>
                            <th>Status</th>
                            <th class="actions text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bills as $bill)
                            @php
                                $reading = $bill->meterReading;
                                $kwhUsed = $reading ? max(0, (float) $reading->current_reading - (float) $reading->previous_reading) : 0;
                            @endphp
                            <tr class="bill-row" data-bill-id="{{ $bill->bill_id }}" data-search="{{ strtolower($bill->lease->room->room_no . ' ' . $bill->lease->tenant->full_name) }}">
                                <td class="nowrap">
                                    {{ $bill->due_date->format('j M') }}
                                    @if ($bill->status === 'Overdue' || ($bill->status === 'Unpaid' && $bill->due_date->lt(today())))
                                        <span class="tag t-red" style="font-size: 11px; padding: 2px 6px;">Overdue</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="javascript:void(0)" onclick="openStatementModal({{ $bill->bill_id }})" class="font-semibold text-blue-600 hover:underline">
                                        Room {{ $bill->lease->room->room_no }}
                                    </a>
                                    <span class="sub nowrap">{{ $bill->lease->room->room_type }}</span>
                                </td>
                                <td>{{ $bill->lease->tenant->full_name }}</td>
                                <td class="money nowrap">@peso($bill->rent_amount)</td>
                                <td class="money nowrap">@peso($bill->water_amount)</td>
                                <td class="money nowrap">
                                    @if ($reading)
                                        <b>@peso($bill->electricity_amount)</b>
                                        <div class="sub nowrap" style="font-size: 11px; line-height: 1.3;">
                                            <b>{{ number_format($kwhUsed, 2) }} kWh</b> × @peso($reading->rate_per_kwh)
                                            <br><span class="muted">{{ number_format((float) $reading->previous_reading, 2) }} → {{ number_format((float) $reading->current_reading, 2) }}</span>
                                        </div>
                                    @elseif ((float) $bill->appliance_amount > 0)
                                        @peso($bill->appliance_amount)
                                        <div class="sub muted" style="font-size: 11px;">Appliances</div>
                                    @else
                                        <span class="muted">₱0.00</span>
                                    @endif
                                </td>
                                <td class="money nowrap"><b>@peso($bill->total_amount)</b></td>
                                <td><x-bill-status :status="$bill->status" /></td>
                                <td class="actions">
                                    <div class="table-action-group">
                                        <button type="button" class="btn sec" onclick="openStatementModal({{ $bill->bill_id }})">Statement</button>

                                        @if (!auth()->user()->can('manage-billing'))
                                            <button type="button" class="btn disabled" disabled title="Caretakers cannot record collections." aria-disabled="true">Record Payment</button>
                                        @elseif ($bill->status === 'Paid')
                                            <button type="button" class="btn disabled" disabled title="Bill is already fully paid." aria-disabled="true">Record Payment</button>
                                        @else
                                            <button type="button" class="btn" onclick="openRecordPaymentModal({{ $bill->bill_id }})">Record Payment</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr id="bills-table-empty" class="empty-filter-row" data-filter-id="billing-search-input" style="display: none;">
                            <td colspan="9" class="muted" style="text-align: center; padding: 24px;">No matching billing records found.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="tot-row">
                            <td colspan="3">{{ $totals['count'] }} {{ Str::plural('bill', $totals['count']) }}</td>
                            <td class="money nowrap">@peso($totals['rent'])</td>
                            <td class="money nowrap">@peso($totals['water'])</td>
                            <td class="money nowrap">
                                @if ($stats['total_kwh'] > 0)
                                    <span class="sub" style="font-weight: normal;">{{ number_format($stats['total_kwh'], 1) }} kWh metered</span><br>
                                @endif
                                <b>@peso($totals['utility'])</b>
                            </td>
                            <td class="money nowrap"><b>@peso($totals['amount'])</b></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Tablet Portrait & Mobile: Stacked Billing Cards --}}
            <div class="bill-cards">
                @foreach ($bills as $bill)
                    @php
                        $reading = $bill->meterReading;
                        $kwhUsed = $reading ? max(0, (float) $reading->current_reading - (float) $reading->previous_reading) : 0;
                    @endphp
                    <div class="bill-card" data-bill-id="{{ $bill->bill_id }}" data-search="{{ strtolower($bill->lease->room->room_no . ' ' . $bill->lease->tenant->full_name) }}">
                        <div class="bill-card-head">
                            <div class="bill-card-title">
                                <span class="bill-card-room">
                                    <a href="javascript:void(0)" onclick="openStatementModal({{ $bill->bill_id }})" class="font-semibold text-blue-600 hover:underline">
                                        Room {{ $bill->lease->room->room_no }}
                                    </a>
                                </span>
                                <span class="bill-card-tenant">{{ $bill->lease->tenant->full_name }}</span>
                                <span class="bill-card-type sub">{{ $bill->lease->room->room_type }}</span>
                            </div>
                        </div>

                        <div class="bill-card-specs">
                            <div class="spec-item">
                                <span class="spec-label">Rent</span>
                                <span class="spec-value nowrap">@peso($bill->rent_amount)</span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Water</span>
                                <span class="spec-value nowrap">@peso($bill->water_amount)</span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">{{ $bill->meterReading ? 'Electricity' : 'Appliances' }}</span>
                                <span class="spec-value nowrap">
                                    @if ($reading)
                                        @peso($bill->electricity_amount)
                                        <span class="spec-sub">({{ number_format($kwhUsed, 1) }} kWh)</span>
                                    @elseif ((float) $bill->appliance_amount > 0)
                                        @peso($bill->appliance_amount)
                                    @else
                                        ₱0.00
                                    @endif
                                </span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">Due Date</span>
                                <span class="spec-value nowrap">{{ $bill->due_date->format('j M Y') }}</span>
                            </div>
                        </div>

                        <div class="bill-card-footer">
                            <div class="bill-card-total-box">
                                <span class="total-label">Total Due</span>
                                <span class="total-amount nowrap">@peso($bill->total_amount)</span>
                            </div>
                            <div class="bill-card-status-box">
                                <x-bill-status :status="$bill->status" />
                            </div>
                        </div>

                        <div class="bill-card-actions">
                            <button type="button" class="btn sec" onclick="openStatementModal({{ $bill->bill_id }})">Statement</button>

                            @if (!auth()->user()->can('manage-billing'))
                                <button type="button" class="btn disabled" disabled title="Caretakers cannot record collections." aria-disabled="true">Record Payment</button>
                            @elseif ($bill->status === 'Paid')
                                <button type="button" class="btn disabled" disabled title="Bill is already fully paid." aria-disabled="true">Record Payment</button>
                            @else
                                <button type="button" class="btn" onclick="openRecordPaymentModal({{ $bill->bill_id }})">Record Payment</button>
                            @endif
                        </div>
                    </div>
                @endforeach
                <div id="bills-cards-empty" class="empty-filter-card muted" data-filter-id="billing-search-input" style="display: none; text-align: center; padding: 24px; border: 1px dashed var(--border, #cbd5e1); border-radius: 8px;">
                    No matching billing records found.
                </div>
            </div>
        @endif
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 1: Generate Bill Modal (#generateBillModal) --}}
    {{-- ========================================================================= --}}
    <div id="generateBillModal" class="modal-backdrop" hidden>
        <div class="modal-box modal-lg">
            <div class="modal-header">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="header-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span id="gbm_title">Generate Bill</span>
                </h2>
                <button type="button" class="modal-close-btn" onclick="closeGenerateBillModal()" aria-label="Close modal">&times;</button>
            </div>

            <form method="POST" action="{{ route('bills.store') }}" id="generate-bill-form">
                @csrf
                <input type="hidden" name="lease_id" id="gbm_lease_id">
                <input type="hidden" name="due_date" id="gbm_due_date">
                <input type="hidden" name="concerns_submitted" value="1">

                {{-- Lease Overview --}}
                <div class="modal-summary-grid">
                    <div><span class="summary-lbl">Room</span><b id="gbm_room_display">-</b></div>
                    <div><span class="summary-lbl">Tenant</span><b id="gbm_tenant_display">-</b></div>
                    <div><span class="summary-lbl">Due Date</span><b id="gbm_due_display">-</b></div>
                    <div><span class="summary-lbl">Utilities Period</span><span id="gbm_period_display" class="nowrap">-</span></div>
                </div>

                {{-- Pre-calculated components --}}
                <div class="bill-calc-breakdown">
                    <div class="breakdown-row">
                        <span>Base Monthly Rent</span>
                        <b class="money" id="gbm_rent_display">₱0.00</b>
                    </div>
                    <div class="breakdown-row">
                        <div>
                            <span>Water Charge</span>
                            <div class="sub muted" id="gbm_water_desc">0 occupants × @peso($settings->water_rate)</div>
                        </div>
                        <b class="money" id="gbm_water_display">₱0.00</b>
                    </div>
                    <div class="breakdown-row" id="gbm_appliance_row">
                        <div>
                            <span>Appliance Surcharges</span>
                            <div class="sub muted" id="gbm_appliance_desc">0 appliances × @peso($settings->appliance_fee)</div>
                        </div>
                        <b class="money" id="gbm_appliance_display">₱0.00</b>
                    </div>
                </div>

                {{-- Meter Encoding Section (Only shown for metered/Aircon rooms) --}}
                <div id="gbm_meter_section" class="meter-encoding-card" style="display: none;">
                    <div class="meter-card-header">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meter-icon"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <b>Sub-Meter Reading &amp; Electricity</b>
                        <span class="rate-badge">Rate: @peso($settings->kwh_rate) / kWh</span>
                    </div>

                    <div class="row">
                        <div class="f">
                            <label for="gbm_previous_reading">Previous reading (kWh) <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <input type="number" id="gbm_previous_reading" name="previous_reading" step="0.01" min="0" readonly class="input-readonly">
                        </div>
                        <div class="f">
                            <label for="gbm_current_reading">Current reading (kWh) <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <input type="number" id="gbm_current_reading" name="current_reading" step="0.01" min="0" oninput="recalcGenerateBillModal()">
                        </div>
                    </div>

                    <div class="row">
                        <div class="f">
                            <label for="gbm_reading_date">Reading date <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <input type="date" id="gbm_reading_date" name="reading_date" max="{{ today()->toDateString() }}">
                        </div>
                        <div class="f">
                            <label for="gbm_source">Source <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                            <select id="gbm_source" name="source">
                                @foreach ($sources as $source)
                                    <option value="{{ $source }}">{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Live Meter Calc Display --}}
                    <div class="meter-live-calc-strip">
                        <div class="calc-strip-item">
                            <span class="muted">Consumption:</span>
                            <b id="gbm_calc_kwh" class="amber">0.00</b> kWh
                        </div>
                        <div class="calc-strip-item">
                            <span class="muted">Active Rate:</span>
                            <span>@peso($settings->kwh_rate)</span>
                        </div>
                        <div class="calc-strip-item">
                            <span class="muted">Electricity Bill:</span>
                            <b id="gbm_calc_elec" class="money red">₱0.00</b>
                        </div>
                    </div>
                    <div id="gbm_calc_warning" class="err" style="display: none; margin-top: 6px;">Current reading cannot be lower than previous reading.</div>
                </div>

                {{-- Optional Other Charges (Chargeable Concerns) --}}
                <div id="gbm_concerns_container" style="display: none; margin-top: 14px;">
                    <fieldset class="resolution-box charge-list">
                        <legend>Other Charges: Chargeable Concerns</legend>
                        <div id="gbm_concerns_list"></div>
                    </fieldset>
                </div>

                {{-- Total Amount Due Banner --}}
                <div class="bill-total-banner">
                    <span>Total Amount to Bill:</span>
                    <b class="money" id="gbm_grand_total">₱0.00</b>
                </div>

                <div class="form-actions" style="margin-top: 18px;">
                    <button type="submit" class="btn" id="gbm_submit_btn">Generate Bill</button>
                    <button type="button" class="btn sec" onclick="closeGenerateBillModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 2: Billing Statement Modal (#statementModal) --}}
    {{-- ========================================================================= --}}
    <div id="statementModal" class="modal-backdrop" hidden>
        <div class="modal-box modal-lg printable-modal">
            <div class="modal-header no-print">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="header-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span>Billing Statement</span>
                    <span id="sm_status_badge"></span>
                </h2>
                <button type="button" class="modal-close-btn" onclick="closeStatementModal()" aria-label="Close modal">&times;</button>
            </div>

            <div id="sm_statement_body" class="statement-modal-body">
                {{-- Dynamically populated statement content --}}
            </div>

            <div class="modal-actions-bar no-print">
                <button type="button" class="btn sec" onclick="printModalStatement()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-icon"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                    Print / Save PDF
                </button>
                <div id="sm_record_payment_wrap" style="display: inline-block;">
                    <button type="button" class="btn" id="sm_record_payment_btn" onclick="openPaymentFromStatement()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-icon"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                        Record Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL 3: Record Payment Modal (#recordPaymentModal) --}}
    {{-- ========================================================================= --}}
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
                        <label for="rpm_payment_date">Payment date <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                        <input id="rpm_payment_date" name="payment_date" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="f">
                        <label for="rpm_mode">Payment mode <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                        <select id="rpm_mode" name="mode" onchange="toggleRpmMode(this.value)">
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                        </select>
                    </div>
                </div>

                <div class="f" id="rpm_gcash_wrap" style="display: none;">
                    <label for="rpm_gcash_ref_no">GCash Reference No. (13 digits) <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
                    <input id="rpm_gcash_ref_no" name="gcash_ref_no" type="text" inputmode="numeric" maxlength="20" placeholder="e.g. 1001234567890">
                    <div class="hint">Exactly 13 digits from the GCash transaction receipt.</div>
                </div>

                <div class="f">
                    <label for="rpm_amount">Amount received (₱) <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
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

    {{-- Client-side Data Stores & Scripts --}}
    <script>
        const activeKwhRate = {{ (float) $settings->kwh_rate }};
        const activeWaterRate = {{ (float) $settings->water_rate }};
        const activeApplianceFee = {{ (float) $settings->appliance_fee }};

        // Data dictionary for To-Bill queue
        window.toBillData = {!! json_encode($toBillMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

        // Data dictionary for existing Bills
        window.billsData = {!! json_encode($billsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

        function peso(val) {
            return '₱' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // =========================================================================
        // Real-Time Search Bar Query
        // =========================================================================
        document.addEventListener('DOMContentLoaded', function () {
            // Find input by ID or fallback by class/name
            const searchInput = document.getElementById('billing-search-input') 
                             || document.querySelector('input.search-input')
                             || document.querySelector('input[name="search"]');
            const clearBtn = document.getElementById('billing-search-clear');
            const searchBtn = document.getElementById('billing-search-btn');

            if (!searchInput) return;

            // Broad selector targeting rows across ledger tables and mobile cards
            const getSearchTargets = () => {
                const tableRows = Array.from(document.querySelectorAll('table.fit tbody tr, .table-wrap tbody tr, .dashboard-table-view tbody tr'));
                const mobileCards = Array.from(document.querySelectorAll('.dashboard-mobile-cards > div, .bill-card, .dashboard-touch-card'));
                return { tableRows, mobileCards };
            };

            let debounceTimer = null;

            function filterItems(query) {
                const cleanQuery = (query || '').toLowerCase().trim();
                const { tableRows, mobileCards } = getSearchTargets();

                // 1. Filter Table Rows (excluding empty-state "No bills" rows)
                tableRows.forEach(row => {
                    // Skip rows that act as empty-state message containers
                    if (row.querySelector('td[colspan]')) return;

                    const textContent = row.textContent.toLowerCase();
                    const matches = cleanQuery === '' || textContent.includes(cleanQuery);
                    row.style.display = matches ? '' : 'none';
                });

                // 2. Filter Mobile Cards
                mobileCards.forEach(card => {
                    const textContent = card.textContent.toLowerCase();
                    const matches = cleanQuery === '' || textContent.includes(cleanQuery);
                    card.style.display = matches ? '' : 'none';
                });

                // 3. Toggle Clear Icon
                if (clearBtn) {
                    clearBtn.style.display = cleanQuery ? 'block' : 'none';
                }
            }

            // Real-time input listener (120ms debounce)
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    filterItems(this.value);
                }, 120);
            });

            // Handle Clear Button click
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    searchInput.value = '';
                    filterItems('');
                    searchInput.focus();
                });
            }

            if (searchBtn) {
                searchBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    filterItems(searchInput.value);
                });
            }

            // Prevent Enter from reloading the page
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    filterItems(this.value);
                }
            });

            // Initial check if pre-filled on page render
            if (searchInput.value.trim() !== '') {
                filterItems(searchInput.value);
            }
        });

        // =========================================================================
        // Generate Bill Modal Logic
        // =========================================================================
        let currentGbmData = null;

        function handleToBillRowClick(e, leaseId) {
            if (e.target.closest('button, a, input, select')) return;
            openGenerateBillModal(leaseId);
        }

        function openGenerateBillModal(leaseId) {
            const data = window.toBillData[leaseId];
            if (!data) return;
            currentGbmData = data;

            document.getElementById('gbm_lease_id').value = data.lease_id;
            document.getElementById('gbm_due_date').value = data.due_date;
            document.getElementById('gbm_title').textContent = 'Generate Bill: Room ' + data.room_no;
            document.getElementById('gbm_room_display').textContent = 'Room ' + data.room_no + ' (' + data.room_type + ')';
            document.getElementById('gbm_tenant_display').textContent = data.tenant;
            document.getElementById('gbm_due_display').textContent = data.due_date_fmt;
            document.getElementById('gbm_period_display').textContent = data.period_fmt;

            document.getElementById('gbm_rent_display').textContent = peso(data.rent);
            document.getElementById('gbm_water_display').textContent = peso(data.water);
            document.getElementById('gbm_water_desc').textContent = data.occupants + ' occupant' + (data.occupants === 1 ? '' : 's') + ' × ' + peso(activeWaterRate);

            const meterSec = document.getElementById('gbm_meter_section');
            const appRow = document.getElementById('gbm_appliance_row');
            const currInput = document.getElementById('gbm_current_reading');

            if (data.is_metered) {
                meterSec.style.display = 'block';
                appRow.style.display = 'none';
                document.getElementById('gbm_previous_reading').disabled = false;
                document.getElementById('gbm_previous_reading').value = data.prev_reading.toFixed(2);
                currInput.disabled = false;
                currInput.value = '';
                currInput.required = true;
                currInput.style.borderColor = '';
                currInput.classList.remove('has-err');
                document.getElementById('gbm_reading_date').disabled = false;
                document.getElementById('gbm_reading_date').value = data.default_date;
                document.getElementById('gbm_source').disabled = false;
                document.getElementById('gbm_source').value = 'Meter';
            } else {
                meterSec.style.display = 'none';
                appRow.style.display = 'flex';
                document.getElementById('gbm_previous_reading').disabled = true;
                currInput.disabled = true;
                currInput.required = false;
                currInput.value = '';
                currInput.style.borderColor = '';
                currInput.classList.remove('has-err');
                document.getElementById('gbm_reading_date').disabled = true;
                document.getElementById('gbm_source').disabled = true;
                document.getElementById('gbm_appliance_display').textContent = peso(data.appliance_amount);
                document.getElementById('gbm_appliance_desc').textContent = data.appliances + ' appliance' + (data.appliances === 1 ? '' : 's') + ' × ' + peso(activeApplianceFee);
            }

            // Concerns
            const concernsCont = document.getElementById('gbm_concerns_container');
            const concernsList = document.getElementById('gbm_concerns_list');
            concernsList.innerHTML = '';
            if (data.concerns && data.concerns.length > 0) {
                concernsCont.style.display = 'block';
                data.concerns.forEach(c => {
                    const label = document.createElement('label');
                    label.style.display = 'flex';
                    label.style.justifyContent = 'space-between';
                    label.style.alignItems = 'center';
                    label.style.padding = '6px 0';
                    label.innerHTML = `
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="concerns[]" value="${c.id}" checked onchange="recalcGenerateBillModal()">
                            <span>Tenant Repair: ${c.desc} <span class="sub">${c.category} · resolved ${c.resolved}</span></span>
                        </span>
                        <b class="money">${peso(c.cost)}</b>
                    `;
                    concernsList.appendChild(label);
                });
            } else {
                concernsCont.style.display = 'none';
            }

            recalcGenerateBillModal();

            const modal = document.getElementById('generateBillModal');
            modal.removeAttribute('hidden');
            if (data.is_metered) {
                setTimeout(() => currInput.focus(), 60);
            }
        }

        function closeGenerateBillModal() {
            document.getElementById('generateBillModal').setAttribute('hidden', '');
        }

        function recalcGenerateBillModal() {
            if (!currentGbmData) return;
            const data = currentGbmData;

            let electricity = 0;
            let isValid = true;
            const submitBtn = document.getElementById('gbm_submit_btn');
            const warnEl = document.getElementById('gbm_calc_warning');
            const currInput = document.getElementById('gbm_current_reading');

            if (data.is_metered) {
                const prev = parseFloat(document.getElementById('gbm_previous_reading').value) || 0;
                const currVal = currInput.value;
                const curr = parseFloat(currVal);

                if (currVal === '' || isNaN(curr)) {
                    document.getElementById('gbm_calc_kwh').textContent = '0.00';
                    document.getElementById('gbm_calc_elec').textContent = '₱0.00';
                    warnEl.style.display = 'none';
                    currInput.style.borderColor = '';
                    currInput.classList.remove('has-err');
                    electricity = 0;
                } else if (curr < prev) {
                    warnEl.style.display = 'block';
                    isValid = false;
                    currInput.style.borderColor = '#dc2626';
                    currInput.classList.add('has-err');
                    document.getElementById('gbm_calc_kwh').textContent = '0.00';
                    document.getElementById('gbm_calc_elec').textContent = '₱0.00';
                    electricity = 0;
                } else {
                    warnEl.style.display = 'none';
                    currInput.style.borderColor = '';
                    currInput.classList.remove('has-err');
                    const diff = curr - prev;
                    electricity = diff * (data.rate_per_kwh || activeKwhRate);
                    document.getElementById('gbm_calc_kwh').textContent = diff.toFixed(2);
                    document.getElementById('gbm_calc_elec').textContent = peso(electricity);
                }
            }

            // Ticked concerns
            let concernsTotal = 0;
            const ticked = document.querySelectorAll('#gbm_concerns_list input[type="checkbox"]:checked');
            ticked.forEach(chk => {
                const cObj = data.concerns.find(c => c.id == chk.value);
                if (cObj) concernsTotal += cObj.cost;
            });

            const grandTotal = data.rent + data.water + (data.is_metered ? electricity : data.appliance_amount) + concernsTotal;
            document.getElementById('gbm_grand_total').textContent = peso(grandTotal);

            if (data.is_metered && document.getElementById('gbm_current_reading').value === '') {
                submitBtn.disabled = true;
            } else {
                submitBtn.disabled = !isValid;
            }
        }

        // =========================================================================
        // Statement Modal Logic (#statementModal)
        // =========================================================================
        let currentStatementBillId = null;

        function handleBillRowClick(e, billId) {
            if (e.target.closest('button, a, input, select')) return;
            openStatementModal(billId);
        }

        function openStatementModal(billId) {
            const bill = window.billsData[billId];
            if (!bill) return;
            currentStatementBillId = billId;

            const badgeWrap = document.getElementById('sm_status_badge');
            if (bill.status === 'Paid') {
                badgeWrap.innerHTML = '<span class="tag t-green">Paid</span>';
            } else if (bill.status === 'Overdue') {
                badgeWrap.innerHTML = '<span class="tag t-red">Overdue</span>';
            } else {
                badgeWrap.innerHTML = '<span class="tag t-amber">Unpaid</span>';
            }

            let meterRows = '';
            if (bill.is_metered) {
                if (bill.reading) {
                    meterRows = `
                        <tr>
                            <td>
                                Electricity (${bill.reading.kwh.toFixed(2)} kWh × ${peso(bill.reading.rate)})
                                <span class="sub">Reading ${bill.reading.prev.toFixed(2)} → ${bill.reading.curr.toFixed(2)} kWh on ${bill.reading.date} (${bill.reading.source})</span>
                            </td>
                            <td class="money">${peso(bill.electricity)}</td>
                        </tr>
                        <tr>
                            <td>Appliance surcharges <span class="sub">not charged: room has its own meter</span></td>
                            <td class="money">${peso(0)}</td>
                        </tr>
                    `;
                } else {
                    meterRows = `
                        <tr>
                            <td>Electricity <span class="sub amber">Meter unrecorded</span></td>
                            <td class="money">${peso(bill.electricity)}</td>
                        </tr>
                    `;
                }
            } else {
                meterRows = `
                    <tr>
                        <td>Electricity <span class="sub">included in rent: room has no meter</span></td>
                        <td class="money">${peso(0)}</td>
                    </tr>
                    <tr>
                        <td>Appliance surcharges</td>
                        <td class="money">${peso(bill.appliances)}</td>
                    </tr>
                `;
            }

            let concernsRows = '';
            if (bill.concerns && bill.concerns.length > 0) {
                concernsRows = `
                    <tr>
                        <td>Other charges
                            ${bill.concerns.map(c => `<span class="sub">Tenant Repair: ${c.category ? c.category + ' — ' : ''}${c.desc} — ${peso(c.cost)}</span>`).join('')}
                        </td>
                        <td class="money">${peso(bill.other_charges)}</td>
                    </tr>
                `;
            } else if (bill.other_charges > 0) {
                concernsRows = `
                    <tr>
                        <td>Other charges</td>
                        <td class="money">${peso(bill.other_charges)}</td>
                    </tr>
                `;
            }

            let penaltyRow = '';
            if (bill.penalty > 0) {
                penaltyRow = `<tr><td>Penalty</td><td class="money">${peso(bill.penalty)}</td></tr>`;
            }

            let paymentNote = '';
            if (bill.payment) {
                paymentNote = `
                    <div class="paid-note" style="margin-top: 14px;">
                        Paid on <b>${bill.payment.date}</b> by <b>${bill.payment.mode}</b>
                        — acknowledgment <a href="${bill.payment.show_url}"><b>${bill.payment.ack_no}</b></a>.
                    </div>
                `;
            }

            let lateNote = '';
            if (bill.status !== 'Paid' && bill.due_today && bill.due_today.days_late > 0) {
                lateNote = `
                    <div class="due-box no-print" style="margin-top: 12px;">
                        <b class="red">Overdue ${bill.due_today.days_late} day${bill.due_today.days_late === 1 ? '' : 's'} after the grace period.</b>
                        Penalty: <b>${peso(bill.due_today.penalty)}</b> · Total due today: <b>${peso(bill.due_today.total)}</b>
                    </div>
                `;
            }

            const html = `
                <div class="statement" id="printable-statement-target">
                    <div class="statement-head">
                        <div>
                            <b>Narciana House Space Rental</b><br>
                            <span class="muted">Km. 6 Carnation St., Buhangin, Davao City</span>
                        </div>
                        <div class="r">
                            <b>Billing Statement</b><br>
                            <span class="muted">Room ${bill.room_no} (${bill.room_type}) · ${bill.tenant}</span>
                        </div>
                    </div>

                    <table class="sum">
                        <tr>
                            <td>Monthly rent <span class="sub">${bill.rent_period_fmt}</span></td>
                            <td class="money">${peso(bill.rent)}</td>
                        </tr>
                        <tr>
                            <td>Water charge <span class="sub">${bill.period_fmt}</span></td>
                            <td class="money">${peso(bill.water)}</td>
                        </tr>
                        ${meterRows}
                        ${penaltyRow}
                        ${concernsRows}
                        <tr class="tot">
                            <td>Total due on ${bill.due_date_fmt}</td>
                            <td class="money">${peso(bill.total)}</td>
                        </tr>
                    </table>

                    ${paymentNote}
                    ${lateNote}

                    <p class="muted print-note" style="margin-top: 14px;">
                        Bill #${bill.bill_id} · Room ${bill.room_no} · ${bill.tenant} · Status: ${bill.status}.
                        Full payment required on or before due date (no partial payments).
                    </p>
                </div>
            `;

            document.getElementById('sm_statement_body').innerHTML = html;

            const payBtnWrap = document.getElementById('sm_record_payment_wrap');
            if (bill.status !== 'Paid' && bill.can_pay) {
                payBtnWrap.style.display = 'inline-block';
            } else {
                payBtnWrap.style.display = 'none';
            }

            document.getElementById('statementModal').removeAttribute('hidden');
            document.body.classList.add('statement-modal-open');
        }

        function closeStatementModal() {
            document.getElementById('statementModal').setAttribute('hidden', '');
            document.body.classList.remove('statement-modal-open');
        }

        function printModalStatement() {
            document.body.classList.add('statement-modal-open');
            window.print();
        }

        function openPaymentFromStatement() {
            const id = currentStatementBillId;
            closeStatementModal();
            if (id) {
                openRecordPaymentModal(id);
            }
        }

        // =========================================================================
        // Record Payment Modal Logic (#recordPaymentModal)
        // =========================================================================
        function openRecordPaymentModal(billId) {
            const bill = window.billsData[billId];
            if (!bill) return;

            document.getElementById('rpm_bill_id').value = bill.bill_id;
            document.getElementById('rpm_room_display').textContent = 'Room ' + bill.room_no;
            document.getElementById('rpm_tenant_display').textContent = bill.tenant;
            document.getElementById('rpm_due_display').textContent = bill.due_date_fmt;
            document.getElementById('rpm_status_display').innerHTML = bill.status === 'Overdue'
                ? '<span class="tag t-red">Overdue</span>'
                : '<span class="tag t-amber">Unpaid</span>';

            const baseAmount = bill.total - bill.penalty;
            document.getElementById('rpm_base_amount').textContent = peso(baseAmount);

            let dueTotal = bill.total;
            let latePenalty = bill.penalty;
            let daysLate = 0;

            if (bill.due_today) {
                dueTotal = bill.due_today.total;
                latePenalty = bill.due_today.penalty;
                daysLate = bill.due_today.days_late;
            }

            document.getElementById('rpm_penalty_amount').textContent = peso(latePenalty);
            document.getElementById('rpm_total_due').textContent = peso(dueTotal);

            const lateSub = document.getElementById('rpm_late_sub');
            if (daysLate > 0) {
                lateSub.textContent = `(${daysLate} day${daysLate === 1 ? '' : 's'} late after grace period)`;
                lateSub.style.display = 'inline';
            } else {
                lateSub.style.display = 'none';
            }

            // Amount field pre-filled with full amount due
            document.getElementById('rpm_amount').value = dueTotal.toFixed(2);
            document.getElementById('rpm_hint_amount').textContent = peso(dueTotal);

            // Default mode = Cash
            document.getElementById('rpm_mode').value = 'Cash';
            toggleRpmMode('Cash');

            document.getElementById('recordPaymentModal').removeAttribute('hidden');
        }

        function closeRecordPaymentModal() {
            document.getElementById('recordPaymentModal').setAttribute('hidden', '');
        }

        function toggleRpmMode(mode) {
            const gcashWrap = document.getElementById('rpm_gcash_wrap');
            const gcashInput = document.getElementById('rpm_gcash_ref_no');
            if (!gcashWrap || !gcashInput) return;
            if (mode === 'GCash') {
                gcashWrap.style.display = 'block';
                gcashInput.disabled = false;
                gcashInput.required = true;
                setTimeout(() => gcashInput.focus(), 50);
            } else {
                gcashWrap.style.display = 'none';
                gcashInput.disabled = true;
                gcashInput.required = false;
                gcashInput.value = '';
            }
        }

        // =========================================================================
        // Keyboard and Backdrop click handling
        // =========================================================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeGenerateBillModal();
                closeStatementModal();
                closeRecordPaymentModal();
            }
        });

        ['generateBillModal', 'statementModal', 'recordPaymentModal'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.setAttribute('hidden', '');
                        if (this.id === 'statementModal') {
                            document.body.classList.remove('statement-modal-open');
                        }
                    }
                });
            }
        });

        // Auto-open statement modal if requested in URL (e.g. redirected from store)
        document.addEventListener('DOMContentLoaded', function() {
            initRealtimeFilter('billing-search-input', 'billing-search-clear', '.bill-row, .bill-card');

            const params = new URLSearchParams(window.location.search);
            const statementId = params.get('statement');
            if (statementId && window.billsData[statementId]) {
                openStatementModal(statementId);
            }
        });
    </script>
@endsection
