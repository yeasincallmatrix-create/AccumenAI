@extends('layouts.institute')
@section('title', 'New Purchase Return — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">New Purchase Return</h4>
    <a href="{{ route('purchase.returns.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('purchase.returns.store') }}">
@csrf
<div class="card mb-4"><div class="card-body">
<div class="row g-3">
    <div class="col-md-4"><label class="form-label">Purchase Order</label><select name="purchase_order_id" class="form-select"><option value="">Select PO</option>@foreach($purchaseOrders as $po)<option value="{{ $po->id }}">{{ $po->order_number }} — {{ $po->supplier?->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Goods Receipt *</label><select name="goods_receipt_id" class="form-select" required><option value="">Select GRN</option>@foreach($goodsReceipts as $gr)<option value="{{ $gr->id }}">{{ $gr->receipt_number }} — {{ $gr->supplier?->name }} (PO: {{ $gr->purchaseOrder?->order_number }})</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Supplier *</label><select name="supplier_id" class="form-select" required><option value="">Select supplier</option>@php $suppliers = \App\Models\Party::withoutGlobalScopes()->where('institute_id',$institute->id)->whereIn('type',['supplier','both'])->where('is_active',true)->orderBy('name')->get(); @endphp @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Return Date *</label><input type="date" name="return_date" value="{{ old('return_date', date('Y-m-d')) }}" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-select"><option value="">Auto</option>@php $whs = \App\Models\InventoryWarehouse::withoutGlobalScopes()->where('institute_id',$institute->id)->where('is_active',true)->get(); @endphp @foreach($whs as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Reason</label><input type="text" name="reason" value="{{ old('reason') }}" class="form-control" placeholder="Damaged goods, wrong item, etc."></div>
    <div class="col-md-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
</div>
</div></div>
<div class="card mb-4">
<div class="card-header d-flex justify-content-between align-items-center"><span>Return Lines</span><button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="addLine"><i class="bi bi-plus-lg"></i> Add Line</button></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table mb-0" id="linesTable">
<thead><tr><th style="min-width:220px">Description *</th><th style="width:90px">Qty *</th><th style="width:70px">Unit</th><th style="width:120px">Unit Price *</th><th style="width:110px">Discount</th><th style="width:120px">Tax</th><th style="width:40px"></th></tr></thead>
<tbody>
@php $oldLines = old('lines', [['description'=>'','quantity'=>1,'unit_price'=>0]]); @endphp
@foreach($oldLines as $idx => $line)
<tr>
<td><input type="text" name="lines[{{ $idx }}][description]" value="{{ $line['description'] ?? '' }}" class="form-control form-control-sm" required><input type="hidden" name="lines[{{ $idx }}][purchase_order_line_id]" value="{{ $line['purchase_order_line_id'] ?? '' }}"><input type="hidden" name="lines[{{ $idx }}][goods_receipt_item_id]" value="{{ $line['goods_receipt_item_id'] ?? '' }}"><input type="hidden" name="lines[{{ $idx }}][inventory_item_id]" value="{{ $line['inventory_item_id'] ?? '' }}"></td>
<td><input type="number" step="0.0001" min="0.0001" name="lines[{{ $idx }}][quantity]" value="{{ $line['quantity'] ?? 1 }}" class="form-control form-control-sm" required></td>
<td><input type="text" name="lines[{{ $idx }}][unit]" value="{{ $line['unit'] ?? '' }}" class="form-control form-control-sm" placeholder="pcs"></td>
<td><input type="number" step="0.0001" min="0" name="lines[{{ $idx }}][unit_price]" value="{{ $line['unit_price'] ?? 0 }}" class="form-control form-control-sm" required></td>
<td><div class="input-group input-group-sm"><input type="number" step="0.0001" min="0" name="lines[{{ $idx }}][discount_amount]" value="{{ $line['discount_amount'] ?? 0 }}" class="form-control"><select name="lines[{{ $idx }}][discount_type]" class="form-select"><option value="fixed" {{ ($line['discount_type']??'fixed')==='fixed'?'selected':'' }}>Amt</option><option value="percent" {{ ($line['discount_type']??'')==='percent'?'selected':'' }}>%</option></select></div></td>
<td><select name="lines[{{ $idx }}][tax_group_id]" class="form-select form-select-sm"><option value="">No Tax</option>@php $tgs = \App\Models\TaxGroup::withoutGlobalScopes()->where('institute_id',$institute->id)->where('is_active',true)->get(); @endphp @foreach($tgs as $tg)<option value="{{ $tg->id }}">{{ $tg->name }} ({{ $tg->rate }}%)</option>@endforeach</select></td>
<td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button></td>
</tr>
@endforeach
</tbody>
</table></div></div>
</div>
<div class="d-flex gap-2"><button type="submit" class="btn btn-primary rounded-pill">Create Return</button><a href="{{ route('purchase.returns.index') }}" class="btn btn-outline-secondary rounded-pill">Cancel</a></div>
</form>
@endsection
@push('scripts')
<script>
document.getElementById('addLine')?.addEventListener('click', function(){
    const tbody=document.querySelector('#linesTable tbody'); const idx=tbody.children.length; const row=tbody.children[0].cloneNode(true);
    row.querySelectorAll('input, select').forEach(el=>{ el.name=el.name.replace(/lines\[\d+\]/,`lines[${idx}]`); if(el.tagName==='INPUT') el.value=el.type==='number'?0:''; if(el.tagName==='SELECT') el.selectedIndex=0; });
    tbody.appendChild(row);
});
document.addEventListener('click', function(e){ if(e.target.closest('.remove-line')){ const tr=e.target.closest('tr'); if(document.querySelectorAll('#linesTable tbody tr').length>1) tr.remove(); }});
</script>
@endpush
