@extends('layouts.institute')

@section('title', 'Invoices — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Invoices</h4>
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

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-2">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        @foreach(['draft' => 'Draft', 'pending' => 'Pending', 'partial' => 'Partial', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        @foreach(['opd' => 'OPD', 'ipd' => 'IPD', 'pharmacy' => 'Pharmacy', 'lab' => 'Lab', 'surgery' => 'Surgery'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="patient_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Patients</option>
                        @foreach($patients as $patient)
                            <option value="{{ $patient->id }}" @selected((string) request('patient_id') === (string) $patient->id)>
                                {{ $patient->full_name }} ({{ $patient->mr_number }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 text-end">
                    <a href="{{ route('medical.billing.invoices.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Invoice No</th><th>Patient</th><th>Type</th><th>Date</th><th>Total</th><th>Due</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td><strong>{{ $invoice->invoice_number }}</strong></td>
                        <td>{{ $invoice->patient->full_name ?? 'N/A' }}</td>
                        <td><span class="badge bg-{{ $invoice->type_class }}">{{ strtoupper($invoice->type) }}</span></td>
                        <td>{{ $invoice->invoice_date?->format('d M Y') }}</td>
                        <td>৳{{ number_format($invoice->total, 2) }}</td>
                        <td>
                            ৳{{ number_format($invoice->due_amount, 2) }}
                            @if($invoice->isOverdue())
                                <span class="badge bg-danger">Overdue</span>
                            @endif
                        </td>
                        <td><span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'pending' ? 'warning text-dark' : 'secondary') }}">{{ $invoice->status_text }}</span></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.billing.invoices.show', $invoice) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($invoice->status === 'pending')
                                    <a href="{{ route('medical.billing.invoices.edit', $invoice) }}" class="btn btn-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-receipt fs-2 d-block mb-2"></i>
                            No invoices found.
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
