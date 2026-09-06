@extends('layouts.institute')

@section('title', 'Expiry Alerts — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Expiry Alerts</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.stock.index') }}">
            <i class="bi bi-arrow-left me-1"></i>All Stock
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card bg-danger text-white"><div class="card-body">
            <h6 class="card-title">Critical (≤ 7 days)</h6>
            <h2 class="card-text">{{ $summary['critical_count'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark"><div class="card-body">
            <h6 class="card-title">Warning (8–30 days)</h6>
            <h2 class="card-text">{{ $summary['warning_count'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-secondary text-white"><div class="card-body">
            <h6 class="card-title">Expired (in stock)</h6>
            <h2 class="card-text">{{ $summary['expired_count'] }}</h2>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0 text-danger">Critical — expiring within 7 days</h6></div>
    <div class="card-body">
        @if(count($alerts['critical']) > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Medicine</th><th>Batch</th><th>Qty</th><th>Expiry</th><th>Days Left</th></tr></thead>
                    <tbody>
                        @foreach($alerts['critical'] as $alert)
                        <tr>
                            <td>{{ $alert['medicine']->display_name ?? 'N/A' }}</td>
                            <td>{{ $alert['batch'] }}</td>
                            <td>{{ $alert['quantity'] }}</td>
                            <td>{{ $alert['expiry_date']?->format('d M Y') }}</td>
                            <td><span class="badge bg-danger">{{ $alert['days_left'] }}d</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No critical batches.</p>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0 text-warning">Warning — expiring within 30 days</h6></div>
    <div class="card-body">
        @if(count($alerts['warning']) > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Medicine</th><th>Batch</th><th>Qty</th><th>Expiry</th><th>Days Left</th></tr></thead>
                    <tbody>
                        @foreach($alerts['warning'] as $alert)
                        <tr>
                            <td>{{ $alert['medicine']->display_name ?? 'N/A' }}</td>
                            <td>{{ $alert['batch'] }}</td>
                            <td>{{ $alert['quantity'] }}</td>
                            <td>{{ $alert['expiry_date']?->format('d M Y') }}</td>
                            <td><span class="badge bg-warning text-dark">{{ $alert['days_left'] }}d</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No warning batches.</p>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Expired — still holding quantity</h6></div>
    <div class="card-body">
        @if(count($alerts['info']) > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Medicine</th><th>Batch</th><th>Qty</th><th>Expired</th><th>Overdue</th></tr></thead>
                    <tbody>
                        @foreach($alerts['info'] as $alert)
                        <tr class="table-danger">
                            <td>{{ $alert['medicine']->display_name ?? 'N/A' }}</td>
                            <td>{{ $alert['batch'] }}</td>
                            <td>{{ $alert['quantity'] }}</td>
                            <td>{{ $alert['expiry_date']?->format('d M Y') }}</td>
                            <td><span class="badge bg-secondary">{{ $alert['days_overdue'] }}d ago</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No expired stock on hand.</p>
        @endif
    </div>
</div>
@endsection
