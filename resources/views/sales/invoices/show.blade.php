@extends('layouts.institute')
@section('title','Invoice '.$invoice->invoice_number)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-receipt me-2"></i>Invoice {{ $invoice->invoice_number }}
        <small class="text-muted">{{ $invoice->party?->name }}</small>
        <span class="badge bg-{{ $invoice->status==='paid'?'success':($invoice->status==='partial'?'warning':($invoice->status==='cancelled'?'secondary':'danger')) }} ms-2">{{ ucfirst($invoice->status) }}</span>
    </h4>
    <div class="d-flex gap-2">
        @if($invoice->due_amount > 0)
        <a href="{{ route('sales.payments.create') }}?invoice_id={{ $invoice->id }}" class="btn btn-sm btn-success rounded-pill"><i class="bi bi-cash me-1"></i>Receive Payment</a>
        @endif
        <a href="{{ route('sales.invoices.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
    </div>
</div>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>Invoice Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="row mb-2"><div class="col-5 text-muted">Invoice #</div><div class="col-7 fw-semibold">{{ $invoice->invoice_number }}</div></div>
                        <div class="row mb-2"><div class="col-5 text-muted">Customer</div><div class="col-7">{{ $invoice->party?->name ?? '—' }}</div></div>
                        <div class="row mb-2"><div class="col-5 text-muted">Sales Order</div><div class="col-7">{{ $invoice->salesOrder?->order_number ?? '—' }}</div></div>
                        <div class="row mb-2"><div class="col-5 text-muted">Due Date</div><div class="col-7">{{ $invoice->due_date ?? '—' }}</div></div>
                    </div>
                    <div class="col-md-6">
                        <div class="row mb-2"><div class="col-5 text-muted">Total</div><div class="col-7">{{ number_format((float)$invoice->total_amount,2) }}</div></div>
                        <div class="row mb-2"><div class="col-5 text-muted">Payable</div><div class="col-7">{{ number_format((float)$invoice->payable_amount,2) }}</div></div>
                        <div class="row mb-2"><div class="col-5 text-muted">Paid</div><div class="col-7 text-success">{{ number_format((float)$invoice->paid_amount,2) }}</div></div>
                        <div class="row mb-2"><div class="col-5 text-muted">Due</div><div class="col-7 fw-bold text-danger">{{ number_format((float)$invoice->due_amount,2) }}</div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-list-check me-1"></i>Line Items</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>#</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Tax</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    @forelse($invoice->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">{{ number_format((float)($item->unit_price ?? 0),2) }}</td>
                            <td class="text-end">{{ number_format((float)($item->tax_amount ?? 0),2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float)$item->amount,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No items.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="5" class="text-end">Total</th>
                            <th class="text-end">{{ number_format((float)$invoice->total_amount,2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @if(!empty($invoice->notes) || !empty($invoice->note))
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-sticky me-1"></i>Notes</div>
            <div class="card-body">{{ $invoice->notes ?? $invoice->note }}</div>
        </div>
        @endif
    </div>
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-cash-stack me-1"></i>Payment History</div>
            <div class="card-body">
                @forelse($invoice->payments as $p)
                    <div class="mb-2 pb-2 border-bottom d-flex justify-content-between">
                        <div>
                            <strong>{{ number_format((float)$p->amount,2) }}</strong>
                            <small class="text-muted d-block">{{ $p->paymentMethod?->name ?? $p->payment_method }}</small>
                        </div>
                        <small class="text-muted text-end"><x-tdate :value="$p->paid_at" fallback="Y-m-d" /></small>
                    </div>
                @empty
                    <p class="text-muted mb-0">No payments yet.</p>
                @endforelse
            </div>
        </div>
        @if($invoice->due_amount > 0)
        <a href="{{ route('sales.payments.create') }}?invoice_id={{ $invoice->id }}" class="btn btn-success w-100 mb-2"><i class="bi bi-cash me-1"></i>Receive Payment</a>
        @endif
        <a href="{{ route('sales.invoices.index') }}" class="btn btn-outline-secondary w-100">Back to Invoices</a>
    </div>
</div>
@endsection
