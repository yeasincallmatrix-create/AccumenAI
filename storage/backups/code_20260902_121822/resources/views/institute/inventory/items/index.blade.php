@extends('layouts.standalone')
@section('title', 'Inventory Items — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>Inventory Items</h4>
    <p>Manage stock items, consumables, medicines, raw materials and other inventory. SKU and barcode are unique per scope.</p>
    <a href="{{ route('inventory.items.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Item</a>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('inventory.items.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Name, SKU or barcode">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Category</label>
                <select class="form-select form-select-sm" name="category_id" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id')==$cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" name="item_type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    @foreach(\App\Services\Inventory\InventoryItemService::ITEM_TYPES as $type)
                        <option value="{{ $type }}" @selected(request('item_type')===$type)>{{ ucfirst(str_replace('_',' ',$type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="active" @selected(request('status')==='active')>Active</option>
                    <option value="inactive" @selected(request('status')==='inactive')>Inactive</option>
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('inventory.items.index') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Item</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th class="text-end">On Hand</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td class="text-muted">{{ $items->firstItem() + $loop->index }}</td>
                        <td>
                            <a href="{{ route('inventory.items.show', $item) }}" class="fw-semibold">{{ $item->name }}</a>
                            @if($item->unit)<div class="text-muted small">{{ $item->unit }}</div>@endif
                        </td>
                        <td>{{ $item->sku ?? '—' }}</td>
                        <td>{{ $item->category?->name ?? '—' }}</td>
                        <td><span class="badge text-bg-light border">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span></td>
                        <td class="text-end fw-semibold">{{ number_format((float) ($item->on_hand_qty ?? $item->onHand()), 2) }}</td>
                        <td>
                            <span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('inventory.items.edit', $item) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                            <form method="POST" action="{{ route('inventory.items.destroy', $item) }}" class="d-inline" data-ajax-delete="1" data-confirm="Move this item to trash?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No inventory items found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())
        <div class="p-2 border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
