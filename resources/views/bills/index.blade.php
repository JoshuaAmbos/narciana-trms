@extends('layouts.app')

@section('title', 'Billing')

@section('content')
    @error('lease') <div class="alert alert-err">{{ $message }}</div> @enderror
    @error('current_reading') <div class="alert alert-err">{{ $message }}</div> @enderror
    @error('previous_reading') <div class="alert alert-err">{{ $message }}</div> @enderror
    @error('reading_date') <div class="alert alert-err">{{ $message }}</div> @enderror

    {{-- System improvement: Monthly financial & utility summary metrics --}}
    <div class="cards">
        <div class="card">
            <div class="n">@peso($stats['total_amount'])</div>
            <div class="l">Total billed ({{ $stats['total_count'] }} {{ Str::plural('bill', $stats['total_count']) }})</div>
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
            <div class="l">Electric bills ({{ number_format($stats['total_kwh'], 1) }} kWh metered)</div>
        </div>
    </div>

    {{-- Section 1: To bill --}}
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
                                    {{-- "Latetobill" should be overdue --}}
                                    <span class="tag t-red">Overdue</span>
                                @endif
                            </td>
                            <td><b>{{ $row['lease']->room->room_no }}</b> <span class="sub nowrap">{{ $row['lease']->room->room_type }}</span></td>
                            <td>{{ $row['lease']->tenant->full_name }}</td>
                            <td>
                                @if ($row['lease']->room->is_metered)
                                    <span class="tag t-amber">Reading needed</span>
                                    <div class="sub nowrap">
                                        Last: <b>{{ number_format((float) ($row['last_reading']?->current_reading ?? 0), 2) }}</b> kWh
                                        @if ($row['last_reading'])
                                            <span class="muted">({{ $row['last_reading']->reading_date->format('j M') }})</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="muted">No meter (appliances)</span>
                                @endif
                            </td>
                            <td class="actions">
                                @if ($row['lease']->room->is_metered)
                                    <button type="button" class="btn sec"
                                            onclick='openQuickEncode({
                                                lease_id: {{ $row['lease']->lease_id }},
                                                room_no: @json($row['lease']->room->room_no),
                                                room_type: @json($row['lease']->room->room_type),
                                                tenant: @json($row['lease']->tenant->full_name),
                                                due_date: @json($row['due_date']->toDateString()),
                                                due_date_fmt: @json($row['due_date']->format("j M Y")),
                                                rent: {{ (float) $row['lease']->monthly_rent }},
                                                prev_reading: {{ (float) ($row['last_reading']?->current_reading ?? 0) }},
                                                rate_per_kwh: {{ (float) $settings->kwh_rate }},
                                                default_date: @json(min(today(), $row['due_date']->copy()->subDay())->toDateString())
                                            })'>Encode meter</button>
                                    <a href="{{ route('bills.create', ['lease' => $row['lease']->lease_id]) }}" class="btn">Generate</a>
                                @else
                                    <a href="{{ route('bills.create', ['lease' => $row['lease']->lease_id]) }}" class="btn">Generate</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    {{-- Section 2: Interactive Meter Calculation in Billing --}}
    <div class="meter-calc-card">
        <h3>⚡ Electric Meter Calculator <span class="muted" style="font-weight: normal; font-size: 13px;">(Active rate: @peso($settings->kwh_rate) / kWh)</span></h3>
        <div class="calc-grid">
            <div class="f" style="margin: 0;">
                <label for="calc_tool_prev" style="font-size: 12px; margin-bottom: 2px;">Previous reading (kWh)</label>
                <input type="number" id="calc_tool_prev" step="0.01" min="0" placeholder="0.00" style="padding: 6px 10px; font-size: 13.5px;" oninput="runMeterCalcTool()">
            </div>
            <div class="f" style="margin: 0;">
                <label for="calc_tool_curr" style="font-size: 12px; margin-bottom: 2px;">Current reading (kWh)</label>
                <input type="number" id="calc_tool_curr" step="0.01" min="0" placeholder="0.00" style="padding: 6px 10px; font-size: 13.5px;" oninput="runMeterCalcTool()">
            </div>
            <div>
                <span class="muted" style="display: block; font-size: 12px; margin-bottom: 4px;">Consumption</span>
                <b id="calc_tool_kwh" class="amber" style="font-size: 16px;">0.00</b> kWh
            </div>
            <div>
                <span class="muted" style="display: block; font-size: 12px; margin-bottom: 4px;">Computed Electric Bill</span>
                <b id="calc_tool_bill" class="money red" style="font-size: 16px;">₱0.00</b>
            </div>
            <div class="calc-formula-row" id="calc_tool_formula_row">
                <span class="muted">Formula:</span> <span id="calc_tool_formula">0.00 kWh × ₱{{ number_format((float) $settings->kwh_rate, 2) }}</span> = <b class="money" id="calc_tool_result">₱0.00</b>
            </div>
        </div>
    </div>

    {{-- Section 3: Bills due in month --}}
    <div class="panel">
        <h2>
            Bills due in {{ $month->format('F Y') }}
            <span class="month-nav">
                <a href="{{ route('bills.index', ['month' => $month->copy()->subMonth()->format('Y-m'), 'status' => $statusFilter, 'search' => $searchQuery]) }}" class="btn sec">‹</a>
                <a href="{{ route('bills.index', ['month' => today()->format('Y-m'), 'status' => $statusFilter]) }}" class="btn sec" title="Current month">Today</a>
                <a href="{{ route('bills.index', ['month' => $month->copy()->addMonth()->format('Y-m'), 'status' => $statusFilter, 'search' => $searchQuery]) }}" class="btn sec">›</a>
                <a href="{{ route('meter-readings.index') }}" class="btn sec">Meter readings</a>
            </span>
        </h2>

        {{-- Filter & Search Bar --}}
        <form method="GET" action="{{ route('bills.index') }}" class="billing-filter-bar">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <div class="billing-filter-group">
                <span class="muted" style="font-size: 13px;">Status:</span>
                @foreach (['All', 'Unpaid', 'Overdue', 'Paid'] as $st)
                    <a href="{{ route('bills.index', ['month' => $month->format('Y-m'), 'status' => $st, 'search' => $searchQuery]) }}"
                       class="chip {{ $statusFilter === $st ? 'on' : '' }}">{{ $st }}</a>
                @endforeach
            </div>
            <div class="billing-filter-group">
                <input type="text" name="search" class="search-input" placeholder="Search room or tenant..." value="{{ $searchQuery }}">
                <button type="submit" class="btn sec" style="padding: 6px 12px; font-size: 13px;">Search</button>
                @if ($searchQuery !== '' || $statusFilter !== 'All')
                    <a href="{{ route('bills.index', ['month' => $month->format('Y-m')]) }}" class="btn sec" style="padding: 6px 10px; font-size: 13px;">Clear</a>
                @endif
            </div>
        </form>

        @if ($bills->isEmpty())
            <p class="muted">No bills due in {{ $month->format('F Y') }} {{ $searchQuery ? "matching '{$searchQuery}'" : '' }}.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Due</th><th>Room</th><th>Tenant</th><th class="r">Rent</th><th class="r">Water</th>
                        <th class="r">Electricity / Meter calculation</th><th class="r">Total</th><th>Status</th><th></th>
                    </tr>
                    @foreach ($bills as $bill)
                        @php
                            $reading = $bill->meterReading;
                            $kwhUsed = $reading ? max(0, (float) $reading->current_reading - (float) $reading->previous_reading) : 0;
                        @endphp
                        <tr>
                            <td class="nowrap">
                                {{ $bill->due_date->format('j M') }}
                                @if ($bill->status === 'Overdue' || ($bill->status === 'Unpaid' && $bill->due_date->lt(today())))
                                    <span class="tag t-red" style="font-size: 11px; padding: 2px 6px;">Overdue</span>
                                @endif
                            </td>
                            <td><b>{{ $bill->lease->room->room_no }}</b> <span class="sub nowrap">{{ $bill->lease->room->room_type }}</span></td>
                            <td>{{ $bill->lease->tenant->full_name }}</td>
                            <td class="money">@peso($bill->rent_amount)</td>
                            <td class="money">@peso($bill->water_amount)</td>
                            <td class="money">
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
                            <td class="money"><b>@peso($bill->total_amount)</b></td>
                            <td><x-bill-status :status="$bill->status" /></td>
                            <td class="actions"><a href="{{ route('bills.show', $bill) }}" class="btn sec">Statement</a></td>
                        </tr>
                    @endforeach
                    <tr class="tot-row">
                        <td colspan="5">{{ $totals['count'] }} {{ Str::plural('bill', $totals['count']) }}</td>
                        <td class="money" style="font-size: 13px;">
                            @if ($stats['total_kwh'] > 0)
                                <span class="sub" style="font-weight: normal;">{{ number_format($stats['total_kwh'], 1) }} kWh metered</span><br>
                            @endif
                            <b>@peso($bills->sum(fn ($b) => (float) $b->electricity_amount + (float) $b->appliance_amount))</b>
                        </td>
                        <td class="money"><b>@peso($totals['amount'])</b></td>
                        <td colspan="2"></td>
                    </tr>
                </table>
            </div>
        @endif
    </div>

    {{-- Quick Encode Modal: Bill of the meter encoded directly in Billing --}}
    <div id="quick-encode-modal" class="modal-backdrop" hidden>
        <div class="modal-box">
            <h2>
                <span>⚡ Encode Meter Reading & Generate Bill</span>
                <button type="button" class="modal-close-btn" onclick="closeQuickEncode()">&times;</button>
            </h2>

            <form method="POST" action="{{ route('bills.store') }}" id="quick-encode-form">
                @csrf
                <input type="hidden" name="lease_id" id="qe_lease_id">
                <input type="hidden" name="due_date" id="qe_due_date">

                <div class="facts" style="margin-bottom: 14px;">
                    <div><dt>Room</dt><dd id="qe_room_display">-</dd></div>
                    <div><dt>Tenant</dt><dd id="qe_tenant_display">-</dd></div>
                    <div><dt>Due Date</dt><dd id="qe_due_display">-</dd></div>
                    <div><dt>Monthly Rent</dt><dd id="qe_rent_display">-</dd></div>
                </div>

                <div class="row">
                    <div class="f">
                        <label for="qe_previous_reading">Previous reading (kWh)</label>
                        <input type="number" id="qe_previous_reading" name="previous_reading" step="0.01" min="0" required oninput="calcQuickEncode()">
                    </div>
                    <div class="f">
                        <label for="qe_current_reading">Current reading (kWh)</label>
                        <input type="number" id="qe_current_reading" name="current_reading" step="0.01" min="0" required autofocus oninput="calcQuickEncode()">
                    </div>
                </div>

                <div class="row">
                    <div class="f">
                        <label for="qe_reading_date">Reading date</label>
                        <input type="date" id="qe_reading_date" name="reading_date" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="f">
                        <label for="qe_source">Source</label>
                        <select id="qe_source" name="source">
                            @foreach ($sources as $source)
                                <option value="{{ $source }}">{{ $source }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Live Meter Calculation Display --}}
                <div class="meter-calc-card" style="margin-top: 10px;">
                    <h3 style="margin-bottom: 6px;">Meter Calculation</h3>
                    <div class="calc-grid">
                        <div><span class="muted">Consumption:</span> <b id="qe_calc_kwh" class="amber">0.00</b> kWh</div>
                        <div><span class="muted">Rate:</span> <span id="qe_calc_rate">₱0.00/kWh</span></div>
                        <div><span class="muted">Electricity Bill:</span> <b id="qe_calc_amount" class="money red">₱0.00</b></div>
                        <div class="calc-formula-row">
                            <span class="muted">Calculation:</span> <span id="qe_calc_formula">0.00 kWh × ₱0.00 = ₱0.00</span>
                        </div>
                    </div>
                    <div id="qe_warning" class="err" style="display: none; margin-top: 8px;">Current reading cannot be lower than previous reading.</div>
                </div>

                <div class="form-actions" style="margin-top: 16px;">
                    <button type="submit" class="btn" id="qe_submit_btn">Save & Generate Bill</button>
                    <button type="button" class="btn sec" onclick="closeQuickEncode()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const activeKwhRate = {{ (float) $settings->kwh_rate }};

        // Quick meter calculation tool
        function runMeterCalcTool() {
            const prev = parseFloat(document.getElementById('calc_tool_prev').value) || 0;
            const curr = parseFloat(document.getElementById('calc_tool_curr').value) || 0;
            const diff = Math.max(0, curr - prev);
            const bill = diff * activeKwhRate;

            document.getElementById('calc_tool_kwh').textContent = diff.toFixed(2);
            document.getElementById('calc_tool_bill').textContent = '₱' + bill.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('calc_tool_formula').textContent = diff.toFixed(2) + ' kWh × ₱' + activeKwhRate.toFixed(2);
            document.getElementById('calc_tool_result').textContent = '₱' + bill.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Quick encode modal logic
        let currentModalRate = activeKwhRate;
        function openQuickEncode(data) {
            document.getElementById('qe_lease_id').value = data.lease_id;
            document.getElementById('qe_due_date').value = data.due_date;
            document.getElementById('qe_room_display').textContent = 'Room ' + data.room_no + ' (' + data.room_type + ')';
            document.getElementById('qe_tenant_display').textContent = data.tenant;
            document.getElementById('qe_due_display').textContent = data.due_date_fmt;
            document.getElementById('qe_rent_display').textContent = '₱' + data.rent.toLocaleString('en-US', { minimumFractionDigits: 2 });

            document.getElementById('qe_previous_reading').value = data.prev_reading.toFixed(2);
            document.getElementById('qe_current_reading').value = '';
            document.getElementById('qe_reading_date').value = data.default_date;
            currentModalRate = data.rate_per_kwh || activeKwhRate;
            document.getElementById('qe_calc_rate').textContent = '₱' + currentModalRate.toFixed(2) + '/kWh';

            calcQuickEncode();

            const modal = document.getElementById('quick-encode-modal');
            modal.removeAttribute('hidden');
            setTimeout(() => {
                document.getElementById('qe_current_reading').focus();
            }, 50);
        }

        function closeQuickEncode() {
            document.getElementById('quick-encode-modal').setAttribute('hidden', '');
        }

        function calcQuickEncode() {
            const prev = parseFloat(document.getElementById('qe_previous_reading').value) || 0;
            const curr = parseFloat(document.getElementById('qe_current_reading').value);
            const warningEl = document.getElementById('qe_warning');
            const submitBtn = document.getElementById('qe_submit_btn');

            if (isNaN(curr)) {
                document.getElementById('qe_calc_kwh').textContent = '0.00';
                document.getElementById('qe_calc_amount').textContent = '₱0.00';
                document.getElementById('qe_calc_formula').textContent = '0.00 kWh × ₱' + currentModalRate.toFixed(2) + ' = ₱0.00';
                warningEl.style.display = 'none';
                submitBtn.disabled = false;
                return;
            }

            if (curr < prev) {
                warningEl.style.display = 'block';
                submitBtn.disabled = true;
            } else {
                warningEl.style.display = 'none';
                submitBtn.disabled = false;
            }

            const diff = Math.max(0, curr - prev);
            const amt = diff * currentModalRate;

            document.getElementById('qe_calc_kwh').textContent = diff.toFixed(2);
            document.getElementById('qe_calc_amount').textContent = '₱' + amt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('qe_calc_formula').textContent = diff.toFixed(2) + ' kWh × ₱' + currentModalRate.toFixed(2) + ' = ₱' + amt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Close modal when clicking on backdrop
        document.getElementById('quick-encode-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQuickEncode();
            }
        });
    </script>
@endsection
