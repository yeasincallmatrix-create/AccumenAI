@extends('layouts.institute')

@section('title', 'New Delivery — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">New Delivery</h4>
    <a href="{{ route('sales.deliveries.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('sales.deliveries.store') }}">
    @csrf

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Sales Order *</label>
                    <select name="order_id" class="form-select" required id="orderSelect" onchange="window.location='{{ route('sales.deliveries.create') }}?order_id='+this.value">
                        <option value="">Select order</option>
                        @foreach ($orders as $o)
                            <option value="{{ $o->id }}" {{ (request('order_id') == $o->id || $selectedOrder?->id == $o->id) ? 'selected' : '' }}>{{ $o->order_number }} — {{ $o->customer?->name }} ({{ $o->status }})</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Only approved/processing/ready orders</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Delivery Date *</label>
                    <input type="date" name="delivery_date" value="{{ old('delivery_date', date('Y-m-d')) }}" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">Auto (default)</option>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Shipping Address</label>
                    <textarea name="shipping_address" class="form-control" rows="2">{{ old('shipping_address', $selectedOrder?->shipping_address) }}</textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    @if ($selectedOrder)
        @php
            $deliveryService = app(\App\Services\Sales\DeliveryService::class);
        @endphp
        <div class="card mb-4">
            <div class="card-header">Order Lines — Partial Delivery</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Previously Delivered</th>
                            <th class="text-end">Remaining</th>
                            <th style="width:150px">This Delivery *</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($selectedOrder->lines as $ol)
                            @php $remaining = $deliveryService->remainingQuantityForOrderLine($ol); @endphp
                            <tr>
                                <td>
                                    {{ $ol->description }} <small class="text-muted">({{ $ol->unit }})</small>
                                    <input type="hidden" name="lines[{{ $loop->index }}][order_line_id]" value="{{ $ol->id }}">
                                    <input type="hidden" name="lines[{{ $loop->index }}][inventory_item_id]" value="{{ $ol->inventory_item_id }}">
                                    <div><small class="text-muted">{{ $ol->inventoryItem?->name ?? 'Service' }} @if($ol->inventoryItem && app(\App\Services\Sales\SalesCatalogService::class)->isStockable($ol->inventoryItem)) <span class="badge bg-warning text-dark">Stockable</span> @else <span class="badge bg-secondary">Service</span> @endif</small></div>
                                </td>
                                <td class="text-end">{{ number_format($ol->quantity, 2) }}</td>
                                <td class="text-end">{{ number_format($ol->quantity - $remaining, 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($remaining, 2) }}</td>
                                <td>
                                    <input type="number" step="0.0001" min="0" max="{{ $remaining }}" name="lines[{{ $loop->index }}][delivery_quantity]" value="{{ old('lines.'.$loop->index.'.delivery_quantity', $remaining) }}" class="form-control form-control-sm" {{ $remaining <= 0.00005 ? 'disabled' : '' }}>
                                    @if($remaining <= 0.00005)
                                        <small class="text-success">Fully delivered</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted small">Leave quantity 0 to skip a line. Delivery quantity cannot exceed remaining.</div>
        </div>
        <button type="submit" class="btn btn-primary rounded-pill">Create Delivery</button>
    @else
        <div class="alert alert-info">Select a sales order to see its lines and remaining quantities.</div>
        <button type="submit" class="btn btn-primary rounded-pill" disabled>Create Delivery</button>
    @endif

    <a href="{{ route('sales.deliveries.index') }}" class="btn btn-outline-secondary rounded-pill">Cancel</a>
</form>

<script>
// Auto-submit on order change already via onchange, but keep lines that are 0 will be filtered server-side
document.querySelector('form').addEventListener('submit', function(e){
    // Remove lines with 0 or empty quantity
    document.querySelectorAll('input[name*="delivery_quantity"]').forEach(function(input){
        if(parseFloat(input.value) <= 0){
            input.closest('tr').remove();
        }
    });
});
</script>
@endsection
