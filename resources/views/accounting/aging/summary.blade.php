@extends('layouts.standalone')

@section('title', 'Aging Summary — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Aging Summary</h4>
    <p>Receivables vs payables at a glance, as of {{ $asOf->toDateString() }}.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

@include('accounting.aging._nav')

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Receivables (AR)</small>
            <h4 class="mb-0 mt-1">{{ number_format($ar['grand_total'], 2) }}</h4>
            <small class="text-muted">{{ $ar['row_count'] }} invoices</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Payables (AP)</small>
            <h4 class="mb-0 mt-1">{{ number_format($ap['grand_total'], 2) }}</h4>
            <small class="text-muted">{{ $ap['row_count'] }} invoices</small>
        </div>
    </div>
    <div class="col-md-4">
        @php $net = $ar['grand_total'] - $ap['grand_total']; @endphp
        <div class="admin-card text-center">
            <small class="text-muted">Net Position (AR − AP)</small>
            <h4 class="mb-0 mt-1 {{ $net >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($net, 2) }}</h4>
            <small class="text-muted">as of {{ $asOf->toDateString() }}</small>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Bucket</th>
                    <th class="text-end">AR Count</th>
                    <th class="text-end">AR Amount</th>
                    <th class="text-end">AP Count</th>
                    <th class="text-end">AP Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ar['buckets'] as $key => $bucket)
                    @php $apBucket = $ap['buckets'][$key] ?? ['rows' => [], 'total' => 0, 'label' => $bucket['label']]; @endphp
                    <tr>
                        <td>{{ $bucket['label'] }}</td>
                        <td class="text-end">{{ count($bucket['rows']) }}</td>
                        <td class="text-end">{{ number_format($bucket['total'], 2) }}</td>
                        <td class="text-end">{{ count($apBucket['rows']) }}</td>
                        <td class="text-end">{{ number_format($apBucket['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td>Total</td>
                    <td class="text-end">{{ $ar['row_count'] }}</td>
                    <td class="text-end">{{ number_format($ar['grand_total'], 2) }}</td>
                    <td class="text-end">{{ $ap['row_count'] }}</td>
                    <td class="text-end">{{ number_format($ap['grand_total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
