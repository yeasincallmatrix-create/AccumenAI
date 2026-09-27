@extends('layouts.standalone')

@section('title', 'AR Aging — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>AR Aging Report (Receivables)</h4>
    <p>Outstanding customer invoices bucketed by days overdue, as of {{ $asOf->toDateString() }}.</p>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('accounting.aging.export', ['type' => 'ar_aging', 'as_of' => $asOf->toDateString()]) }}">
            <i class="bi bi-download"></i> CSV
        </a>
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

@include('accounting.aging._nav')

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('accounting.aging.ar') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">As of</label>
                <x-tdate-input class="form-control form-control-sm" name="as_of" value="{{ $asOf->toDateString() }}" />
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('accounting.aging.ar') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Bucket</th>
                    <th>Range (days)</th>
                    <th class="text-end">Count</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['buckets'] as $key => $bucket)
                    <tr>
                        <td>{{ $bucket['label'] }}</td>
                        <td class="text-muted">{{ $bucket['min'] }}–{{ $bucket['max'] ?? '∞' }}</td>
                        <td class="text-end">{{ count($bucket['rows']) }}</td>
                        <td class="text-end">{{ number_format($bucket['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="2">Total Outstanding</td>
                    <td class="text-end">{{ $report['row_count'] }}</td>
                    <td class="text-end">{{ number_format($report['grand_total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="admin-card mt-3">
    <h6 class="mb-3">Detail</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Due Date</th>
                    <th class="text-end">Days Overdue</th>
                    <th>Bucket</th>
                    <th class="text-end">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @php $shown = 0; @endphp
                @foreach ($report['buckets'] as $key => $bucket)
                    @foreach ($bucket['rows'] as $row)
                        @php $shown++; @endphp
                        <tr>
                            <td>{{ $row['invoice_number'] }}</td>
                            <td>{{ $row['due_date'] ?? '—' }}</td>
                            <td class="text-end">{{ $row['days_overdue'] }}</td>
                            <td>{{ $bucket['label'] }}</td>
                            <td class="text-end">{{ number_format($row['outstanding'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
                @if ($shown === 0)
                    <tr><td colspan="5" class="text-muted text-center py-3">No outstanding receivables for this date.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
