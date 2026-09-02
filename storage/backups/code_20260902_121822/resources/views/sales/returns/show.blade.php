@extends('layouts.institute')
@section('title','Return '.$ret->return_number.' — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Return {{ $ret->return_number }} <small class="text-muted">{{ $ret->credit_note_number }}</small></h4>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('sales.returns.index') }}">Back</a>
        <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('sales.returns.credit-note',$ret) }}">Credit Note</a>
        <a class="btn btn-sm btn-outline-dark rounded-pill" href="{{ route('sales.returns.credit-note',$ret) }}?print=1" target="_blank">Print</a>
    </div>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="card mb-4"><div class="card-body row g-3">
    <div class="col-md-3"><strong>Invoice:</strong> {{ $ret->invoice?->invoice_number }}<br><strong>Customer:</strong> {{ $ret->customer?->name }}<br><strong>Date:</strong> {{ $ret->return_date->format('Y-m-d') }}</div>
    <div class="col-md-3"><strong>Status:</strong> <span class="badge bg-{{ ['draft'=>'secondary','approved'=>'info','posted'=>'success','cancelled'=>'dark','reversed'=>'warning'][$ret->status]??'secondary' }}">{{ ucfirst($ret->status) }}</span><br><strong>Refund:</strong> {{ ucfirst($ret->refund_status) }} ({{ number_format($ret->refunded_amount,2) }}/{{ number_format($ret->refundable_amount,2) }})<br><strong>Reason:</strong> {{ $ret->reason }}</div>
    <div class="col-md-3"><strong>Warehouse:</strong> {{ $ret->warehouse?->name ?? '—' }}<br><strong>Journal:</strong> {{ $ret->journal?->journal_no ?? '—' }}<br><strong>Inv. Journal:</strong> {{ $ret->inventoryJournal?->journal_no ?? '—' }}</div>
    <div class="col-md-3 text-end">
        @if($ret->status==='draft')<form method="POST" action="{{ route('sales.returns.approve',$ret) }}" class="d-inline">@csrf<button class="btn btn-sm btn-info rounded-pill">Approve</button></form>@endif
        @if(in_array($ret->status,['draft','approved']))<form method="POST" action="{{ route('sales.returns.post',$ret) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success rounded-pill">Post (Credit Note)</button></form> <form method="POST" action="{{ route('sales.returns.cancel',$ret) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-danger rounded-pill">Cancel</button></form>@endif
        @if($ret->status==='posted')<form method="POST" action="{{ route('sales.returns.reverse',$ret) }}" class="d-inline">@csrf<button class="btn btn-sm btn-warning rounded-pill" onclick="return confirm('Reverse this posted return?')">Reverse</button></form>@endif
    </div>
</div></div>
<div class="card mb-4"><div class="card-header">Returned Items</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit</th><th class="text-end">Discount</th><th class="text-end">Tax</th><th class="text-end">Total</th></tr></thead><tbody>@foreach($ret->items as $it)<tr><td>{{ $it->description }}</td><td class="text-end">{{ $it->quantity }}</td><td class="text-end">{{ number_format($it->unit_price,2) }}</td><td class="text-end">{{ number_format($it->discount_amount,2) }}</td><td class="text-end">{{ number_format($it->tax_amount,2) }} ({{ $it->tax_rate }}%)</td><td class="text-end fw-semibold">{{ number_format($it->line_total,2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="5" class="text-end">Grand Total</th><th class="text-end">{{ number_format($ret->grand_total,2) }}</th></tr></tfoot></table></div></div>
@if($ret->status==='posted')
<div class="card mb-4"><div class="card-header">Refund / Credit Application</div><div class="card-body">
    <form method="POST" action="{{ route('sales.returns.refund',$ret) }}" class="row g-3">@csrf
        <div class="col-md-2"><label class="form-label">Method</label><select name="method" class="form-select" required><option value="credit">Credit balance</option><option value="cash">Cash</option><option value="bank">Bank</option><option value="other">Other</option></select></div>
        <div class="col-md-2"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" max="{{ $ret->refundable_amount - $ret->refunded_amount }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Reference</label><input type="text" name="reference" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Date</label><input type="date" name="refund_date" value="{{ now()->toDateString() }}" class="form-control" required></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-sm btn-primary rounded-pill">Record Refund</button></div>
    </form>
    @if($ret->refunds->count())<hr><table class="table table-sm"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Ref</th></tr></thead><tbody>@foreach($ret->refunds as $rf)<tr><td>{{ $rf->refund_date->format('Y-m-d') }}</td><td>{{ ucfirst($rf->method) }}</td><td>{{ number_format($rf->amount,2) }}</td><td>{{ $rf->reference ?? '—' }}</td></tr>@endforeach</tbody></table>@endif
</div></div>
@endif
@endsection
