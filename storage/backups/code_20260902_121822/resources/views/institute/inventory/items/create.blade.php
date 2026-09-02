@extends('layouts.standalone')
@section('title', ($isEdit ?? false) ? 'Edit Item — AccumenAI' : 'New Item — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>{{ ($isEdit ?? false) ? 'Edit Inventory Item' : 'New Inventory Item' }}</h4>
</div>

@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ ($isEdit ?? false) ? route('inventory.items.update', $item) : route('inventory.items.store') }}">
            @csrf
            @if($isEdit ?? false) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" value="{{ old('name', $item->name ?? '') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">SKU</label>
                    <input type="text" class="form-control" name="sku" value="{{ old('sku', $item->sku ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Barcode</label>
                    <input type="text" class="form-control" name="barcode" value="{{ old('barcode', $item->barcode ?? '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Item Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="item_type" required>
                        @foreach($itemTypes as $type)
                            <option value="{{ $type }}" @selected(old('item_type', $item->item_type ?? '') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category_id">
                        <option value="">None</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $item->category_id ?? '') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unit</label>
                    <input type="text" class="form-control" name="unit" value="{{ old('unit', $item->unit ?? '') }}" placeholder="e.g. pc, kg, box">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2">{{ old('description', $item->description ?? '') }}</textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Purchase Price</label>
                    <input type="number" step="0.0001" class="form-control" name="purchase_price" value="{{ old('purchase_price', $item->purchase_price ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Selling Price</label>
                    <input type="number" step="0.0001" class="form-control" name="selling_price" value="{{ old('selling_price', $item->selling_price ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Reorder Level</label>
                    <input type="number" step="0.0001" class="form-control" name="reorder_level" value="{{ old('reorder_level', $item->reorder_level ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Min Stock</label>
                    <input type="number" step="0.0001" class="form-control" name="min_stock" value="{{ old('min_stock', $item->min_stock ?? '') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Max Stock</label>
                    <input type="number" step="0.0001" class="form-control" name="max_stock" value="{{ old('max_stock', $item->max_stock ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="is_active">
                        <option value="1" @selected(old('is_active', $item->is_active ?? 1))>Active</option>
                        <option value="0" @selected(old('is_active', $item->is_active ?? 1) == 0)>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-check-circle me-1"></i>{{ ($isEdit ?? false) ? 'Update Item' : 'Create Item' }}</button>
                <a href="{{ route('inventory.items.index') }}" class="btn btn-outline-secondary rounded-pill">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
