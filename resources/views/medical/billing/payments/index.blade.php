@extends('layouts.institute')

@section('title', 'Payments — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Payment History</h4>
        <p class="text-muted small mb-0">Derived from invoice rows — payments are recorded directly on invoices.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.billing.invoices.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Invoices
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <select name="method" class="form-select" onchange="this.form.submit()">
                        <option value="">All Methods</option>
                        @foreach(['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'mobile_banking' => 'Mobile Banking', 'tpa' => 'TPA / Insurance', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" onchange="this.form.submit()">
                </div>
                <div class="col-md-3">
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 text-end">
                    <a href="{{ route('medical.billing.payments.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Invoice No</th><th>Patient</th><th>Method</th><th>Reference</th><th>Paid</th><th>Due</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td>
                            <a href="{{ route('medical.billing.invoices.show', $invoice) }}"><strong>{{ $invoice->invoice_number }}</strong></a>
                        </td>
                        <td>{{ $invoice->patient->full_name ?? 'N/A' }}</td>
                        <td>{{ $invoice->payment_method ? ucfirst(str_replace('_', ' ', $invoice->payment_method)) : '—' }}</td>
                        <td class="small">{{ $invoice->payment_reference ?? '—' }}</td>
                        <td>৳{{ number_format($invoice->paid_amount, 2) }}</td>
                        <td>৳{{ number_format($invoice->due_amount, 2) }}</td>
                        <td><span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : 'secondary' }}">{{ $invoice->status_text }}</span></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-cash-stack fs-2 d-block mb-2"></i>
                            No payments recorded.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $invoices->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
