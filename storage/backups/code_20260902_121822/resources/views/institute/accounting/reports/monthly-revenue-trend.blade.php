@extends('layouts.standalone')
@section('title', 'Monthly Revenue Trend — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Monthly Revenue Trend</h4>
    <p>Monthly income, expense and net income over a date range.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-end">Income</th>
                    <th class="text-end">Expense</th>
                    <th class="text-end">Net</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($months as $m)
                    <tr>
                        <td>{{ $m->month_label }}</td>
                        <td class="text-end">{{ number_format($m->total_income, 2) }}</td>
                        <td class="text-end">{{ number_format($m->total_expense, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($m->net, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No data for selected period.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td>Total</td>
                    <td class="text-end">{{ number_format($months->sum('total_income'), 2) }}</td>
                    <td class="text-end">{{ number_format($months->sum('total_expense'), 2) }}</td>
                    <td class="text-end">{{ number_format($months->sum('net'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
