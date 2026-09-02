@extends('layouts.standalone')
@section('title', ($item->name ?? 'Item') . ' — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>{{ $item->name }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('inventory.items.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        <a href="{{ route('inventory.items.edit', $item) }}" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-pencil-square"></i> Edit</a>
    </div>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6>Item Details</h6>
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" style="width:40%">SKU</td><td>{{ $item->sku ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Barcode</td><td>{{ $item->barcode ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Type</td><td><span class="badge text-bg-light border">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span></td></tr>
                    <tr><td class="text-muted">Category</td><td>{{ $item->category?->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Unit</td><td>{{ $item->unit ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                    @if($item->description)<tr><td class="text-muted">Description</td><td>{{ $item->description }}</td></tr>@endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6>Pricing & Stock</h6>
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted" style="width:40%">Purchase Price</td><td>{{ $item->purchase_price ? number_format($item->purchase_price, 4) : '—' }}</td></tr>
                    <tr><td class="text-muted">Selling Price</td><td>{{ $item->selling_price ? number_format($item->selling_price, 4) : '—' }}</td></tr>
                    <tr><td class="text-muted">Reorder Level</td><td>{{ $item->reorder_level ? number_format($item->reorder_level, 4) : '—' }}</td></tr>
                    <tr><td class="text-muted">Min Stock</td><td>{{ $item->min_stock ? number_format($item->min_stock, 4) : '—' }}</td></tr>
                    <tr><td class="text-muted">Max Stock</td><td>{{ $item->max_stock ? number_format($item->max_stock, 4) : '—' }}</td></tr>
                    <tr><td class="text-muted fw-bold">Total On Hand</td><td class="fw-bold">{{ number_format($item->onHand(), 4) }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6>Stock Levels by Warehouse</h6>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Warehouse</th><th class="text-end">Quantity</th><th class="text-end">Avg Cost</th><th class="text-end">Value</th></tr></thead>
                <tbody>
                    @forelse($item->stockLevels as $level)
                        <tr>
                            <td>{{ $level->warehouse?->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($level->quantity, 4) }}</td>
                            <td class="text-end">{{ number_format($level->avg_cost, 4) }}</td>
                            <td class="text-end">{{ number_format($level->quantity * $level->avg_cost, 4) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No stock levels recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
