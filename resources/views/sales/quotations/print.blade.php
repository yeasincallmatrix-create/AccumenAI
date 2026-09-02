<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quotation->quotation_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display:none; } }
        body { padding:20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ $institute->logo_url }}" alt="{{ $institute->name }} Logo" style="max-height:60px; object-fit:contain;">
            <h3 class="mb-0">{{ $institute->name }}</h3>
        </div>
        <h4>QUOTATION<br><small class="text-muted">{{ $quotation->quotation_number }}</small></h4>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <strong>Bill To:</strong><br>
            {{ $quotation->customer?->name }}<br>
            {{ $quotation->customer?->address }}<br>
            {{ $quotation->customer?->phone }}<br>
            {{ $quotation->customer?->email }}
        </div>
        <div class="col-6 text-end">
            <p><strong>Date:</strong> {{ $quotation->quotation_date->format('Y-m-d') }}<br>
            <strong>Valid Until:</strong> {{ $quotation->validity_date->format('Y-m-d') }}<br>
            <strong>Currency:</strong> {{ $quotation->currency?->code }}<br>
            <strong>Status:</strong> {{ ucfirst($quotation->status) }}</p>
        </div>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr><th>#</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Discount</th><th class="text-end">Tax</th><th class="text-end">Total</th></tr>
        </thead>
        <tbody>
            @foreach ($quotation->lines as $idx => $line)
                <tr>
                    <td>{{ $idx+1 }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="text-end">{{ number_format($line->quantity,2) }} {{ $line->unit }}</td>
                    <td class="text-end">{{ number_format($line->unit_price,2) }}</td>
                    <td class="text-end">{{ number_format($line->discount_amount,2) }}</td>
                    <td class="text-end">{{ number_format($line->tax_amount,2) }}</td>
                    <td class="text-end">{{ number_format($line->line_total,2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><th colspan="6" class="text-end">Subtotal</th><th class="text-end">{{ number_format($quotation->subtotal,2) }}</th></tr>
            <tr><th colspan="6" class="text-end">Discount</th><th class="text-end">-{{ number_format($quotation->discount_amount,2) }}</th></tr>
            <tr><th colspan="6" class="text-end">Tax</th><th class="text-end">{{ number_format($quotation->tax_amount,2) }}</th></tr>
            <tr><th colspan="6" class="text-end">Grand Total</th><th class="text-end">{{ number_format($quotation->grand_total,2) }} {{ $quotation->currency?->code }}</th></tr>
        </tfoot>
    </table>

    @if($quotation->notes)
        <p><strong>Notes:</strong> {{ $quotation->notes }}</p>
    @endif
    @if($quotation->terms_conditions)
        <p><strong>Terms:</strong><br>{{ $quotation->terms_conditions }}</p>
    @endif

    <div class="no-print mt-4">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
        <a href="{{ route('sales.quotations.show', $quotation) }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>
</body>
</html>
