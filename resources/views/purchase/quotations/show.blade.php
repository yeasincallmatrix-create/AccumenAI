@extends('layouts.institute')

@section('title', 'Purchase Quotation ' . $quotation->quotation_number . ' — AccumenAI')

@php
    $colors = ['draft'=>'secondary','sent'=>'info','accepted'=>'success','rejected'=>'danger','expired'=>'warning','cancelled'=>'dark'];
    $u = auth()->user();
    $canView = true;
    $canUpdate = $u && method_exists($u, 'hasPermission') ? ($u->hasPermission('purchase.update') || $u->hasPermission('purchase.manage') || (method_exists($u,'isOwner') && $u->isOwner())) : true;
    $canManage = $u && method_exists($u, 'hasPermission') ? ($u->hasPermission('purchase.manage') || (method_exists($u,'isOwner') && $u->isOwner())) : true;
    $canCreate = $u && method_exists($u, 'hasPermission') ? ($u->hasPermission('purchase.create') || $u->hasPermission('purchase.manage') || (method_exists($u,'isOwner') && $u->isOwner())) : true;
    if (!($u instanceof \App\Models\InstituteUser) && !method_exists($u,'hasPermission')) {
        $m = \App\Support\Workspace::membership();
        if ($m) {
            $canUpdate = $m->hasPermission('purchase.update') || $m->hasPermission('purchase.manage');
            $canManage = $m->hasPermission('purchase.manage');
            $canCreate = $m->hasPermission('purchase.create') || $m->hasPermission('purchase.manage');
        }
    }
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $quotation->quotation_number }} <span class="badge bg-{{ $colors[$quotation->status] ?? 'secondary' }}">{{ ucfirst($quotation->status) }}</span></h4>
    <div class="d-flex gap-2">
        <a href="{{ route('purchase.quotations.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        @if ($quotation->isDraft() && $canUpdate)
            <a href="{{ route('purchase.quotations.edit', $quotation) }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-pencil"></i> Edit</a>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Supplier</h6>
                <p class="mb-1 fw-semibold">{{ $quotation->supplier?->name ?? '—' }}</p>
                <p class="mb-1 text-muted small">{{ $quotation->supplier?->phone }} {{ $quotation->supplier?->email ? '• ' . $quotation->supplier?->email : '' }}</p>
                <p class="mb-1 text-muted small">{{ $quotation->supplier?->address }}</p>
                <hr>
                <p class="mb-1"><strong>Branch:</strong> <span class="text-muted">{{ $quotation->branch?->name ?? 'Institute-wide' }}</span></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Quotation Date:</strong> {{ $quotation->quotation_date?->format('Y-m-d') }}</p>
                <p class="mb-1"><strong>Valid Until:</strong> {{ $quotation->validity_date?->format('Y-m-d') ?? '—' }} @if($quotation->isExpiredByDate()) <span class="badge bg-warning">Expired</span> @endif</p>
                <p class="mb-1"><strong>Currency:</strong> {{ $quotation->currency?->code ?? '—' }}</p>
                <p class="mb-1"><strong>Reference:</strong> {{ $quotation->reference ?? $quotation->reference_number ?? '—' }}</p>
                @if ($quotation->converted_to_order_id)
                    <p class="mb-1"><strong>Converted Order:</strong> <a href="{{ route('purchase.orders.show', $quotation->converted_to_order_id) }}">#{{ $quotation->converted_to_order_id }}</a></p>
                @endif
                @if ($quotation->converted_at)
                    <p class="mb-1 small text-muted">Converted: {{ $quotation->converted_at->format('Y-m-d H:i') }}</p>
                @endif
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
                        <td class="text-end">{{ number_format($line->discount_amount, 2) }} @if(($line->discount_type ?? 'fixed') === 'percent' || ($line->discount_type ?? '') === 'percentage') <small class="text-muted">({{ $line->discount_rate ?? $line->discount_amount }}%)</small> @endif</td>
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
            @if ($canUpdate)
                <form method="POST" action="{{ route('purchase.quotations.send', $quotation) }}">@csrf<button class="btn btn-info rounded-pill">Send</button></form>
                <form method="POST" action="{{ route('purchase.quotations.cancel', $quotation) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
            @endif
        @elseif ($quotation->status === 'sent')
            @if ($canManage)
                <form method="POST" action="{{ route('purchase.quotations.accept', $quotation) }}">@csrf<button class="btn btn-success rounded-pill">Accept</button></form>
                <form method="POST" action="{{ route('purchase.quotations.reject', $quotation) }}">@csrf<button class="btn btn-danger rounded-pill">Reject</button></form>
            @endif
            @if ($canUpdate)
                <form method="POST" action="{{ route('purchase.quotations.cancel', $quotation) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
            @endif
            <form method="POST" action="{{ route('purchase.quotations.expire', $quotation) ?? '#' }}">@csrf<button class="btn btn-warning rounded-pill" @if(!Route::has('purchase.quotations.expire')) type="button" disabled title="No route" @endif>Expire</button></form>
        @elseif ($quotation->status === 'accepted')
            @if ($quotation->converted_to_order_id)
                <span class="badge bg-success align-self-center">Converted to Order #{{ $quotation->converted_to_order_id }} — <a href="{{ route('purchase.orders.show', $quotation->converted_to_order_id) }}" class="text-white text-decoration-underline">View Order</a></span>
            @elseif ($canCreate)
                <form method="POST" action="{{ route('purchase.quotations.convert', $quotation) }}">@csrf<button class="btn btn-primary rounded-pill"><i class="bi bi-arrow-repeat me-1"></i>Convert to Purchase Order</button></form>
            @else
                <span class="badge bg-success align-self-center">Ready for conversion to Purchase Order</span>
            @endif
        @elseif ($quotation->status === 'rejected')
            <span class="badge bg-danger align-self-center">Rejected</span>
        @elseif ($quotation->status === 'expired')
            <span class="badge bg-warning text-dark align-self-center">Expired</span>
        @elseif ($quotation->status === 'cancelled')
            <span class="badge bg-dark align-self-center">Cancelled</span>
        @endif
    </div>
</div>
@endsection
