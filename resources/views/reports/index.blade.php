@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="cards">
        <a href="{{ route('reports.collections') }}" class="card card-link"><div class="n">@peso($summary['All'])</div><div class="l">Collected in {{ $month->format('F Y') }}</div></a>
        <a href="{{ route('reports.collections') }}" class="card card-link"><div class="n">@peso($summary['Cash'])</div><div class="l">Cash</div></a>
        <a href="{{ route('reports.collections') }}" class="card card-link"><div class="n">@peso($summary['GCash'])</div><div class="l">GCash</div></a>
        <a href="{{ route('reports.due') }}" class="card card-link"><div class="n red">@peso($summary['unpaid'])</div><div class="l">Unpaid / overdue (due within 7 days, as of today)</div></a>
    </div>

    <div class="panel">
        <h2>Available reports <span class="muted">Open a report, then Print (or Save as PDF in Chrome)</span></h2>
        <div class="table-wrap">
            <table class="fit">
                @foreach ([
                    ['reports.collections', 'Monthly collection summary', 'Cash vs GCash, per receiver, every payment in a month'],
                    ['reports.due', 'Due and overdue tenants', 'Bills not paid yet, with the penalty so far'],
                    ['reports.statement', 'Statement of account per tenant', 'Every bill and payment of one lease, with the running balance'],
                    ['reports.concerns', 'Concern history per room', 'Concerns per room or shared area, repeated issues, costs'],
                    ['reports.inquiries', 'Inquiries and room demand', 'Room types asked for, rented, referred, still waiting'],
                ] as [$route, $label, $about])
                    <tr>
                        <td><b>{{ $label }}</b><span class="sub">{{ $about }}</span></td>
                        <td class="actions"><a href="{{ route($route) }}" class="btn sec">Open</a></td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endsection
