@extends('layouts.standalone')
@section('title', 'Stock Valuation — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Stock Valuation Report</h4>
    <p>Current inventory valuation across all warehouses.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Items</small>
            <h4 class="mb-0 mt-1">{{ $total_items ?? 0 }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Qty On Hand</small>
            <h4 class="mb-0 mt-1">{{ number_format($total_qty ?? 0, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Valuation</small>
            <h4 class="mb-0 mt-1">{{ number_format($total_valuation ?? 0, 2) }}</h4>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Valuation by Warehouse</h6>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Warehouse</th>
                    <th>SKU</th>
                    <th>Item</th>
                    <th class="text-end">Qty On Hand</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Total Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items ?? [] as $row)
                    <tr>
                        <td>{{ $row->warehouse_name }}</td>
                        <td>{{ $row->sku }}</td>
                        <td>{{ $row->item_name }}</td>
                        <td class="text-end">{{ number_format($row->qty_on_hand, 2) }}</td>
                        <td class="text-end">{{ number_format($row->unit_cost, 2) }}</td>
                        <td class="text-end">{{ number_format($row->total_value, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No stock items found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
