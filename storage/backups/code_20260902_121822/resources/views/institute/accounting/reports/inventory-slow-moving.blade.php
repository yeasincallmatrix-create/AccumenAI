@extends('layouts.standalone')
@section('title', 'Slow-Moving Inventory — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Slow-Moving Inventory</h4>
    <p>Items with no movement over the last {{ number_format($summary['period_days']) }} days but still having stock on hand.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <div class="admin-card text-center">
            <small class="text-muted">Slow-Moving Items</small>
            <h5 class="mb-0">{{ number_format($summary['total_slow_moving']) }}</h5>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card text-center">
            <small class="text-muted">Total Value at Risk</small>
            <h5 class="mb-0 text-warning">{{ number_format($summary['total_value'], 2) }}</h5>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Slow-Moving Items</h6>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Item</th>
                    <th>Warehouse</th>
                    <th class="text-end">Qty On Hand</th>
                    <th class="text-end">Avg Cost</th>
                    <th class="text-end">Total Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item['sku'] ?? '-' }}</td>
                        <td>{{ $item['item_name'] ?? '-' }}</td>
                        <td>{{ $item['warehouse_name'] ?? '-' }}</td>
                        <td class="text-end">{{ number_format($item['quantity'], 2) }}</td>
                        <td class="text-end">{{ number_format($item['avg_cost'], 2) }}</td>
                        <td class="text-end">{{ number_format($item['value'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No slow-moving items found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
