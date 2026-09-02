@extends('layouts.standalone')
@section('title', 'Low Stock — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>Low Stock Items</h4>
    <p>Items at or below their reorder level. Review and restock to avoid stockouts.</p>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Item</th>
                    <th>SKU</th>
                    <th>Warehouse</th>
                    <th class="text-end">On Hand</th>
                    <th class="text-end">Reorder Level</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $idx => $row)
                    <tr>
                        <td class="text-muted">{{ $idx + 1 }}</td>
                        <td><span class="fw-semibold">{{ $row['item_name'] }}</span></td>
                        <td>{{ $row['sku'] ?? '—' }}</td>
                        <td>{{ $row['warehouse_name'] ?? '—' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($row['quantity'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['reorder_level'], 2) }}</td>
                        <td>
                            @if($row['status'] === 'out_of_stock')
                                <span class="badge text-bg-danger">Out of Stock</span>
                            @else
                                <span class="badge text-bg-warning text-dark">Low Stock</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">All items are above reorder levels.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
