@extends('layouts.institute')

@section('title', 'Order ' . $order->order_number . ' — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $order->order_number }} <span class="badge bg-{{ ['draft'=>'secondary','pending_approval'=>'warning','approved'=>'info','rejected'=>'danger','processing'=>'primary','ready_for_delivery'=>'success','completed'=>'dark','cancelled'=>'dark'][$order->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$order->status)) }}</span></h4>
    <div class="d-flex gap-2">
        <a href="{{ route('sales.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        <a href="{{ route('sales.orders.print', $order) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-printer"></i> Print</a>
        @if (in_array($order->status, ['draft','rejected']))
            <a href="{{ route('sales.orders.edit', $order) }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-pencil"></i> Edit</a>
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
                <h6>Customer</h6>
                <p class="mb-1 fw-semibold">{{ $order->customer?->name }}</p>
                <p class="mb-1 text-muted small">{{ $order->customer?->phone }} {{ $order->customer?->email ? '• ' . $order->customer?->email : '' }}</p>
                <p class="mb-1 text-muted small">{{ $order->customer?->address }}</p>
                <hr>
                <p class="mb-1"><strong>Billing Address:</strong><br><span class="text-muted">{{ $order->billing_address ?? '—' }}</span></p>
                <p class="mb-1"><strong>Shipping Address:</strong><br><span class="text-muted">{{ $order->shipping_address ?? '—' }}</span></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Order Date:</strong> {{ $order->order_date->format('Y-m-d') }}</p>
                <p class="mb-1"><strong>Expected Delivery:</strong> {{ $order->expected_delivery_date?->format('Y-m-d') ?? '—' }}</p>
                <p class="mb-1"><strong>Currency:</strong> {{ $order->currency?->code }}</p>
                <p class="mb-1"><strong>Payment Terms:</strong> {{ $order->payment_terms ?? '—' }}</p>
                <p class="mb-1"><strong>Branch:</strong> {{ $order->branch?->name ?? 'Institute-wide' }}</p>
                <p class="mb-1"><strong>Quotation Ref:</strong>
                    @if ($order->quotation)
                        <a href="{{ route('sales.quotations.show', $order->quotation) }}">{{ $order->quotation->quotation_number }}</a>
                    @else
                        — <small class="text-muted">(Direct order)</small>
                    @endif
                </p>
                @if ($order->submitted_at)
                    <p class="mb-1 small text-muted">Submitted: {{ $order->submitted_at->format('Y-m-d H:i') }}</p>
                @endif
                @if ($order->approved_at)
                    <p class="mb-1 small text-muted">Approved: {{ $order->approved_at->format('Y-m-d H:i') }}</p>
                @endif
            </div>
        </div>
        @if ($order->notes)
            <div class="mt-3"><strong>Notes:</strong><p class="text-muted">{{ $order->notes }}</p></div>
        @endif
        @if ($order->terms_conditions)
            <div class="mt-2"><strong>Terms:</strong><p class="text-muted small">{{ $order->terms_conditions }}</p></div>
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
                @foreach ($order->lines as $idx => $line)
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
                <tr><th colspan="7" class="text-end">Subtotal</th><th class="text-end">{{ number_format($order->subtotal, 2) }}</th></tr>
                <tr><th colspan="7" class="text-end">Discount</th><th class="text-end text-danger">-{{ number_format($order->discount_amount, 2) }}</th></tr>
                <tr><th colspan="7" class="text-end">Tax</th><th class="text-end">{{ number_format($order->tax_amount, 2) }}</th></tr>
                <tr class="table-light"><th colspan="7" class="text-end">Grand Total</th><th class="text-end">{{ number_format($order->grand_total, 2) }} {{ $order->currency?->code }}</th></tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-2">
        @if ($order->status === 'draft')
            <form method="POST" action="{{ route('sales.orders.submit', $order) }}">@csrf<button class="btn btn-warning rounded-pill">Submit for Approval</button></form>
            <form method="POST" action="{{ route('sales.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
        @elseif ($order->status === 'pending_approval')
            <form method="POST" action="{{ route('sales.orders.approve', $order) }}">@csrf<button class="btn btn-success rounded-pill">Approve</button></form>
            <form method="POST" action="{{ route('sales.orders.reject', $order) }}">@csrf<button class="btn btn-danger rounded-pill">Reject</button></form>
            <form method="POST" action="{{ route('sales.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
        @elseif ($order->status === 'rejected')
            <form method="POST" action="{{ route('sales.orders.submit', $order) }}">@csrf<button class="btn btn-warning rounded-pill">Resubmit</button></form>
            <form method="POST" action="{{ route('sales.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
        @elseif ($order->status === 'approved')
            <form method="POST" action="{{ route('sales.orders.processing', $order) }}">@csrf<button class="btn btn-primary rounded-pill">Move to Processing</button></form>
            <form method="POST" action="{{ route('sales.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
        @elseif ($order->status === 'processing')
            <form method="POST" action="{{ route('sales.orders.ready', $order) }}">@csrf<button class="btn btn-success rounded-pill">Ready for Delivery</button></form>
            <form method="POST" action="{{ route('sales.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
        @elseif ($order->status === 'ready_for_delivery')
            <form method="POST" action="{{ route('sales.orders.complete', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Mark Completed</button></form>
            <form method="POST" action="{{ route('sales.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
        @elseif ($order->status === 'completed')
            <span class="badge bg-dark align-self-center">Completed — ready for delivery (S-5) and invoicing (S-6)</span>
        @endif

        @if ($order->status === 'draft' || $order->status === 'pending_approval')
            <small class="text-muted align-self-center ms-2">Inventory will be checked at delivery (S-5), no stock movement yet.</small>
        @endif
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-truck me-1"></i>Deliveries</span>
        @if(in_array($order->status, ['approved','processing','ready_for_delivery','completed']))
            <a href="{{ route('sales.deliveries.create', ['order_id' => $order->id]) }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-plus-lg"></i> New Delivery</a>
        @endif
    </div>
    <div class="card-body p-0">
        @php
            $deliveries = \App\Models\SalesDelivery::withoutGlobalScopes()->where('order_id', $order->id)->with('warehouse')->orderByDesc('id')->get();
        @endphp
        @if($deliveries->isEmpty())
            <p class="text-muted text-center py-3 mb-0">No deliveries yet. @if(in_array($order->status, ['approved','processing','ready_for_delivery'])) Create one to fulfill this order. @endif</p>
        @else
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Delivery</th><th>Date</th><th>Status</th><th>Warehouse</th><th></th></tr></thead>
                    <tbody>
                        @foreach($deliveries as $d)
                            <tr>
                                <td><a href="{{ route('sales.deliveries.show', $d) }}">{{ $d->delivery_number }}</a></td>
                                <td>{{ $d->delivery_date->format('Y-m-d') }}</td>
                                <td><span class="badge bg-{{ ['draft'=>'secondary','confirmed'=>'success','delivered'=>'primary','cancelled'=>'dark'][$d->status] ?? 'secondary' }}">{{ ucfirst($d->status) }}</span></td>
                                <td>{{ $d->warehouse?->name ?? '—' }}</td>
                                <td class="text-end"><a href="{{ route('sales.deliveries.show', $d) }}" class="btn btn-sm btn-outline-primary rounded-pill">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @php
                $deliveryService = app(\App\Services\Sales\DeliveryService::class);
                $allLines = $order->lines;
                $fully = $deliveryService->isOrderFullyDelivered($order);
            @endphp
            @if($fully)
                <div class="card-footer text-success"><i class="bi bi-check-circle me-1"></i>Fully delivered — Ready for Invoice (S-6)</div>
            @endif
        @endif
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="text-muted"><i class="bi bi-box-seam me-1"></i>Inventory Integration (S-5)</h6>
        <p class="small text-muted mb-0">This order does <strong>not</strong> reduce stock. Availability can be checked via <code>/sales/items/{id}/availability</code> and delivery will handle stock movements. No journal posted until invoicing (S-6).</p>
    </div>
</div>

@php
    $invoiceService = app(\App\Services\Sales\SalesInvoiceService::class);
    $eligible = $invoiceService->isEligible($order);
    $remainingMap = $invoiceService->remainingForOrder($order);
    $invoices = $invoiceService->invoicesForOrder($order);
    $hasRemaining = collect($remainingMap)->sum(fn($r) => $r['max_invoicable']) > 0.00005;
@endphp
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt me-1"></i>Finance Invoices (S-6) — AR</span>
        @if($eligible && $hasRemaining)
            <a href="{{ route('sales.invoices.create', $order) }}" class="btn btn-sm btn-success rounded-pill"><i class="bi bi-plus-lg"></i> Create Invoice</a>
        @elseif(!$eligible)
            <span class="badge bg-secondary">Invoicing not eligible (status: {{ $order->status }})</span>
        @else
            <span class="badge bg-success">Fully invoiced</span>
        @endif
    </div>
    <div class="card-body p-0">
        @if($invoices->isEmpty())
            <p class="text-muted text-center py-3 mb-0">
                No invoices yet.
                @if($eligible && $hasRemaining) <a href="{{ route('sales.invoices.create', $order) }}" class="ms-1">Create one</a> from delivered quantities. @endif
                @if(!$eligible) Invoicing requires order Approved → Completed. @endif
            </p>
        @else
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Invoice</th><th>Status</th><th>Payable</th><th>Paid</th><th>Due</th><th>Delivery</th><th>Journal</th><th></th></tr></thead>
                    <tbody>
                    @foreach($invoices as $inv)
                        <tr>
                            <td><span class="fw-semibold">{{ $inv->invoice_number }}</span><br><small class="text-muted">{{ $inv->created_at->format('Y-m-d') }}</small></td>
                            <td><span class="badge bg-{{ ['unpaid'=>'warning','partial'=>'info','paid'=>'success','cancelled'=>'dark'][$inv->status] ?? 'secondary' }}">{{ ucfirst($inv->status) }}</span></td>
                            <td>{{ number_format($inv->payable_amount,2) }}</td>
                            <td>{{ number_format($inv->paid_amount,2) }}</td>
                            <td class="fw-semibold {{ $inv->due_amount > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($inv->due_amount,2) }}</td>
                            <td>
                                @if($inv->sales_delivery_id)
                                    @php $d = \App\Models\SalesDelivery::withoutGlobalScopes()->find($inv->sales_delivery_id); @endphp
                                    @if($d)<a href="{{ route('sales.deliveries.show',$d) }}">{{ $d->delivery_number }}</a>@else #{{ $inv->sales_delivery_id }} @endif
                                @else — @endif
                            </td>
                            <td>
                                @if($inv->journal_id)
                                    <a href="{{ route('finance.journals.show', $inv->journal_id) }}" class="badge bg-light text-dark border">Journal #{{ $inv->journal_id }}</a>
                                @else <span class="text-muted">draft</span> @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('finance.invoices.show', $inv) }}" class="btn btn-sm btn-outline-primary rounded-pill">Finance View</a>
                                @if($inv->status !== 'cancelled' && $inv->paid_amount == 0)
                                    <small class="text-muted d-block">Cancel via Finance</small>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">
                Posted to AR via <code>InvoiceService</code> (sale journal Dr Receivable / Cr Income). Payments via <code>PaymentService</code>. Check
                <a href="{{ route('finance.invoices.show', $invoices->first()) }}">Trial Balance / Income Statement</a> for posting.
            </div>
        @endif
        @if($invoices->isNotEmpty())
            @php $paidSum = $invoices->sum('paid_amount'); $dueSum = $invoices->sum('due_amount'); @endphp
            <div class="p-3 bg-light d-flex justify-content-between">
                <span class="small">Total invoiced: <strong>{{ number_format($invoices->sum('payable_amount'),2) }}</strong> • Paid: <span class="text-success">{{ number_format($paidSum,2) }}</span> • Due: <span class="text-danger">{{ number_format($dueSum,2) }}</span></span>
                <span class="small text-muted">Remaining invoicable: {{ number_format(collect($remainingMap)->sum(fn($r)=>$r['max_invoicable']),2) }} units</span>
            </div>
        @endif
    </div>
</div>
@endsection
