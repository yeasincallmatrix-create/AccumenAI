@extends('layouts.institute')

@section('title', 'Daily Report — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Daily Report — <x-tdate :value="today()" fallback="d M Y" /></h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.reports.monthly') }}">
            <i class="bi bi-calendar-month me-1"></i>Monthly
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.reports.revenue') }}">
            <i class="bi bi-graph-up me-1"></i>Revenue
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">Billed Today</h6><h2 class="card-text">৳{{ number_format($revenue['total_revenue'] ?? 0, 2) }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white"><div class="card-body">
            <h6 class="card-title">Collected Today</h6><h2 class="card-text">৳{{ number_format($revenue['total_paid'] ?? 0, 2) }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-dark"><div class="card-body">
            <h6 class="card-title">Invoices</h6><h2 class="card-text">{{ $revenue['count'] ?? 0 }}</h2>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Today's Invoices</h6></div>
    <div class="card-body">
        @if($invoices->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Invoice No</th><th>Patient</th><th>Type</th><th>Total</th><th>Paid</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('medical.billing.invoices.show', $invoice) }}">{{ clinical_no($invoice->invoice_number) }}</a></td>
                            <td>{{ $invoice->patient->full_name ?? 'N/A' }}</td>
                            <td>{{ strtoupper($invoice->type) }}</td>
                            <td>৳{{ number_format($invoice->total, 2) }}</td>
                            <td>৳{{ number_format($invoice->paid_amount, 2) }}</td>
                            <td><span class="badge bg-secondary">{{ $invoice->status_text }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No invoices today.</p>
        @endif
    </div>
</div>
@endsection
