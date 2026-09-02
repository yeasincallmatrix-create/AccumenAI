@extends('layouts.standalone')

@section('title', 'Receivables — AccumenAI')
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
    <h4>Accounts Receivable</h4>
    <p>Outstanding customer balances derived from posted journal entries, with aging buckets based on journal date.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('accounting.reports.receivables') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">As of</label>
                <input type="date" class="form-control form-control-sm" name="as_of_date" value="{{ $asOf }}">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('accounting.reports.receivables') }}">Reset</a>
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
                    <th>Customer</th>
                    <th>Phone</th>
                    <th class="text-end">Current</th>
                    <th class="text-end">31–60</th>
                    <th class="text-end">61–90</th>
                    <th class="text-end">90+</th>
                    <th class="text-end">Receivable</th>
                    <th class="text-end">Net</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->phone ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float) $customer->aging['current'], 2) }}</td>
                        <td class="text-end">{{ number_format((float) $customer->aging['31_60'], 2) }}</td>
                        <td class="text-end">{{ number_format((float) $customer->aging['61_90'], 2) }}</td>
                        <td class="text-end">{{ number_format((float) $customer->aging['91_plus'], 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $customer->receivable, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $customer->net, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No outstanding receivables.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-semibold">
                    <td colspan="3">Total receivables</td>
                    <td class="text-end">{{ number_format($totals['receivable'], 2) }}</td>
                    <td class="text-end" colspan="5"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endsection