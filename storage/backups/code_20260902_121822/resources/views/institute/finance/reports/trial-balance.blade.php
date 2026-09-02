@extends('layouts.standalone')

@section('title', 'Trial Balance — AccumenAI')
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
    <h4>Trial Balance</h4>
    <p>All accounts with summed debits, credits and net balance as of {{ $asOf }}. Derived from posted journals and opening balances.</p>
    <div class="d-flex gap-2 flex-wrap">
        <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.reports.trial-balance') }}">
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
                    <th>Type</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                @php $debit = 0; $credit = 0; @endphp
                @forelse ($rows as $row)
                    @php $debit += (float) $row->debit; $credit += (float) $row->credit; @endphp
                    <tr>
                        <td class="text-muted">{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        <td><span class="badge text-bg-light border">{{ ucfirst($row->type) }}</span></td>
                        <td class="text-end">{{ number_format((float) $row->debit, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $row->credit, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $row->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No entries yet.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="3">Totals</td>
                        <td class="text-end">{{ number_format($debit, 2) }}</td>
                        <td class="text-end">{{ number_format($credit, 2) }}</td>
                        <td class="text-end">{{ number_format($debit - $credit, 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@endsection