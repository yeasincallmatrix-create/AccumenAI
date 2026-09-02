@extends('layouts.standalone')

@section('title', 'Profit & Loss — AccumenAI')
@section('page_title', 'Accounting Reports')

@push('styles')
<style>
    @media print {
        .topbar, .standalone-heading .btn, .filter-card { display: none !important; }
        .standalone-heading h4 { font-size: 1.4rem; }
        .admin-card { box-shadow: none !important; border: none !important; }
    }
</style>
@endpush

@section('content')

<div class="standalone-heading">
    <h4>Profit &amp; Loss</h4>
    <p>Revenue and expenses between {{ $from }} and {{ $to }}.</p>
    <div class="d-flex gap-2 flex-wrap">
        <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.reports.profit-loss') }}">
            <div>
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
            </div>
            <div>
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
            </div>
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="admin-card">
            <h6 class="card-title">Income</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @forelse ($statement['income'] as $row)
                            <tr>
                                <td>{{ $row->code }} — {{ $row->name }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) $row->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No income recorded.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td>Total income</td>
                            <td class="text-end text-success">{{ number_format($statement['total_income'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card">
            <h6 class="card-title">Expenses</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @forelse ($statement['expense'] as $row)
                            <tr>
                                <td>{{ $row->code }} — {{ $row->name }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) $row->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No expenses recorded.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td>Total expenses</td>
                            <td class="text-end text-danger">{{ number_format($statement['total_expense'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mt-3">
    <div class="d-flex justify-content-between align-items-center">
        <span class="fw-semibold fs-5">Net profit / (loss)</span>
        <span class="fw-semibold fs-5 {{ $statement['net'] < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($statement['net'], 2) }}</span>
    </div>
</div>

@endsection