@extends('layouts.institute')

@section('title', 'Quotation ' . $quotation->quotation_number . ' — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $quotation->quotation_number }} <span class="badge bg-{{ ['draft'=>'secondary','sent'=>'info','accepted'=>'success','rejected'=>'danger','expired'=>'warning','cancelled'=>'dark'][$quotation->status] ?? 'secondary' }}">{{ ucfirst($quotation->status) }}</span></h4>
    <div class="d-flex gap-2">
        <a href="{{ route('sales.quotations.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        <a href="{{ route('sales.quotations.print', $quotation) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-printer"></i> Print</a>
        @if ($quotation->isDraft())
            <a href="{{ route('sales.quotations.edit', $quotation) }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-pencil"></i> Edit</a>
        @endif
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
                <p class="mb-1 fw-semibold">{{ $quotation->customer?->name }}</p>
                <p class="mb-1 text-muted small">{{ $quotation->customer?->phone }} {{ $quotation->customer?->email ? '• ' . $quotation->customer?->email : '' }}</p>
                <p class="mb-1 text-muted small">{{ $quotation->customer?->address }}</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Quotation Date:</strong> {{ $quotation->quotation_date->format('Y-m-d') }}</p>
                <p class="mb-1"><strong>Valid Until:</strong> {{ $quotation->validity_date->format('Y-m-d') }} @if($quotation->isExpiredByDate()) <span class="badge bg-warning">Expired</span> @endif</p>
                <p class="mb-1"><strong>Currency:</strong> {{ $quotation->currency?->code }}</p>
                <p class="mb-1"><strong>Payment Terms:</strong> {{ $quotation->payment_terms ?? '—' }}</p>
                <p class="mb-1"><strong>Branch:</strong> {{ $quotation->branch?->name ?? 'Institute-wide' }}</p>
            </div>
        </div>
        @if ($quotation->notes)
            <div class="mt-3"><strong>Notes:</strong><p class="text-muted">{{ $quotation->notes }}</p></div>
        @endif
        @if ($quotation->terms_conditions)
            <div class="mt-2"><strong>Terms:</strong><p class="text-muted small">{{ $quotation->terms_conditions }}</p></div>
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
                    <th class="text-end">Qty</th>
                    <th>Unit</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Discount</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotation->lines as $idx => $line)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $line->description }} @if($line->inventoryItem) <small class="text-muted">({{ $line->inventoryItem->sku }})</small> @endif</td>
                        <td class="text-end">{{ number_format($line->quantity, 2) }}</td>
                        <td>{{ $line->unit ?? '—' }}</td>
                        <td class="text-end">{{ number_format($line->unit_price, 2) }}</td>
                        <td class="text-end">{{ number_format($line->discount_amount, 2) }}</td>
                        <td class="text-end">{{ number_format($line->tax_amount, 2) }} @if($line->tax_rate) <small class="text-muted">({{ $line->tax_rate }}%)</small> @endif</td>
                        <td class="text-end fw-semibold">{{ number_format($line->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><th colspan="7" class="text-end">Subtotal</th><th class="text-end">{{ number_format($quotation->subtotal, 2) }}</th></tr>
                <tr><th colspan="7" class="text-end">Discount</th><th class="text-end text-danger">-{{ number_format($quotation->discount_amount, 2) }}</th></tr>
                <tr><th colspan="7" class="text-end">Tax</th><th class="text-end">{{ number_format($quotation->tax_amount, 2) }}</th></tr>
                <tr class="table-light"><th colspan="7" class="text-end">Grand Total</th><th class="text-end">{{ number_format($quotation->grand_total, 2) }} {{ $quotation->currency?->code }}</th></tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-2">
        @if ($quotation->status === 'draft')
            <form method="POST" action="{{ route('sales.quotations.send', $quotation) }}">@csrf<button class="btn btn-info rounded-pill">Send</button></form>
            <form method="POST" action="{{ route('sales.quotations.cancel', $quotation) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
        @elseif ($quotation->status === 'sent')
            <form method="POST" action="{{ route('sales.quotations.accept', $quotation) }}">@csrf<button class="btn btn-success rounded-pill">Accept</button></form>
            <form method="POST" action="{{ route('sales.quotations.reject', $quotation) }}">@csrf<button class="btn btn-danger rounded-pill">Reject</button></form>
            <form method="POST" action="{{ route('sales.quotations.expire', $quotation) }}">@csrf<button class="btn btn-warning rounded-pill">Expire</button></form>
            <form method="POST" action="{{ route('sales.quotations.cancel', $quotation) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
        @endif

        @if ($quotation->converted_to_order_id)
            <span class="badge bg-success align-self-center">Converted to Order #{{ $quotation->converted_to_order_id }}</span>
        @elseif ($quotation->status === 'accepted')
            <span class="badge bg-success align-self-center">Ready for conversion to Sales Order (S-4)</span>
        @endif
    </div>
</div>
@endsection
