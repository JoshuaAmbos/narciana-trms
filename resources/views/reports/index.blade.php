@extends('layouts.app')

@section('title', 'Operational Reports')

@section('content')
    {{-- KPI Overview Cards --}}
    <div class="cards">
        <a href="{{ route('reports.collections') }}" class="card card-link">
            <div class="n">@peso($summary['All'])</div>
            <div class="l">Total Collected ({{ $month->format('F Y') }})</div>
        </a>
        <a href="{{ route('reports.collections') }}" class="card card-link">
            <div class="n">@peso($summary['Cash'])</div>
            <div class="l">Cash Collected</div>
        </a>
        <a href="{{ route('reports.collections') }}" class="card card-link">
            <div class="n">@peso($summary['GCash'])</div>
            <div class="l">GCash Collected</div>
        </a>
        <a href="{{ route('reports.due') }}" class="card card-link">
            <div class="n red">@peso($summary['unpaid'])</div>
            <div class="l">Outstanding Arrears (Due Within 7 Days)</div>
        </a>
    </div>

    <div class="panel reports-container">
        <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; margin-bottom: 14px;">
            <h2 style="margin: 0;">Operational Reports</h2>
            <span class="muted" style="font-size: 13px;">Select a report to generate, filter, and print or export as PDF.</span>
        </div>

        @php
            $reportsList = [
                [
                    'route' => 'reports.collections',
                    'title' => 'Monthly Collection Summary',
                    'desc' => 'Reconciles payments with ACK numbers, payment mode (Cash vs. GCash), collectors, and amounts.',
                    'badge' => 'Financial Audit',
                ],
                [
                    'route' => 'reports.due',
                    'title' => 'Due And Overdue Tenants',
                    'desc' => 'Identifies delinquent accounts, days overdue, and computed penalties.',
                    'badge' => 'Delinquency',
                ],
                [
                    'route' => 'reports.statement',
                    'title' => 'Billing Statements Per Tenant',
                    'desc' => 'Comprehensive historical billing invoices, charges, payments, and running balance.',
                    'badge' => 'Tenant Ledger',
                ],
                [
                    'route' => 'reports.concerns',
                    'title' => 'Concern History Per Room',
                    'desc' => 'Maintenance audit tracking repair frequency, costs, and tenant charges.',
                    'badge' => 'Maintenance',
                ],
                [
                    'route' => 'reports.inquiries',
                    'title' => 'Inquiries And Room Demand',
                    'desc' => 'Aggregates conversion metrics (Aircon vs. Basic across Waiting, Rented, Referred, and Declined).',
                    'badge' => 'Demand Matrix',
                ],
                [
                    'route' => 'reports.settlements',
                    'title' => 'Move-Out Settlements',
                    'desc' => 'Summary of departed leases, deposit deductions (maintenance fee ₱500, damages, utilities), and net refunds/excess.',
                    'badge' => 'Move-Out Audit',
                ],
            ];
        @endphp

        {{-- Landscape Tablet & Desktop Ledger Table --}}
        <div class="table-wrap report-table-wrap">
            <table class="fit">
                <thead>
                    <tr>
                        <th>Report Name &amp; Purpose</th>
                        <th>Category</th>
                        <th class="r" style="width: 44px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reportsList as $item)
                        <tr class="report-row-clickable"
                            onclick="window.location='{{ route($item['route']) }}'"
                            tabindex="0"
                            role="button"
                            aria-label="Open {{ $item['title'] }}">
                            <td>
                                <div style="font-size: 15px; font-weight: 700; color: var(--navy);">{{ $item['title'] }}</div>
                                <div class="sub" style="font-size: 13px; margin-top: 3px;">{{ $item['desc'] }}</div>
                            </td>
                            <td class="nowrap">
                                <span class="tag t-sub" style="font-weight: 600;">{{ $item['badge'] }}</span>
                            </td>
                            <td class="r" style="color: var(--muted); vertical-align: middle;">
                                <x-icon name="chevron-right" size="20" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Portrait Tablet (~712px - 800px) & Mobile Stacked Cards --}}
        <div class="report-cards">
            @foreach ($reportsList as $item)
                <div class="report-card"
                     onclick="window.location='{{ route($item['route']) }}'"
                     tabindex="0"
                     role="button"
                     aria-label="Open {{ $item['title'] }}">
                    <div class="report-card-head">
                        <div>
                            <div class="report-card-title">{{ $item['title'] }}</div>
                            <div class="report-card-desc">{{ $item['desc'] }}</div>
                        </div>
                        <div class="report-card-badge">
                            <span class="tag t-sub">{{ $item['badge'] }}</span>
                            <span class="report-chevron"><x-icon name="chevron-right" size="18" /></span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
