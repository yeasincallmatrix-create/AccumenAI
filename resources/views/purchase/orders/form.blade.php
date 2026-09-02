@extends('layouts.institute')

@section('title', ($isEdit ? 'Edit' : 'New') . ' Purchase Order — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $isEdit ? 'Edit Purchase Order' : 'New Purchase Order' }} @if($isEdit) <small class="text-muted">{{ $order->order_number }}</small> @endif</h4>
    <a href="{{ route('purchase.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $isEdit ? route('purchase.orders.update', $order) : route('purchase.orders.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Supplier *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select supplier</option>
                        @php
                            $suppliers = \App\Models\Party::withoutGlobalScopes()->where('institute_id', $institute->id)->whereIn('type', ['supplier','both'])->where('is_active', true)->orderBy('name')->get();
                            $selectedSupplier = old('supplier_id', $order?->supplier_id ?? null);
                        @endphp
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" {{ (string)$selectedSupplier === (string)$s->id ? 'selected' : '' }}>{{ $s->name }} @if($s->phone) ({{ $s->phone }}) @endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">Select warehouse</option>
                        @php
                            $warehouses = \App\Models\InventoryWarehouse::withoutGlobalScopes()->where('institute_id', $institute->id)->orderBy('name')->get();
                            $selectedWh = old('warehouse_id', $order?->warehouse_id ?? null);
                        @endphp
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (string)$selectedWh === (string)$wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Order Date *</label>
                    <input type="date" name="order_date" value="{{ old('order_date', $order?->order_date?->format('Y-m-d') ?? date('Y-m-d')) }}" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Expected Delivery</label>
                    <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', $order?->expected_delivery_date?->format('Y-m-d') ?? '') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Currency</label>
                    <select name="currency_id" class="form-select">
                        <option value="">Default</option>
                        @foreach ($currencies as $cur)
                            <option value="{{ $cur->id }}" {{ (string)old('currency_id', $order?->currency_id ?? '') === (string)$cur->id ? 'selected' : '' }}>{{ $cur->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" value="{{ old('reference_number', $order?->reference_number ?? '') }}" class="form-control" placeholder="REF-001">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Discount Type</label>
                    <select name="discount_type" class="form-select">
                        <option value="fixed" {{ old('discount_type', $order?->discount_type ?? 'fixed') === 'fixed' ? 'selected' : '' }}>Fixed</option>
                        <option value="percent" {{ old('discount_type', $order?->discount_type ?? '') === 'percent' ? 'selected' : '' }}>Percent</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Header Discount</label>
                    <input type="number" step="0.0001" min="0" name="discount_amount" value="{{ old('discount_amount', $order?->discount_amount ?? 0) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $order?->notes ?? '') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Terms & Conditions</label>
                    <textarea name="terms_conditions" class="form-control" rows="2">{{ old('terms_conditions', $order?->terms_conditions ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Lines</span>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="addLine"><i class="bi bi-plus-lg"></i> Add Line</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0" id="linesTable">
                    <thead>
                        <tr>
                            <th style="min-width:220px">Product / Description *</th>
                            <th style="width:100px">Qty *</th>
                            <th style="width:80px">Unit</th>
                            <th style="width:120px">Unit Price *</th>
                            <th style="width:140px">Discount</th>
                            <th style="width:140px">Tax</th>
                            <th style="width:110px" class="text-end">Line Total</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $oldLines = old('lines', $order?->lines?->toArray() ?? [['description'=>'','quantity'=>1,'unit_price'=>0,'discount_amount'=>0,'discount_type'=>'fixed']]);
                        @endphp
                        @foreach ($oldLines as $idx => $line)
                            <tr>
                                <td>
                                    <select name="lines[{{ $idx }}][inventory_item_id]" class="form-select form-select-sm mb-1">
                                        <option value="">— Manual —</option>
                                        @php $items = \App\Models\InventoryItem::withoutGlobalScopes()->where('institute_id', $institute->id)->where('is_active', true)->orderBy('name')->limit(100)->get(); @endphp
                                        @foreach ($items as $it)
                                            <option value="{{ $it->id }}" {{ ($line['inventory_item_id'] ?? null) == $it->id ? 'selected' : '' }}>{{ $it->name }} ({{ $it->sku }})</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="lines[{{ $idx }}][description]" value="{{ $line['description'] ?? '' }}" class="form-control form-control-sm" placeholder="Description">
                                </td>
                                <td><input type="number" step="0.0001" min="0.0001" name="lines[{{ $idx }}][quantity]" value="{{ $line['quantity'] ?? 1 }}" class="form-control form-control-sm qty-input" required></td>
                                <td><input type="text" name="lines[{{ $idx }}][unit]" value="{{ $line['unit'] ?? '' }}" class="form-control form-control-sm" placeholder="pcs"></td>
                                <td><input type="number" step="0.0001" min="0" name="lines[{{ $idx }}][unit_price]" value="{{ $line['unit_price'] ?? 0 }}" class="form-control form-control-sm price-input" required></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.0001" min="0" name="lines[{{ $idx }}][discount_amount]" value="{{ $line['discount_amount'] ?? 0 }}" class="form-control discount-input">
                                        <select name="lines[{{ $idx }}][discount_type]" class="form-select discount-type"><option value="fixed" {{ ($line['discount_type'] ?? 'fixed')==='fixed'?'selected':'' }}>Amt</option><option value="percent" {{ ($line['discount_type'] ?? '')==='percent'?'selected':'' }}>%</option></select>
                                    </div>
                                    <input type="hidden" name="lines[{{ $idx }}][discount_rate]" value="{{ $line['discount_rate'] ?? 0 }}">
                                </td>
                                <td>
                                    <select name="lines[{{ $idx }}][tax_group_id]" class="form-select form-select-sm tax-group">
                                        <option value="">No Tax</option>
                                        @php $tgs = \App\Models\TaxGroup::withoutGlobalScopes()->where('institute_id', $institute->id)->where('is_active', true)->get(); @endphp
                                        @foreach ($tgs as $tg)
                                            <option value="{{ $tg->id }}" data-rate="{{ $tg->rate }}" {{ ($line['tax_group_id'] ?? null)==$tg->id?'selected':'' }}>{{ $tg->name }} ({{ $tg->rate }}%)</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="lines[{{ $idx }}][tax_rate]" value="{{ $line['tax_rate'] ?? 0 }}" class="tax-rate">
                                </td>
                                <td class="text-end"><span class="line-total fw-semibold">0.00</span></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="6" class="text-end">Grand Total</th>
                            <th class="text-end"><span id="grandTotal">0.00</span></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary rounded-pill">{{ $isEdit ? 'Update' : 'Create' }} Purchase Order</button>
        <a href="{{ $isEdit ? route('purchase.orders.show', $order) : route('purchase.orders.index') }}" class="btn btn-outline-secondary rounded-pill">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('addLine')?.addEventListener('click', function(){
    const tbody = document.querySelector('#linesTable tbody');
    if(!tbody) return;
    const idx = tbody.children.length;
    const row = tbody.children[0].cloneNode(true);
    row.querySelectorAll('input, select').forEach(el=>{
        if(el.name) el.name = el.name.replace(/lines\[\d+\]/, `lines[${idx}]`);
        if(el.tagName==='INPUT'){
            if(el.type==='number') el.value = 0;
            else if(el.type==='hidden') el.value = 0;
            else el.value = '';
            if(el.classList.contains('qty-input')) el.value = 1;
        }
        if(el.tagName==='SELECT') el.selectedIndex = 0;
    });
    row.querySelector('.line-total').textContent = '0.00';
    tbody.appendChild(row);
    recalcTotals();
});
document.addEventListener('click', function(e){
    if(e.target.closest('.remove-line')){
        const tr = e.target.closest('tr');
        if(document.querySelectorAll('#linesTable tbody tr').length > 1) {
            tr.remove();
            recalcTotals();
        }
    }
});
function parseNum(v){ const n=parseFloat(v); return isNaN(n)?0:n; }
function recalcRow(tr){
    const qty = parseNum(tr.querySelector('.qty-input')?.value);
    const price = parseNum(tr.querySelector('.price-input')?.value);
    let discount = parseNum(tr.querySelector('.discount-input')?.value);
    const discountType = tr.querySelector('.discount-type')?.value;
    const subtotal = qty * price;
    let discountAmt = 0;
    if(discountType === 'percent') discountAmt = subtotal * discount / 100;
    else discountAmt = discount;
    if(discountAmt > subtotal) discountAmt = subtotal;
    const net = subtotal - discountAmt;
    const taxRate = parseNum(tr.querySelector('.tax-group')?.selectedOptions[0]?.dataset.rate || tr.querySelector('.tax-rate')?.value || 0);
    const tax = net * taxRate / 100;
    const total = net + tax;
    const el = tr.querySelector('.line-total');
    if(el) el.textContent = total.toFixed(2);
    const taxRateInput = tr.querySelector('.tax-rate');
    if(taxRateInput) taxRateInput.value = taxRate;
    return total;
}
function recalcTotals(){
    let grand = 0;
    document.querySelectorAll('#linesTable tbody tr').forEach(tr=>{
        grand += recalcRow(tr);
    });
    const gt = document.getElementById('grandTotal');
    if(gt) gt.textContent = grand.toFixed(2);
}
document.addEventListener('input', function(e){
    if(e.target.closest('#linesTable')) recalcTotals();
});
document.addEventListener('change', function(e){
    if(e.target.closest('#linesTable')) recalcTotals();
});
document.addEventListener('DOMContentLoaded', recalcTotals);
</script>
@endpush
