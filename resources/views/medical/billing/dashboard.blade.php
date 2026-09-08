@extends('layouts.institute')

@section('title', 'Billing — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Billing</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-info me-1" href="{{ route('medical.billing.payments.index') }}">
            <i class="bi bi-cash-stack me-1"></i>Payments
        </a>
        <a class="btn btn-primary" href="{{ route('medical.billing.invoices.create') }}">
            <i class="bi bi-plus-lg me-1"></i>New Invoice
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-success text-white"><div class="card-body">
            <h6 class="card-title">Collected (30d)</h6>
            <h2 class="card-text">৳{{ number_format($revenue['total_paid'] ?? 0, 2) }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">Billed (30d)</h6>
            <h2 class="card-text">৳{{ number_format($revenue['total_revenue'] ?? 0, 2) }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark"><div class="card-body">
            <h6 class="card-title">Outstanding</h6>
            <h2 class="card-text">৳{{ number_format($outstanding, 2) }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-dark"><div class="card-body">
            <h6 class="card-title">Invoices (30d)</h6>
            <h2 class="card-text">{{ $revenue['count'] ?? 0 }}</h2>
        </div></div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Invoices</h6>
                <a href="{{ route('medical.billing.invoices.index') }}" class="btn btn-sm btn-link">View all</a>
            </div>
            <div class="card-body">
                @if($recentInvoices->count() > 0)
                    <ul class="list-unstyled mb-0">
                        @foreach($recentInvoices as $invoice)
                        <li class="border-bottom py-2">
                            <a href="{{ route('medical.billing.invoices.show', $invoice) }}"><strong>{{ $invoice->invoice_number }}</strong></a>
                            <span class="text-muted">· {{ $invoice->patient->full_name ?? 'N/A' }}</span>
                            <span class="float-end">৳{{ number_format($invoice->total, 2) }}
                                <span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'pending' ? 'warning text-dark' : 'secondary') }}">{{ $invoice->status_text }}</span>
                            </span>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">No invoices yet.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Due / Overdue</h6></div>
            <div class="card-body">
                @if($dueInvoices->count() > 0)
                    <ul class="list-unstyled mb-0">
                        @foreach($dueInvoices as $invoice)
                        <li class="border-bottom py-2">
                            <a href="{{ route('medical.billing.invoices.show', $invoice) }}"><strong>{{ $invoice->invoice_number }}</strong></a>
                            <span class="text-muted">· due <x-tdate :value="$invoice->due_date" fallback="d M Y" /></span>
                            <span class="float-end">৳{{ number_format($invoice->due_amount, 2) }}
                                @if($invoice->isOverdue())
                                    <span class="badge bg-danger">Overdue</span>
                                @endif
                            </span>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">Nothing outstanding.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
