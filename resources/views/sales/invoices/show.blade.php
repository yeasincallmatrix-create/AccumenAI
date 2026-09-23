@extends('layouts.institute')
@section('title','Invoice '.$invoice->invoice_number)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Invoice {{ $invoice->invoice_number }} <small class="text-muted">{{ $invoice->party?->name }}</small></h4>
    <a href="{{ route('sales.invoices.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header">Details</div>
            <div class="card-body">
                <div class="row mb-2"><div class="col-4 text-muted">Status</div><div class="col-8"><span class="badge bg-{{ $invoice->status==='paid'?'success':'warning' }}">{{ $invoice->status }}</span></div></div>
                <div class="row mb-2"><div class="col-4 text-muted">Total</div><div class="col-8">{{ number_format((float)$invoice->total_amount,2) }}</div></div>
                <div class="row mb-2"><div class="col-4 text-muted">Payable</div><div class="col-8">{{ number_format((float)$invoice->payable_amount,2) }}</div></div>
                <div class="row mb-2"><div class="col-4 text-muted">Paid</div><div class="col-8">{{ number_format((float)$invoice->paid_amount,2) }}</div></div>
                <div class="row mb-2"><div class="col-4 text-muted">Due</div><div class="col-8 fw-bold">{{ number_format((float)$invoice->due_amount,2) }}</div></div>
                <div class="row mb-2"><div class="col-4 text-muted">Due Date</div><div class="col-8">{{ $invoice->due_date }}</div></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Line Items</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Tax</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    @forelse($invoice->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">{{ number_format((float)($item->unit_price ?? 0),2) }}</td>
                            <td class="text-end">{{ number_format((float)($item->tax_amount ?? 0),2) }}</td>
                            <td class="text-end">{{ number_format((float)$item->amount,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No items.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">Payments</div>
            <div class="card-body">
                @forelse($invoice->payments as $p)
                    <div class="mb-2 pb-2 border-bottom">
                        <strong>{{ number_format((float)$p->amount,2) }}</strong>
                        <small class="text-muted d-block">{{ $p->paid_at }} • {{ $p->payment_method }}</small>
                    </div>
                @empty
                    <p class="text-muted mb-0">No payments yet.</p>
                @endforelse
            </div>
        </div>
        @if($invoice->due_amount > 0)
        <a href="{{ route('sales.payments.create') }}?invoice_id={{ $invoice->id }}" class="btn btn-success w-100">Receive Payment</a>
        @endif
    </div>
</div>
@endsection
