@extends('layouts.standalone')

@section('title', 'Balance Sheet — AccumenAI')
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
    <h4>Balance Sheet</h4>
    <p>Assets, liabilities and equity as of {{ $asOf }}. Current-period net income is included in equity.</p>
    <div class="d-flex gap-2 flex-wrap">
        <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.reports.balance-sheet') }}">
            <div>
                <label class="form-label mb-1">As of</label>
                <input type="date" class="form-control form-control-sm" name="as_of_date" value="{{ $asOf }}">
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

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card">
            <h6 class="card-title">Assets</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @forelse ($statement['assets'] as $row)
                            <tr>
                                <td>{{ $row->code }} — {{ $row->name }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) $row->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No assets.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td>Total assets</td>
                            <td class="text-end">{{ number_format($statement['total_assets'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card">
            <h6 class="card-title">Liabilities</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @forelse ($statement['liabilities'] as $row)
                            <tr>
                                <td>{{ $row->code }} — {{ $row->name }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) $row->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No liabilities.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td>Total liabilities</td>
                            <td class="text-end">{{ number_format($statement['total_liabilities'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card">
            <h6 class="card-title">Equity</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @forelse ($statement['equity'] as $row)
                            <tr>
                                <td>{{ $row->code }} — {{ $row->name }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) $row->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No equity.</td></tr>
                        @endforelse
                        <tr>
                            <td>Net income</td>
                            <td class="text-end fw-semibold">{{ number_format($statement['net_income'], 2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td>Total equity</td>
                            <td class="text-end">{{ number_format($statement['total_equity'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection