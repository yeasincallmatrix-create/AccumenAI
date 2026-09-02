@extends('layouts.institute')
@section('title', 'Purchase Invoice ' . $invoice->invoice_number . ' — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $invoice->invoice_number }} <span class="badge bg-{{ ['draft'=>'secondary','posted'=>'success','cancelled'=>'dark','reversed'=>'warning'][$invoice->status]??'secondary' }}">{{ ucfirst($invoice->status) }}</span></h4>
    <div class="d-flex gap-2"><a href="{{ route('purchase.invoices.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a><a href="{{ route('purchase.invoices.print',$invoice) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-printer"></i> Print</a></div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card mb-4"><div class="card-body"><div class="row">
<div class="col-md-6"><h6>Supplier</h6><p class="mb-1 fw-semibold">{{ $invoice->supplier?->name }}</p><p class="mb-1 text-muted small">{{ $invoice->supplier?->phone }} {{ $invoice->supplier?->email ? '• '.$invoice->supplier?->email : '' }}</p><p class="mb-1"><strong>PO:</strong> @if($invoice->purchaseOrder)<a href="{{ route('purchase.orders.show',$invoice->purchaseOrder) }}">{{ $invoice->purchaseOrder->order_number }}</a>@else — @endif</p><p class="mb-1"><strong>GRN:</strong> @if($invoice->goodsReceipt)<a href="{{ route('purchase.receipts.show',$invoice->goodsReceipt) }}">{{ $invoice->goodsReceipt->receipt_number }}</a>@else — @endif</p></div>
<div class="col-md-6 text-md-end"><p class="mb-1"><strong>Invoice Date:</strong> {{ $invoice->invoice_date->format('Y-m-d') }}</p><p class="mb-1"><strong>Due Date:</strong> {{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</p><p class="mb-1"><strong>Currency:</strong> {{ $invoice->currency?->code }}</p><p class="mb-1"><strong>Branch:</strong> {{ $invoice->branch?->name ?? 'Institute-wide' }}</p><p class="mb-1"><strong>Journal:</strong> @if($invoice->journal) {{ $invoice->journal->journal_no }} @else — @endif</p></div>
</div>
@if($invoice->notes)<div class="mt-3"><strong>Notes:</strong><p class="text-muted">{{ $invoice->notes }}</p></div>@endif
</div></div>
<div class="card mb-4"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>#</th><th>Description</th><th class="text-end">Qty</th><th>Unit</th><th class="text-end">Unit Price</th><th class="text-end">Discount</th><th class="text-end">Tax</th><th class="text-end">Total</th></tr></thead>
<tbody>
@foreach($invoice->items as $idx=>$line)
<tr><td>{{ $idx+1 }}</td><td>{{ $line->description }} @if($line->inventoryItem)<small class="text-muted">({{ $line->inventoryItem->sku }})</small>@endif</td><td class="text-end">{{ number_format($line->quantity,2) }}</td><td>{{ $line->unit ?? '—' }}</td><td class="text-end">{{ number_format($line->unit_price,2) }}</td><td class="text-end">{{ number_format($line->discount_amount,2) }}</td><td class="text-end">{{ number_format($line->tax_amount,2) }} @if($line->tax_rate)<small class="text-muted">({{ $line->tax_rate }}%)</small>@endif</td><td class="text-end fw-semibold">{{ number_format($line->line_total,2) }}</td></tr>
@endforeach
</tbody>
<tfoot>
<tr><th colspan="7" class="text-end">Subtotal</th><th class="text-end">{{ number_format($invoice->subtotal,2) }}</th></tr>
<tr><th colspan="7" class="text-end">Discount</th><th class="text-end text-danger">-{{ number_format($invoice->discount_amount,2) }}</th></tr>
<tr><th colspan="7" class="text-end">Tax</th><th class="text-end">{{ number_format($invoice->tax_amount,2) }}</th></tr>
<tr class="table-light"><th colspan="7" class="text-end">Grand Total</th><th class="text-end">{{ number_format($invoice->grand_total,2) }} {{ $invoice->currency?->code }}</th></tr>
<tr><th colspan="7" class="text-end">Paid</th><th class="text-end text-success">{{ number_format($invoice->paid_amount,2) }}</th></tr>
<tr><th colspan="7" class="text-end">Due</th><th class="text-end text-danger">{{ number_format($invoice->due_amount,2) }}</th></tr>
</tfoot>
</table></div></div>
<div class="card mb-4"><div class="card-body d-flex flex-wrap gap-2">
@if($invoice->isDraft())
<form method="POST" action="{{ route('purchase.invoices.post',$invoice) }}">@csrf<button class="btn btn-success rounded-pill">Post Invoice (AP)</button></form>
<form method="POST" action="{{ route('purchase.invoices.cancel',$invoice) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
@elseif($invoice->isPosted())
<form method="POST" action="{{ route('purchase.invoices.reverse',$invoice) }}">@csrf<input type="hidden" name="reason" value="Reversal"><button class="btn btn-warning rounded-pill">Reverse Invoice</button></form>
@endif
</div></div>
<div class="card mb-4">
<div class="card-header d-flex justify-content-between align-items-center"><span>Supplier Payments</span><span class="badge bg-info">{{ number_format($invoice->paid_amount,2) }} paid / {{ number_format($invoice->due_amount,2) }} due</span></div>
<div class="card-body">
@if($invoice->isPosted() && $invoice->due_amount > 0.00005)
<form method="POST" action="{{ route('purchase.invoices.pay',$invoice) }}" class="row g-3 align-items-end mb-4">
@csrf
<div class="col-md-3"><label class="form-label">Amount *</label><input type="number" step="0.0001" min="0.0001" max="{{ $invoice->due_amount }}" name="amount" value="{{ old('amount', $invoice->due_amount) }}" class="form-control" required></div>
<div class="col-md-3"><label class="form-label">Method</label><select name="payment_method" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option><option value="bkash">bKash</option><option value="nagad">Nagad</option><option value="card">Card</option><option value="other">Other</option></select></div>
<div class="col-md-3"><label class="form-label">Transaction ID</label><input type="text" name="transaction_id" class="form-control" placeholder="Optional"></div>
<div class="col-md-3"><button class="btn btn-primary rounded-pill" type="submit">Pay Supplier</button></div>
</form>
@endif
<div class="table-responsive"><table class="table mb-0">
<thead><tr><th>#</th><th>Date</th><th class="text-end">Amount</th><th>Method</th><th>Journal</th><th></th></tr></thead>
<tbody>
@forelse($invoice->payments as $pay)
<tr><td>{{ $loop->iteration }}</td><td>{{ $pay->paid_at->format('Y-m-d H:i') }}</td><td class="text-end">{{ number_format($pay->amount,2) }}</td><td>{{ ucfirst($pay->payment_method) }}</td><td>{{ $pay->journal?->journal_no ?? '—' }}</td><td class="text-end"><form method="POST" action="{{ route('purchase.payments.reverse',$pay) }}">@csrf<button class="btn btn-sm btn-outline-warning rounded-pill" onclick="return confirm('Reverse this payment?')">Reverse</button></form></td></tr>
@empty<tr><td colspan="6" class="text-center text-muted py-3">No payments yet.</td></tr>@endforelse
</tbody>
</table></div>
</div>
</div>
@endsection
