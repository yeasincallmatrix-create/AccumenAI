@extends('layouts.institute')

@section('title', ($isEdit ? 'Edit' : 'New') . ' Sales Order — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $isEdit ? 'Edit Order' : 'New Sales Order' }} @if($isEdit) <small class="text-muted">{{ $order->order_number }}</small> @endif</h4>
    <a href="{{ route('sales.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if (!$isEdit && !$order && isset($prefillQuotation) && $prefillQuotation)
    <div class="alert alert-info"><i class="bi bi-link-45deg"></i> Prefilled from accepted quotation <strong>{{ $prefillQuotation->quotation_number }}</strong> — customer and lines are copied. You can still adjust dates and addresses before creation.</div>
@endif

@if (!$isEdit)
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('sales.orders.create') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Convert Accepted Quotation (optional)</label>
                <select name="quotation_id" class="form-select">
                    <option value="">— Direct Order (no quotation) —</option>
                    @php
                        $qs = \App\Models\SalesQuotation::where('institute_id', $institute->id)
                            ->when(request()->user() && method_exists(request()->user(),'branch_id') && request()->user()->branch_id !== null, function($q) { $q->where(function($qq){ $qq->whereNull('branch_id')->orWhere('branch_id', request()->user()->branch_id); }); })
                            ->where('status', 'accepted')->whereNull('converted_to_order_id')->orderByDesc('id')->limit(50)->get();
                    @endphp
                    @foreach ($qs as $qq)
                        <option value="{{ $qq->id }}" {{ (string)request('quotation_id') === (string)$qq->id || (isset($prefillQuotation) && $prefillQuotation->id === $qq->id) ? 'selected' : '' }}>{{ $qq->quotation_number }} — {{ $qq->customer?->name }} — {{ number_format($qq->grand_total,2) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-outline-primary rounded-pill mt-4" type="submit">Load</button>
            </div>
        </form>
        @if (isset($prefillQuotation) && $prefillQuotation)
            <form method="POST" action="{{ route('sales.orders.store') }}" class="mt-3">
                @csrf
                <input type="hidden" name="quotation_id" value="{{ $prefillQuotation->id }}">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Order Date *</label>
                        <input type="date" name="order_date" value="{{ old('order_date', date('Y-m-d')) }}" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expected Delivery</label>
                        <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date') }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Billing Address</label>
                        <input type="text" name="billing_address" value="{{ old('billing_address') }}" class="form-control" placeholder="Billing address">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Shipping Address</label>
                        <input type="text" name="shipping_address" value="{{ old('shipping_address') }}" class="form-control" placeholder="Delivery address">
                    </div>
                </div>
                <button type="submit" class="btn btn-success rounded-pill mt-3"><i class="bi bi-arrow-right-circle me-1"></i>Convert to Sales Order</button>
            </form>
        @endif
    </div>
</div>
@endif

@if (!isset($prefillQuotation) || !$prefillQuotation || $isEdit)
<form method="POST" action="{{ $isEdit ? route('sales.orders.update', $order) : route('sales.orders.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Customer *</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select customer</option>
                        @php
                            $customers = \App\Models\Party::withoutGlobalScopes()->where('institute_id', $institute->id)->whereIn('type', ['customer','both'])->where('is_active', true)->orderBy('name')->get();
                            $selectedCustomer = old('customer_id', $order?->customer_id ?? $prefillData['customer_id'] ?? null);
                        @endphp
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" {{ (string)$selectedCustomer === (string)$c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->phone }})</option>
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
                <div class="col-md-2">
                    <label class="form-label">Currency *</label>
                    <select name="currency_id" class="form-select" required>
                        @foreach ($currencies as $cur)
                            <option value="{{ $cur->id }}" {{ (string)old('currency_id', $order?->currency_id ?? $prefillData['currency_id'] ?? '') === (string)$cur->id ? 'selected' : '' }}>{{ $cur->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Terms</label>
                    <input type="text" name="payment_terms" value="{{ old('payment_terms', $order?->payment_terms ?? $prefillData['payment_terms'] ?? '') }}" class="form-control" placeholder="Net 15">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Billing Address</label>
                    <textarea name="billing_address" class="form-control" rows="2" placeholder="Billing address">{{ old('billing_address', $order?->billing_address) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Shipping / Delivery Address</label>
                    <textarea name="shipping_address" class="form-control" rows="2" placeholder="Shipping address">{{ old('shipping_address', $order?->shipping_address) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $order?->notes ?? $prefillData['notes'] ?? '') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Terms & Conditions</label>
                    <textarea name="terms_conditions" class="form-control" rows="2">{{ old('terms_conditions', $order?->terms_conditions ?? $prefillData['terms_conditions'] ?? '') }}</textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Discount Type</label>
                    <select name="discount_type" class="form-select">
                        <option value="fixed" {{ old('discount_type', $order?->discount_type ?? $prefillData['discount_type'] ?? 'fixed') === 'fixed' ? 'selected' : '' }}>Fixed</option>
                        <option value="percent" {{ old('discount_type', $order?->discount_type ?? $prefillData['discount_type'] ?? '') === 'percent' ? 'selected' : '' }}>Percent</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Header Discount</label>
                    <input type="number" step="0.0001" min="0" name="discount_amount" value="{{ old('discount_amount', $order?->discount_amount ?? 0) }}" class="form-control">
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
                            <th style="min-width:200px">Product / Description *</th>
                            <th style="width:100px">Qty *</th>
                            <th style="width:80px">Unit</th>
                            <th style="width:120px">Unit Price *</th>
                            <th style="width:110px">Discount</th>
                            <th style="width:120px">Tax</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $oldLines = old('lines', $order?->lines?->toArray() ?? $prefillData['lines'] ?? [['description'=>'','quantity'=>1,'unit_price'=>0]]);
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
                                <td><input type="number" step="0.0001" min="0.0001" name="lines[{{ $idx }}][quantity]" value="{{ $line['quantity'] ?? 1 }}" class="form-control form-control-sm" required></td>
                                <td><input type="text" name="lines[{{ $idx }}][unit]" value="{{ $line['unit'] ?? '' }}" class="form-control form-control-sm" placeholder="pcs"></td>
                                <td><input type="number" step="0.0001" min="0" name="lines[{{ $idx }}][unit_price]" value="{{ $line['unit_price'] ?? 0 }}" class="form-control form-control-sm" required></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.0001" min="0" name="lines[{{ $idx }}][discount_amount]" value="{{ $line['discount_amount'] ?? 0 }}" class="form-control">
                                        <select name="lines[{{ $idx }}][discount_type]" class="form-select"><option value="fixed" {{ ($line['discount_type'] ?? 'fixed')==='fixed'?'selected':'' }}>Amt</option><option value="percent" {{ ($line['discount_type'] ?? '')==='percent'?'selected':'' }}>%</option></select>
                                    </div>
                                </td>
                                <td>
                                    <select name="lines[{{ $idx }}][tax_group_id]" class="form-select form-select-sm">
                                        <option value="">No Tax</option>
                                        @php $tgs = \App\Models\TaxGroup::withoutGlobalScopes()->where('institute_id', $institute->id)->where('is_active', true)->get(); @endphp
                                        @foreach ($tgs as $tg)
                                            <option value="{{ $tg->id }}" {{ ($line['tax_group_id'] ?? null)==$tg->id?'selected':'' }}>{{ $tg->name }} ({{ $tg->rate }}%)</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary rounded-pill">{{ $isEdit ? 'Update' : 'Create' }} Order</button>
        <a href="{{ $isEdit ? route('sales.orders.show', $order) : route('sales.orders.index') }}" class="btn btn-outline-secondary rounded-pill">Cancel</a>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
document.getElementById('addLine')?.addEventListener('click', function(){
    const tbody = document.querySelector('#linesTable tbody');
    if(!tbody) return;
    const idx = tbody.children.length;
    const row = tbody.children[0].cloneNode(true);
    row.querySelectorAll('input, select').forEach(el=>{
        el.name = el.name.replace(/lines\[\d+\]/, `lines[${idx}]`);
        if(el.tagName==='INPUT') el.value = el.type==='number' ? 0 : '';
        if(el.tagName==='SELECT') el.selectedIndex = 0;
    });
    tbody.appendChild(row);
});
document.addEventListener('click', function(e){
    if(e.target.closest('.remove-line')){
        const tr = e.target.closest('tr');
        if(document.querySelectorAll('#linesTable tbody tr').length > 1) tr.remove();
    }
});
</script>
@endpush
