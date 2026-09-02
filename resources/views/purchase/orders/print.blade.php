<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $order->order_number }}</title>
    <style>
        body{font-family:system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:#212529; font-size:13px;}
        .header{display:flex;justify-content:space-between;border-bottom:2px solid #212529;padding-bottom:12px;margin-bottom:16px;}
        table{width:100%;border-collapse:collapse;}
        th,td{padding:6px 8px;border:1px solid #dee2e6;text-align:left;}
        th{background:#f8f9fa;}
        .text-end{text-align:right;}
        .badge{padding:2px 8px;border-radius:999px;background:#e9ecef;font-size:11px;}
        @media print{.no-print{display:none}}
    </style>
</head>
<body>
<div class="header">
    <div class="d-flex align-items-center gap-3">
        <img src="{{ $institute->logo_url }}" alt="{{ $institute->name }} Logo" style="max-height:60px; object-fit:contain;">
        <div>
            <h2 style="margin:0">{{ $institute->name }}</h2>
            <div class="text-muted">{{ $order->order_number }} — {{ ucfirst(str_replace('_',' ',$order->status)) }}</div>
            @if($order->reference_number)<div class="text-muted">Ref: {{ $order->reference_number }}</div>@endif
        </div>
    </div>
    <div style="text-align:right">
        <div><strong>Order Date:</strong> {{ $order->order_date?->format('Y-m-d') }}</div>
        <div><strong>Expected:</strong> {{ $order->expected_delivery_date?->format('Y-m-d') ?? '—' }}</div>
        <div><strong>Currency:</strong> {{ $order->currency?->code ?? '—' }}</div>
        <div><strong>Branch:</strong> {{ $order->branch?->name ?? 'Institute-wide' }}</div>
    </div>
</div>

<div style="display:flex;gap:24px;margin-bottom:16px">
    <div style="flex:1">
        <strong>Supplier</strong><br>{{ $order->supplier?->name ?? '—' }}<br>{{ $order->supplier?->phone }} {{ $order->supplier?->email ? '• '.$order->supplier?->email : '' }}<br>{{ $order->supplier?->address }}<br>
    </div>
    <div style="flex:1;text-align:right">
        <strong>Warehouse:</strong> {{ $order->warehouse?->name ?? '—' }}<br>
        @if($order->reference_number)<strong>Reference:</strong> {{ $order->reference_number }}<br>@endif
        <strong>Order #:</strong> {{ $order->order_number }}<br>
        <strong>Status:</strong> {{ ucfirst(str_replace('_',' ',$order->status)) }}
    </div>
</div>

<table>
    <thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Unit</th><th class="text-end">Unit Price</th><th class="text-end">Discount</th><th class="text-end">Tax</th><th class="text-end">Total</th></tr></thead>
    <tbody>
        @foreach($order->lines as $idx=>$line)
            <tr><td>{{ $idx+1 }}</td><td>{{ $line->description }} @if($line->inventoryItem) <small>({{ $line->inventoryItem->sku }})</small> @endif</td><td class="text-end">{{ number_format($line->quantity,2) }}</td><td>{{ $line->unit ?? '—' }}</td><td class="text-end">{{ number_format($line->unit_price,2) }}</td><td class="text-end">{{ number_format($line->discount_amount,2) }}</td><td class="text-end">{{ number_format($line->tax_amount,2) }} @if($line->tax_rate) <small>({{ $line->tax_rate }}%)</small> @endif</td><td class="text-end">{{ number_format($line->line_total,2) }}</td></tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th colspan="7" class="text-end">Subtotal</th><th class="text-end">{{ number_format($order->subtotal,2) }}</th></tr>
        <tr><th colspan="7" class="text-end">Discount</th><th class="text-end">-{{ number_format($order->discount_amount,2) }}</th></tr>
        <tr><th colspan="7" class="text-end">Tax</th><th class="text-end">{{ number_format($order->tax_amount,2) }}</th></tr>
        <tr><th colspan="7" class="text-end">Grand Total</th><th class="text-end">{{ number_format($order->grand_total,2) }} {{ $order->currency?->code }}</th></tr>
    </tfoot>
</table>

@if($order->notes)<p><strong>Notes:</strong><br>{{ $order->notes }}</p>@endif
@if($order->terms_conditions)<p><strong>Terms:</strong><br><small>{{ $order->terms_conditions }}</small></p>@endif

<p class="text-muted" style="margin-top:24px">Printed: {{ now()->format('Y-m-d H:i') }}</p>
<div class="no-print" style="margin-top:12px"><button onclick="window.print()">Print</button></div>
</body>
</html>
