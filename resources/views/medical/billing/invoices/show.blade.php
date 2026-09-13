@extends('layouts.institute')

@section('title', 'Invoice — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ clinical_no($invoice->invoice_number) }}
            <span class="badge bg-{{ $invoice->type_class }}">{{ strtoupper($invoice->type) }}</span>
            <span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'pending' ? 'warning text-dark' : 'secondary') }}">{{ $invoice->status_text }}</span>
        </h4>
    </div>
    <div class="page-header-actions">
        @if(!in_array($invoice->status, ['draft', 'cancelled'], true))
            <a class="btn btn-primary" href="{{ route('medical.billing.invoices.print', $invoice) }}">
                <i class="bi bi-file-earmark-pdf me-1"></i>PDF
            </a>
        @endif
        @if($invoice->status === 'pending')
            <a class="btn btn-warning" href="{{ route('medical.billing.invoices.edit', $invoice) }}">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        @endif
        <a class="btn btn-secondary" href="{{ route('medical.billing.invoices.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Invoice Info</h6></div>
            <div class="card-body">
                <p><strong>Patient:</strong>
                    @if($invoice->patient)
                        <a href="{{ route('medical.patients.show', $invoice->patient) }}">{{ $invoice->patient->full_name }}</a>
                        <span class="text-muted">({{ clinical_no($invoice->patient->mr_number) }})</span>
                    @else
                        N/A
                    @endif
                </p>
                <p><strong>Invoice Date:</strong> <x-tdate :value="$invoice->invoice_date" fallback="d M Y" /></p>
                <p><strong>Due Date:</strong>
                    <x-tdate :value="$invoice->due_date" fallback="d M Y" />
                    @if($invoice->isOverdue())
                        <span class="badge bg-danger">Overdue</span>
                    @endif
                </p>
                <p class="mb-0"><strong>Notes:</strong> {{ $invoice->notes ?? '—' }}</p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Line Items</h6></div>
            <div class="card-body">
                @if(count($items) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Description</th><th>Amount</th><th>Qty</th><th>Discount</th><th class="text-end">Line Total</th></tr></thead>
                            <tbody>
                                @foreach($items as $item)
                                <tr>
                                    <td>{{ $item['description'] ?? '' }}</td>
                                    <td>৳{{ number_format($item['amount'] ?? 0, 2) }}</td>
                                    <td>{{ $item['quantity'] ?? 1 }}</td>
                                    <td>৳{{ number_format($item['discount'] ?? 0, 2) }}</td>
                                    <td class="text-end">৳{{ number_format(($item['amount'] ?? 0) * ($item['quantity'] ?? 1), 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No line items.</p>
                @endif
                <hr>
                <p class="mb-1"><strong>Subtotal:</strong> ৳{{ number_format($invoice->subtotal, 2) }}</p>
                <p class="mb-1"><strong>Tax (5%):</strong> ৳{{ number_format($invoice->tax, 2) }}</p>
                <p class="mb-1"><strong>Discount:</strong> ৳{{ number_format($invoice->discount, 2) }}</p>
                <p class="mb-0 fs-5"><strong>Total:</strong> ৳{{ number_format($invoice->total, 2) }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Payment Status</h6></div>
            <div class="card-body">
                <p><strong>Paid:</strong> ৳{{ number_format($invoice->paid_amount, 2) }}</p>
                <p><strong>Due:</strong> ৳{{ number_format($invoice->due_amount, 2) }}</p>
                <p><strong>Method:</strong> {{ $invoice->payment_method ? ucfirst(str_replace('_', ' ', $invoice->payment_method)) : '—' }}</p>
                <p class="mb-0"><strong>Reference:</strong> {{ $invoice->payment_reference ?? '—' }}</p>
            </div>
        </div>

        @if(!in_array($invoice->status, ['paid', 'cancelled', 'draft'], true))
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Record Payment</h6></div>
            <div class="card-body">
                <form action="{{ route('medical.billing.invoices.payment', $invoice) }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="amount">Amount (৳) <span class="text-danger">*</span></label>
                                <input type="number" id="amount" name="amount" min="0.01" step="0.01" max="{{ $invoice->due_amount }}"
                                       class="form-control @error('amount') is-invalid @enderror"
                                       value="{{ old('amount', $invoice->due_amount) }}" required>
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="method">Method <span class="text-danger">*</span></label>
                                <select id="method" name="method" class="form-select @error('method') is-invalid @enderror" required>
                                    @foreach(['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank Transfer', 'mobile_banking' => 'Mobile Banking', 'tpa' => 'TPA / Insurance', 'other' => 'Other'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="reference">Reference</label>
                                <input type="text" id="reference" name="reference" maxlength="100"
                                       class="form-control @error('reference') is-invalid @enderror"
                                       value="{{ old('reference') }}" placeholder="Receipt / txn id">
                                @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-cash-coin me-1"></i>Record Payment
                    </button>
                </form>
            </div>
        </div>
        @endif

        @if($invoice->tpaClaims->count() > 0)
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Linked TPA Claims</h6></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    @foreach($invoice->tpaClaims as $claim)
                    <li class="border-bottom py-1">
                        <a href="{{ route('medical.tpa.claims.show', $claim) }}">{{ clinical_no($claim->claim_number) }}</a>
                        <span class="badge bg-secondary float-end">{{ ucfirst($claim->status) }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
