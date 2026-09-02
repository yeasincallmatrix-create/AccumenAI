@extends('layouts.institute')

@section('title', 'Delivery ' . $delivery->delivery_number . ' — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $delivery->delivery_number }} <span class="badge bg-{{ ['draft'=>'secondary','confirmed'=>'success','delivered'=>'primary','cancelled'=>'dark'][$delivery->status] ?? 'secondary' }}">{{ ucfirst($delivery->status) }}</span></h4>
    <div class="d-flex gap-2">
        <a href="{{ route('sales.deliveries.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        <a href="{{ route('sales.deliveries.print', $delivery) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-printer"></i> Print</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Customer</h6>
                <p class="mb-1 fw-semibold">{{ $delivery->customer?->name }}</p>
                <p class="mb-1 text-muted small">{{ $delivery->customer?->phone }} {{ $delivery->customer?->email ? '• ' . $delivery->customer?->email : '' }}</p>
                <p class="mb-1"><strong>Order:</strong> <a href="{{ route('sales.orders.show', $delivery->order) }}">{{ $delivery->order?->order_number }}</a></p>
                <p class="mb-1"><strong>Warehouse:</strong> {{ $delivery->warehouse?->name ?? '—' }}</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Delivery Date:</strong> {{ $delivery->delivery_date->format('Y-m-d') }}</p>
                <p class="mb-1"><strong>Branch:</strong> {{ $delivery->branch?->name ?? 'Institute-wide' }}</p>
                @if($delivery->delivered_at)
                    <p class="mb-1"><strong>Delivered At:</strong> {{ $delivery->delivered_at->format('Y-m-d H:i') }}</p>
                @endif
                <p class="mb-1"><strong>Shipping:</strong> {{ $delivery->shipping_address ?? '—' }}</p>
            </div>
        </div>
        @if ($delivery->notes)
            <div class="mt-3"><strong>Notes:</strong><p class="text-muted">{{ $delivery->notes }}</p></div>
        @endif
    </div>
</div>

<div class="card mb-4">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th class="text-end">Ordered</th>
                    <th class="text-end">Previously</th>
                    <th class="text-end">This Delivery</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($delivery->lines as $idx => $line)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $line->description }} @if($line->inventoryItem) <small class="text-muted">({{ $line->inventoryItem->sku }})</small> @endif</td>
                        <td class="text-end">{{ number_format($line->ordered_quantity, 2) }}</td>
                        <td class="text-end">{{ number_format($line->previously_delivered_quantity, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($line->delivery_quantity, 2) }}</td>
                        <td>{{ $line->unit ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-2">
        @if ($delivery->isDraft())
            <form method="POST" action="{{ route('sales.deliveries.confirm', $delivery) }}">@csrf<button class="btn btn-success rounded-pill" onclick="return confirm('Confirm delivery and move inventory?')">Confirm Delivery</button></form>
            <form method="POST" action="{{ route('sales.deliveries.cancel', $delivery) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel Delivery</button></form>
        @elseif ($delivery->isConfirmed())
            <span class="badge bg-success align-self-center">Inventory moved — Ready for Invoice (S-6)</span>
            <form method="POST" action="{{ route('sales.deliveries.invoice', $delivery) }}">@csrf<button class="btn btn-info rounded-pill"><i class="bi bi-receipt"></i> Create Invoice from Delivery</button></form>
            <form method="POST" action="{{ route('sales.deliveries.cancel', $delivery) }}">@csrf<button class="btn btn-warning rounded-pill" onclick="return confirm('Cancel confirmed delivery and reverse inventory?')">Cancel & Reverse</button></form>
        @else
            <span class="badge bg-secondary">No actions — {{ ucfirst($delivery->status) }}</span>
        @endif
    </div>
</div>

@if($delivery->order)
    @php
        $order = $delivery->order->load('lines');
        $deliveryService = app(\App\Services\Sales\DeliveryService::class);
    @endphp
    <div class="card">
        <div class="card-header">Order Delivery Progress</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Order Line</th><th class="text-end">Ordered</th><th class="text-end">Delivered</th><th class="text-end">Remaining</th></tr></thead>
                <tbody>
                    @foreach ($order->lines as $ol)
                        @php $delivered = $deliveryService->deliveredQuantityForOrderLine($ol); $remaining = $deliveryService->remainingQuantityForOrderLine($ol); @endphp
                        <tr>
                            <td>{{ $ol->description }}</td>
                            <td class="text-end">{{ number_format($ol->quantity,2) }}</td>
                            <td class="text-end">{{ number_format($delivered,2) }}</td>
                            <td class="text-end {{ $remaining>0.00005 ? 'text-warning fw-semibold' : 'text-success' }}">{{ number_format($remaining,2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($deliveryService->isOrderFullyDelivered($order))
            <div class="card-footer text-success">Fully delivered — Ready for Invoice</div>
        @endif
    </div>
@endif

@php $deliveryInvoices = \App\Models\Invoice::where('sales_delivery_id',$delivery->id)->whereNotIn('status',['cancelled'])->get(); @endphp
@if($deliveryInvoices->isNotEmpty())
    <div class="card mt-4">
        <div class="card-header"><i class="bi bi-receipt me-1"></i>Invoices for this Delivery</div>
        <ul class="list-group list-group-flush">
            @foreach($deliveryInvoices as $inv)
                <li class="list-group-item d-flex justify-content-between"><span>{{ $inv->invoice_number }} • {{ ucfirst($inv->status) }} • {{ number_format($inv->payable_amount,2) }} payable</span><a href="{{ route('finance.invoices.show',$inv) }}" class="btn btn-sm btn-outline-primary">Finance View</a></li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
