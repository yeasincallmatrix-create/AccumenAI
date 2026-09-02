@extends('layouts.institute')
@section('title','New Sales Return — AccumenAI')
@section('content')
<h4 class="mb-4"><i class="bi bi-arrow-return-left me-2"></i>New Sales Return</h4>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('sales.returns.store') }}">@csrf
<div class="card mb-4"><div class="card-body row g-3">
    <div class="col-md-4"><label class="form-label">Invoice *</label><select name="invoice_id" id="invoice_id" class="form-select" required><option value="">Select posted invoice</option>@foreach($invoices as $inv)<option value="{{ $inv->id }}">{{ $inv->invoice_number }} — {{ $inv->party?->name }} — {{ number_format($inv->payable_amount,2) }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Warehouse (return location)</label><select name="warehouse_id" class="form-select"><option value="">Auto</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }} ({{ $w->code }})</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Return Date *</label><input type="date" name="return_date" value="{{ old('return_date', now()->toDateString()) }}" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Reason *</label><input type="text" name="reason" value="{{ old('reason') }}" class="form-control" placeholder="Damaged / Wrong item..." required></div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
</div></div>
<div class="card mb-4"><div class="card-header d-flex justify-content-between"><span>Return Items</span><button type="button" id="loadLines" class="btn btn-sm btn-outline-primary">Load Invoice Lines</button></div><div class="card-body"><div id="linesArea" class="text-muted">Select an invoice and click Load.</div></div></div>
<div class="text-end"><button class="btn btn-primary rounded-pill" type="submit">Create Return (Draft)</button></div>
</form>
@push('scripts')
<script>
document.getElementById('loadLines').addEventListener('click', async ()=>{
    const raw=document.getElementById('invoice_id').value;
    const id=raw ? String(raw).trim() : '';
    if(!id || id.indexOf('[object')!==-1 || !/^\d+$/.test(id)) return alert('Select invoice');
    const r=await fetch(`{{ url('sales/invoices') }}/${encodeURIComponent(id)}/return-lines`, {headers:{'Accept':'application/json'}});
    const j=await r.json();
    const area=document.getElementById('linesArea');
    let html='<table class="table"><thead><tr><th>Description</th><th>Invoiced</th><th>Already Returned</th><th>Remaining</th><th>Return Qty</th></tr></thead><tbody>';
    for(const [iid,info] of Object.entries(j.remaining)){
        html+=`<tr><td>${info.invoice_item.description}</td><td>${info.invoiced}</td><td>${info.returned}</td><td>${info.remaining}</td><td><input type="number" step="0.0001" name="lines[${Object.keys(j.remaining).indexOf(iid)}][quantity]" class="form-control form-control-sm" max="${info.remaining}"><input type="hidden" name="lines[${Object.keys(j.remaining).indexOf(iid)}][invoice_item_id]" value="${iid}"></td></tr>`;
    }
    html+='</tbody></table>';
    area.innerHTML=html;
});
</script>
@endpush
@endsection
