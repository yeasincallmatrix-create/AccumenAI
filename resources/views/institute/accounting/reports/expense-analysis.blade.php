@extends('layouts.standalone')
@section('title', 'Expense Analysis — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Expense Analysis</h4>
    <p>Detailed expense breakdown by account with percentage of total.</p>
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
                    <th>#</th>
                    <th>Account</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">% of Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $i => $exp)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>{{ $exp->code }} — {{ $exp->name }}</td>
                        <td class="text-end">{{ number_format($exp->balance, 2) }}</td>
                        <td class="text-end">{{ $exp->pct }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No expenses found.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="2">Total Expenses</td>
                    <td class="text-end">{{ number_format($total, 2) }}</td>
                    <td class="text-end">100%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
