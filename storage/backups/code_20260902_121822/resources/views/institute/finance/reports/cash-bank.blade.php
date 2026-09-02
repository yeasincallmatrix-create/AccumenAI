@extends('layouts.standalone')

@section('title', 'Cash & Bank — AccumenAI')
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
    <h4>Cash & Bank Summary</h4>
    <p>Balances of all cash and bank accounts as of {{ $asOf }}.</p>
    <div class="d-flex gap-2 flex-wrap">
        <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.reports.cash-bank') }}">
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

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Account</th>
                    <th>Kind</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-muted">{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        <td>
                            @if ($row->is_cash)<span class="badge text-bg-info me-1">Cash</span>@endif
                            @if ($row->is_bank)<span class="badge text-bg-primary">Bank</span>@endif
                        </td>
                        <td class="text-end fw-semibold">{{ number_format((float) $row->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No cash/bank accounts.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-semibold">
                    <td colspan="3">Total cash & bank</td>
                    <td class="text-end">{{ number_format($rows->sum('balance'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endsection