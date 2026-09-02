@extends('layouts.standalone')

@section('title', 'Invoice '.$invoice->invoice_number.' — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Invoice {{ $invoice->invoice_number }}</h4>
    <p>
        {{ $invoice->party?->name ?? $invoice->student?->name ?? 'Walk-in' }}
        @if ($invoice->invoice_type) · {{ str_replace('_', ' ', $invoice->invoice_type) }} @endif
        · {{ $invoice->created_at?->format('Y-m-d') }}
    </p>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        @if ($invoice->status !== 'cancelled' && (float) $invoice->due_amount > 0)
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#paymentForm"><i class="bi bi-cash-coin me-1"></i>Record payment</button>
        @endif
        @if ($invoice->status !== 'cancelled' && (float) $invoice->paid_amount === 0)
            <form method="POST" action="{{ route('finance.invoices.cancel', $invoice) }}" class="d-inline" data-ajax-submit="1" data-confirm="Cancel this invoice? Its sale journal will be reversed.">
                @csrf
                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-lg me-1"></i>Cancel invoice</button>
            </form>
        @endif
        <span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : ($invoice->status === 'cancelled' ? 'secondary' : 'danger')) }}">{{ $invoice->status }}</span>
    </div>
</div>

@if ($invoice->status !== 'cancelled' && (float) $invoice->due_amount > 0)
    <div class="collapse mb-3" id="paymentForm">
        <div class="admin-card">
            <h6 class="card-title">Record payment</h6>
            <form method="POST" action="{{ route('finance.payments.store') }}">
                @csrf
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="amount" placeholder="Due {{ number_format((float) $invoice->due_amount, 2) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Method <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="payment_method" required>
                            @foreach (['cash', 'bank', 'bkash', 'nagad', 'rocket', 'card', 'other'] as $method)
                                <option value="{{ $method }}" @selected(old('payment_method', 'cash') === $method)>{{ ucfirst($method) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Payment method</label>
                        <select class="form-select form-select-sm" name="payment_method_id">
                            <option value="">— None —</option>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Paid at</label>
                        <input type="date" class="form-control form-control-sm" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ref / txn</label>
                        <input type="text" class="form-control form-control-sm" name="transaction_id" value="{{ old('transaction_id') }}" maxlength="100">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-cash-stack me-1"></i>Record payment</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="admin-card mb-3">
            <h6 class="card-title">Items</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>
                                    {{ $item->description }}
                                    @if ($item->coa)
                                        <div class="text-muted small">{{ $item->coa->code }} — {{ $item->coa->name }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((float) $item->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="text-end">Total</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $invoice->total_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end">Discount</td>
                            <td class="text-end">{{ number_format((float) $invoice->discount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end">Payable</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $invoice->payable_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end">Paid</td>
                            <td class="text-end">{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end">Due</td>
                            <td class="text-end fw-semibold text-danger">{{ number_format((float) $invoice->due_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if ($invoice->journal)
            <div class="admin-card">
                <h6 class="card-title">Ledger posting</h6>
                <p class="mb-1">
                    <a href="{{ route('finance.journals.show', $invoice->journal) }}" class="text-decoration-none">{{ $invoice->journal->journal_no }}</a>
                    <span class="badge text-bg-{{ $invoice->journal->status === 'posted' ? 'success' : 'warning' }} ms-1">{{ $invoice->journal->status }}</span>
                </p>
                @if ($invoice->journal->status === 'draft')
                    <form method="POST" action="{{ route('finance.journals.post', $invoice->journal) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-outline-success mt-1" type="submit"><i class="bi bi-check-lg me-1"></i>Post sale journal</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="admin-card mb-3">
            <h6 class="card-title">Payments</h6>
            @forelse ($invoice->payments as $payment)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span>
                        <span class="badge text-bg-light border">{{ $payment->payment_method }}</span>
                        {{ number_format((float) $payment->amount, 2) }}
                        <span class="small text-muted">{{ $payment->paid_at?->format('Y-m-d H:i') }}</span>
                    </span>
                    <span class="d-flex align-items-center gap-1">
                        @if ($payment->journal?->status === 'posted')
                            <span class="badge text-bg-success">Posted</span>
                            <form method="POST" action="{{ route('finance.payments.reverse', $payment) }}" class="d-inline" data-ajax-submit="1" data-confirm="Reverse this payment? The receipt journal will be reversed.">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Reverse"><i class="bi bi-arrow-counterclockwise"></i></button>
                            </form>
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-muted mb-0">No payments yet.</p>
            @endforelse
        </div>

        @if ($invoice->installments->isNotEmpty())
            <div class="admin-card">
                <h6 class="card-title">Installments</h6>
                @foreach ($invoice->installments as $installment)
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span>#{{ $installment->installment_no }} — {{ number_format((float) $installment->amount, 2) }}</span>
                        <span>
                            <span class="badge text-bg-{{ $installment->status === 'paid' ? 'success' : ($installment->status === 'overdue' ? 'danger' : 'secondary') }}">{{ $installment->status }}</span>
                            <span class="small text-muted ms-1">due {{ $installment->due_date }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@if (optional(auth('institute_user')->user())->hasPermission('documents.view'))
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="admin-card">
                @include('documents._panel', ['entityType' => 'invoice', 'entityId' => $invoice->id])
            </div>
        </div>
    </div>
@endif

@endsection