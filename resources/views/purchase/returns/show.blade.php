@extends('layouts.institute')
@section('title', 'Return ' . $return->return_number . ' — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $return->return_number }} <span class="badge bg-{{ ['draft'=>'secondary','submitted'=>'info','approved'=>'warning','posted'=>'success','cancelled'=>'dark','reversed'=>'dark'][$return->status]??'secondary' }}">{{ ucfirst($return->status) }}</span> <small class="text-muted">CN: {{ $return->credit_note_number ?? '—' }}</small></h4>
    <div class="d-flex gap-2"><a href="{{ route('purchase.returns.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a><a href="{{ route('purchase.returns.print',$return) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-printer"></i> Print</a><a href="{{ route('purchase.returns.creditNote',$return) }}" class="btn btn-sm btn-outline-info rounded-pill">Credit Note</a></div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card mb-4"><div class="card-body"><div class="row">
<div class="col-md-6"><h6>Supplier</h6><p class="mb-1 fw-semibold">{{ $return->supplier?->name }}</p><p class="mb-1 text-muted small">{{ $return->supplier?->phone }}</p><p class="mb-1"><strong>PO:</strong> @if($return->purchaseOrder)<a href="{{ route('purchase.orders.show',$return->purchaseOrder) }}">{{ $return->purchaseOrder->order_number }}</a>@else — @endif</p><p class="mb-1"><strong>GRN:</strong> @if($return->goodsReceipt) {{ $return->goodsReceipt->receipt_number }} @else — @endif</p></div>
<div class="col-md-6 text-md-end"><p class="mb-1"><strong>Return Date:</strong> {{ $return->return_date->format('Y-m-d') }}</p><p class="mb-1"><strong>Warehouse:</strong> {{ $return->warehouse?->name ?? '—' }}</p><p class="mb-1"><strong>Branch:</strong> {{ $return->branch?->name ?? 'Institute-wide' }}</p><p class="mb-1"><strong>Reason:</strong> {{ $return->reason ?? '—' }}</p></div>
</div></div></div>
<div class="card mb-4"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>#</th><th>Description</th><th class="text-end">Qty</th><th>Unit</th><th class="text-end">Unit Price</th><th class="text-end">Discount</th><th class="text-end">Tax</th><th class="text-end">Total</th></tr></thead>
<tbody>@foreach($return->items as $idx=>$line)<tr><td>{{ $idx+1 }}</td><td>{{ $line->description }} @if($line->inventoryItem)<small class="text-muted">({{ $line->inventoryItem->sku }})</small>@endif</td><td class="text-end">{{ number_format($line->quantity,2) }}</td><td>{{ $line->unit ?? '—' }}</td><td class="text-end">{{ number_format($line->unit_price,2) }}</td><td class="text-end">{{ number_format($line->discount_amount,2) }}</td><td class="text-end">{{ number_format($line->tax_amount,2) }}</td><td class="text-end fw-semibold">{{ number_format($line->line_total,2) }}</td></tr>@endforeach</tbody>
<tfoot><tr><th colspan="7" class="text-end">Subtotal</th><th class="text-end">{{ number_format($return->subtotal,2) }}</th></tr><tr><th colspan="7" class="text-end">Discount</th><th class="text-end text-danger">-{{ number_format($return->discount_amount,2) }}</th></tr><tr><th colspan="7" class="text-end">Tax</th><th class="text-end">{{ number_format($return->tax_amount,2) }}</th></tr><tr class="table-light"><th colspan="7" class="text-end">Grand Total</th><th class="text-end">{{ number_format($return->grand_total,2) }}</th></tr></tfoot>
</table></div></div>
<div class="card mb-4"><div class="card-body d-flex flex-wrap gap-2">
@if($return->status==='draft')<form method="POST" action="{{ route('purchase.returns.submit',$return) }}">@csrf<button class="btn btn-info rounded-pill">Submit</button></form><form method="POST" action="{{ route('purchase.returns.cancel',$return) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
@elseif($return->status==='submitted')<form method="POST" action="{{ route('purchase.returns.approve',$return) }}">@csrf<button class="btn btn-warning rounded-pill">Approve</button></form><form method="POST" action="{{ route('purchase.returns.cancel',$return) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
@elseif($return->status==='approved')<form method="POST" action="{{ route('purchase.returns.post',$return) }}">@csrf<button class="btn btn-success rounded-pill">Post (Inventory + Credit Note)</button></form><form method="POST" action="{{ route('purchase.returns.cancel',$return) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
@elseif($return->status==='posted')<form method="POST" action="{{ route('purchase.returns.reverse',$return) }}">@csrf<input type="hidden" name="reason" value="Reversal"><button class="btn btn-warning rounded-pill">Reverse</button></form>
@endif
</div></div>
<div class="card mb-4">
<div class="card-header d-flex justify-content-between align-items-center"><span>Supplier Credit</span>@if($credit)<span class="badge bg-{{ $credit->status==='available'?'success':'warning' }}">{{ ucfirst($credit->status) }} — {{ number_format($credit->remaining_amount,2) }} remaining</span>@endif</div>
<div class="card-body">
@if($credit)
<p><strong>Credit Note:</strong> {{ $return->credit_note_number }} — <strong>Amount:</strong> {{ number_format($credit->credit_amount,2) }} | <strong>Used:</strong> {{ number_format($credit->used_amount,2) }} | <strong>Remaining:</strong> {{ number_format($credit->remaining_amount,2) }}</p>
<form method="POST" action="{{ route('purchase.returns.refund',$return) }}" class="row g-3 align-items-end mb-3">
@csrf
<div class="col-md-3"><label class="form-label">Refund Amount</label><input type="number" step="0.0001" min="0.0001" max="{{ $credit->remaining_amount }}" name="amount" class="form-control" required></div>
<div class="col-md-3"><label class="form-label">Method</label><select name="refund_method" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option></select></div>
<div class="col-md-3"><button class="btn btn-primary rounded-pill" type="submit">Refund</button></div>
</form>
<h6>Refunds</h6>
<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th class="text-end">Amount</th><th>Method</th><th>Journal</th></tr></thead><tbody>
@forelse($refunds as $r)<tr><td>{{ $r->created_at->format('Y-m-d') }}</td><td class="text-end">{{ number_format($r->amount,2) }}</td><td>{{ ucfirst($r->refund_method) }}</td><td>{{ $r->journal?->journal_no ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">No refunds yet.</td></tr>@endforelse
</tbody></table></div>
<form method="POST" action="{{ route('purchase.credit.adjust') }}" class="row g-3 align-items-end mt-3">
@csrf
<input type="hidden" name="supplier_id" value="{{ $return->supplier_id }}">
<div class="col-md-4"><label class="form-label">Adjust Against Invoice</label><select name="purchase_invoice_id" class="form-select" required><option value="">Select invoice</option>@php $invs = \App\Models\PurchaseInvoice::withoutGlobalScopes()->where('institute_id',$return->institute_id)->where('supplier_id',$return->supplier_id)->where('status','posted')->where('due_amount','>',0)->get(); @endphp @foreach($invs as $inv)<option value="{{ $inv->id }}">{{ $inv->invoice_number }} — Due {{ number_format($inv->due_amount,2) }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Amount</label><input type="number" step="0.0001" name="amount" class="form-control" required></div>
<div class="col-md-3"><button class="btn btn-outline-primary rounded-pill" type="submit">Adjust Credit</button></div>
</form>
@else
<p class="text-muted">No credit yet — post the return to generate credit note.</p>
@endif
</div>
</div>
@endsection
