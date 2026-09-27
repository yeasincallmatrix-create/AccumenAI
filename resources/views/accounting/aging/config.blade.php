@extends('layouts.standalone')

@section('title', 'Aging Configuration — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Aging Configuration</h4>
    <p>Read-only view of the bucket definitions and data sources driving the aging reports.</p>
</div>

@include('accounting.aging._nav')

<div class="admin-card mb-3">
    <h6 class="mb-3">Buckets <small class="text-muted">(config/accounting.php → aging.buckets)</small></h6>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Bucket Key</th>
                    <th>Label</th>
                    <th>Range (days overdue)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($buckets as $key => $meta)
                    <tr>
                        <td><code>{{ $key }}</code></td>
                        <td>{{ $meta['label'] }}</td>
                        <td>{{ $meta['min'] }} – {{ $meta['max'] ?? '∞' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <h6 class="mb-3">Data Sources</h6>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Report</th>
                    <th>Table</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>AR Aging</td>
                    <td><code>{{ $sources['ar'] ?? 'invoices' }}</code></td>
                </tr>
                <tr>
                    <td>AP Aging</td>
                    <td><code>{{ $sources['ap'] ?? 'purchase_invoices' }}</code></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
