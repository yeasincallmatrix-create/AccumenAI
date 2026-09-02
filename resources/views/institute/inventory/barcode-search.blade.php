@extends('layouts.standalone')
@section('title', 'Barcode Search — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>Barcode Search</h4>
    <p>Look up inventory items by barcode. Displays item details and current stock levels.</p>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('inventory.barcode-search') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Barcode</label>
                <input type="search" class="form-control form-control-sm" name="barcode" value="{{ request('barcode') }}" placeholder="Scan or enter barcode">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('inventory.barcode-search') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

@if(request('barcode'))
    <div class="admin-card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th class="text-end">On Hand</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td><span class="fw-semibold">{{ $item->name }}</span></td>
                            <td>{{ $item->sku ?? '—' }}</td>
                            <td><code>{{ $item->barcode }}</code></td>
                            <td>{{ $item->category?->name ?? '—' }}</td>
                            <td><span class="badge text-bg-light border">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span></td>
                            <td class="text-end fw-semibold">{{ number_format($item->stockLevels->sum('quantity'), 2) }}</td>
                            <td>
                                <span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No item found with barcode "{{ request('barcode') }}".</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->isNotEmpty())
            <div class="p-3 border-top">
                <h6 class="mb-2">Stock Levels</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Warehouse</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Avg Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                @foreach($item->stockLevels as $level)
                                    <tr>
                                        <td>{{ $item->name }}</td>
                                        <td>{{ $level->warehouse?->name ?? '—' }}</td>
                                        <td class="text-end fw-semibold">{{ number_format($level->quantity, 2) }}</td>
                                        <td class="text-end">{{ number_format($level->avg_cost, 2) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endif
@endsection
