@extends('layouts.standalone')

@section('title', 'Education Finance — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Education Finance</h4>
    <p>Student billing for {{ $institute->name }} — fee structures, invoices, installments, payments, waivers and refunds, all derived from the accounting ledger.</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('finance.education.students.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-people me-1"></i>Student Ledger</a>
        <a href="{{ route('finance.education.fee-structures.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Fee Structure</a>
        <a href="{{ route('finance.education.fee-structures.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-check me-1"></i>Fee Structures</a>
        <a href="{{ route('finance.education.reports.batches') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart-fill me-1"></i>Batch Report</a>
        <a href="{{ route('finance.education.reports.courses') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart-fill me-1"></i>Course Report</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Total billed</div>
            <div class="fs-5 fw-semibold">{{ number_format($metrics['billed'], 2) }}</div>
            <div class="small text-muted">{{ $baseCurrency }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Total collected</div>
            <div class="fs-5 fw-semibold text-success">{{ number_format($metrics['collected'], 2) }}</div>
            <div class="small text-muted">net of refunds</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Outstanding</div>
            <div class="fs-5 fw-semibold text-danger">{{ number_format($metrics['outstanding'], 2) }}</div>
            <div class="small text-muted">overdue {{ number_format($metrics['overdue'], 2) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Discounts &amp; waivers</div>
            <div class="fs-5 fw-semibold">{{ number_format($metrics['discounts'], 2) }}</div>
            <div class="small text-muted">{{ $metrics['waiver_count'] }} waivers ({{ number_format($metrics['waiver_amount'], 2) }})</div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="card-title mb-0">Recent student invoices</h6>
        <a href="{{ route('finance.education.students.index') }}" class="btn btn-sm btn-outline-primary">All students</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Student</th>
                    <th>Type</th>
                    <th class="text-end">Payable</th>
                    <th class="text-end">Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($metrics['recent_invoices'] as $invoice)
                    <tr>
                        <td>
                            <a href="{{ route('finance.education.students.show', $invoice->student_id) }}" class="text-decoration-none">{{ $invoice->invoice_number }}</a>
                            <div class="text-muted small">{{ $invoice->created_at?->format('Y-m-d') }}</div>
                        </td>
                        <td>{{ $invoice->student?->full_name ?? '—' }}</td>
                        <td>{{ str_replace('_', ' ', $invoice->invoice_type) }}</td>
                        <td class="text-end">{{ number_format((float) $invoice->payable_amount, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format((float) $invoice->due_amount, 2) }}</td>
                        <td>
                            <span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : ($invoice->status === 'cancelled' ? 'secondary' : 'danger')) }}">{{ $invoice->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No student invoices yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection