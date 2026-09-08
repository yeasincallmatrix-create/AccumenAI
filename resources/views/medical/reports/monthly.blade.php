@extends('layouts.institute')

@section('title', 'Monthly Report — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Monthly Report — Last 30 Days</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.reports.daily') }}">
            <i class="bi bi-calendar-day me-1"></i>Daily
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.reports.revenue') }}">
            <i class="bi bi-graph-up me-1"></i>Revenue
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">Billed (30d)</h6><h2 class="card-text">৳{{ number_format($revenue['total_revenue'] ?? 0, 2) }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white"><div class="card-body">
            <h6 class="card-title">Collected (30d)</h6><h2 class="card-text">৳{{ number_format($revenue['total_paid'] ?? 0, 2) }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-dark"><div class="card-body">
            <h6 class="card-title">Invoices</h6><h2 class="card-text">{{ $revenue['count'] ?? 0 }}</h2>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Daily Breakdown</h6></div>
    <div class="card-body">
        @if($byDay->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Day</th><th>Invoices</th><th>Billed</th><th>Collected</th></tr></thead>
                    <tbody>
                        @foreach($byDay as $row)
                        <tr>
                            <td><x-tdate :value="$row->day" fallback="d M Y" /></td>
                            <td>{{ $row->count }}</td>
                            <td>৳{{ number_format($row->revenue, 2) }}</td>
                            <td>৳{{ number_format($row->collected, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No billing activity in the last 30 days.</p>
        @endif
    </div>
</div>
@endsection
