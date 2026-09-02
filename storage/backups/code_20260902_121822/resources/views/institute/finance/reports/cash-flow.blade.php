@extends('layouts.standalone')

@section('title', 'Cash Flow Statement — AccumenAI')
@section('page_title', 'Finance Reports')

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
    <h4>Cash Flow Statement</h4>
    <p>Cash movements for the period {{ $from }} to {{ $to }} (direct method).</p>
    <div class="d-flex gap-2 flex-wrap">
        <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.reports.cash-flow') }}">
            <div>
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
            </div>
            <div>
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
            </div>
            <div>
                <label class="form-label mb-1">Fiscal year</label>
                <select class="form-select form-select-sm" name="fiscal_year_id">
                    <option value="">— None —</option>
                    @foreach ($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" @selected((string) $fiscalYearId === (string) $fy->id)>{{ $fy->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </form>
    </div>
</div>

<div class="admin-card">
    @if ((float) ($statement['unclassified_amount'] ?? 0) != 0)
        <div class="alert alert-warning mb-0 mx-3 mt-3" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Some cash movements are not classified and are excluded from operating/investing/financing sections.
            Unclassified amount: <strong>{{ number_format((float) $statement['unclassified_amount'], 2) }}</strong>.
        </div>
    @endif
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="w-75">Activity</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr class="fw-semibold">
                    <td colspan="2" class="text-muted small">Operating Activities</td>
                </tr>
                <tr>
                    <td class="ps-4">Cash generated from operations</td>
                    <td class="text-end">{{ number_format((float) $statement['operating'], 2) }}</td>
                </tr>

                <tr class="fw-semibold">
                    <td colspan="2" class="text-muted small">Investing Activities</td>
                </tr>
                <tr>
                    <td class="ps-4">Cash used in investing activities</td>
                    <td class="text-end">{{ number_format((float) $statement['investing'], 2) }}</td>
                </tr>

                <tr class="fw-semibold">
                    <td colspan="2" class="text-muted small">Financing Activities</td>
                </tr>
                <tr>
                    <td class="ps-4">Cash generated from financing activities</td>
                    <td class="text-end">{{ number_format((float) $statement['financing'], 2) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td>Net increase / (decrease) in cash</td>
                    <td class="text-end">{{ number_format((float) $statement['net_change'], 2) }}</td>
                </tr>
                <tr>
                    <td>Cash at beginning of period</td>
                    <td class="text-end">{{ number_format((float) $statement['opening'], 2) }}</td>
                </tr>
                <tr class="fw-bold">
                    <td>Cash at end of period</td>
                    <td class="text-end">{{ number_format((float) $statement['closing'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endsection
