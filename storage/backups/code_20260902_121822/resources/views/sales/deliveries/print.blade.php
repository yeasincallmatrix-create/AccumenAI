<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery {{ $delivery->delivery_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>@media print { .no-print { display:none; } } body { padding:20px; }</style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3"><img src="{{ $institute->logo_url }}" alt="{{ $institute->name }} Logo" style="max-height:60px; object-fit:contain;"><h3 class="mb-0">{{ $institute->name }}</h3></div>
        <h4>DELIVERY NOTE<br><small class="text-muted">{{ $delivery->delivery_number }}</small></h4>
    </div>
    <div class="row mb-4">
        <div class="col-6">
            <strong>Deliver To:</strong><br>
            {{ $delivery->customer?->name }}<br>
            {{ $delivery->shipping_address ?? $delivery->customer?->address }}<br>
            {{ $delivery->customer?->phone }}<br>
            {{ $delivery->customer?->email }}
        </div>
        <div class="col-6 text-end">
            <p><strong>Order:</strong> {{ $delivery->order?->order_number }}<br>
            <strong>Date:</strong> {{ $delivery->delivery_date->format('Y-m-d') }}<br>
            <strong>Status:</strong> {{ ucfirst($delivery->status) }}<br>
            <strong>Warehouse:</strong> {{ $delivery->warehouse?->name ?? '—' }}</p>
        </div>
    </div>
    <table class="table table-bordered">
        <thead><tr><th>#</th><th>Description</th><th class="text-end">Ordered</th><th class="text-end">Previously</th><th class="text-end">This Delivery</th><th>Unit</th></tr></thead>
        <tbody>
            @foreach ($delivery->lines as $idx => $line)
                <tr>
                    <td>{{ $idx+1 }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="text-end">{{ number_format($line->ordered_quantity,2) }}</td>
                    <td class="text-end">{{ number_format($line->previously_delivered_quantity,2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($line->delivery_quantity,2) }}</td>
                    <td>{{ $line->unit ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if($delivery->notes)<p><strong>Notes:</strong> {{ $delivery->notes }}</p>@endif
    <div class="row mt-5">
        <div class="col-4 text-center"><hr>Delivered By</div>
        <div class="col-4 text-center"><hr>Received By</div>
        <div class="col-4 text-center"><hr>Authorized</div>
    </div>
    <div class="no-print mt-4">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
        <a href="{{ route('sales.deliveries.show', $delivery) }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>
</body>
</html>


