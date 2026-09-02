@extends('layouts.institute')

@section('title', ($isEdit ? 'Edit' : 'New') . ' Quotation — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $isEdit ? 'Edit Quotation' : 'New Quotation' }} @if($isEdit) <small class="text-muted">{{ $quotation->quotation_number }}</small> @endif</h4>
    <a href="{{ route('sales.quotations.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $isEdit ? route('sales.quotations.update', $quotation) : route('sales.quotations.store') }}">
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
                        @endphp
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" {{ old('customer_id', $quotation?->customer_id) == $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->phone }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quotation Date *</label>
                    <input type="date" name="quotation_date" value="{{ old('quotation_date', $quotation?->quotation_date?->format('Y-m-d') ?? date('Y-m-d')) }}" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Valid Until *</label>
                    <input type="date" name="validity_date" value="{{ old('validity_date', $quotation?->validity_date?->format('Y-m-d') ?? date('Y-m-d', strtotime('+30 days'))) }}" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Currency *</label>
                    <select name="currency_id" class="form-select" required>
                        @foreach ($currencies as $cur)
                            <option value="{{ $cur->id }}" {{ old('currency_id', $quotation?->currency_id) == $cur->id ? 'selected' : '' }}>{{ $cur->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Terms</label>
                    <input type="text" name="payment_terms" value="{{ old('payment_terms', $quotation?->payment_terms) }}" class="form-control" placeholder="Net 15">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $quotation?->notes) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Terms & Conditions</label>
                    <textarea name="terms_conditions" class="form-control" rows="2">{{ old('terms_conditions', $quotation?->terms_conditions) }}</textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Discount Type</label>
                    <select name="discount_type" class="form-select">
                        <option value="fixed" {{ old('discount_type', $quotation?->discount_type) === 'fixed' ? 'selected' : '' }}>Fixed</option>
                        <option value="percent" {{ old('discount_type', $quotation?->discount_type) === 'percent' ? 'selected' : '' }}>Percent</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Header Discount</label>
                    <input type="number" step="0.0001" min="0" name="discount_amount" value="{{ old('discount_amount', $quotation?->discount_amount) }}" class="form-control">
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
                            $oldLines = old('lines', $quotation?->lines?->toArray() ?? [['description'=>'','quantity'=>1,'unit_price'=>0]]);
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
        <button type="submit" class="btn btn-primary rounded-pill">{{ $isEdit ? 'Update' : 'Create' }} Quotation</button>
        <a href="{{ route('sales.quotations.index') }}" class="btn btn-outline-secondary rounded-pill">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('addLine')?.addEventListener('click', function(){
    const tbody = document.querySelector('#linesTable tbody');
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
