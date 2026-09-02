@extends('layouts.institute')

@section('title', 'Purchase Order ' . $order->order_number . ' — AccumenAI')

@php
    $colors = ['draft'=>'secondary','submitted'=>'warning','approved'=>'info','partially_received'=>'primary','fully_received'=>'success','cancelled'=>'dark','closed'=>'dark'];
    $u = auth()->user();
    $canUpdate = $u && method_exists($u, 'hasPermission') ? ($u->hasPermission('purchase.update') || $u->hasPermission('purchase.manage') || (method_exists($u,'isOwner') && $u->isOwner())) : true;
    $canManage = $u && method_exists($u, 'hasPermission') ? ($u->hasPermission('purchase.manage') || (method_exists($u,'isOwner') && $u->isOwner())) : true;
    if (!($u instanceof \App\Models\InstituteUser) && !method_exists($u,'hasPermission')) {
        $m = \App\Support\Workspace::membership();
        if ($m) {
            $canUpdate = $m->hasPermission('purchase.update') || $m->hasPermission('purchase.manage') || $m->hasPermission('purchase.view');
            $canManage = $m->hasPermission('purchase.manage');
        }
    }
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $order->order_number }} <span class="badge bg-{{ $colors[$order->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$order->status)) }}</span></h4>
    <div class="d-flex gap-2">
        <a href="{{ route('purchase.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        <a href="{{ route('purchase.orders.print', $order) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-printer"></i> Print</a>
        @if ($order->status === 'draft' && $canUpdate)
            <a href="{{ route('purchase.orders.edit', $order) }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-pencil"></i> Edit</a>
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
                <p class="mb-1 fw-semibold">{{ $order->supplier?->name ?? '—' }}</p>
                <p class="mb-1 text-muted small">{{ $order->supplier?->phone }} {{ $order->supplier?->email ? '• ' . $order->supplier?->email : '' }}</p>
                <p class="mb-1 text-muted small">{{ $order->supplier?->address }}</p>
                <hr>
                <p class="mb-1"><strong>Warehouse:</strong> <span class="text-muted">{{ $order->warehouse?->name ?? '—' }}</span></p>
                <p class="mb-1"><strong>Branch:</strong> <span class="text-muted">{{ $order->branch?->name ?? 'Institute-wide' }}</span></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Order Date:</strong> {{ $order->order_date?->format('Y-m-d') }}</p>
                <p class="mb-1"><strong>Expected Delivery:</strong> {{ $order->expected_delivery_date?->format('Y-m-d') ?? '—' }}</p>
                <p class="mb-1"><strong>Currency:</strong> {{ $order->currency?->code ?? '—' }}</p>
                <p class="mb-1"><strong>Reference:</strong> {{ $order->reference_number ?? '—' }}</p>
                <p class="mb-1"><strong>Warehouse:</strong> {{ $order->warehouse?->name ?? '—' }}</p>
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
                        <td class="text-end">{{ number_format($line->discount_amount, 2) }} @if(($line->discount_type ?? 'fixed') === 'percent' || ($line->discount_type ?? '') === 'percentage') <small class="text-muted">({{ $line->discount_rate ?? $line->discount_amount }}%)</small> @endif</td>
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
            @if ($canUpdate)
                <form method="POST" action="{{ route('purchase.orders.submit', $order) }}">@csrf<button class="btn btn-warning rounded-pill">Submit for Approval</button></form>
                <form method="POST" action="{{ route('purchase.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
            @endif
        @elseif ($order->status === 'submitted')
            @if ($canManage)
                <form method="POST" action="{{ route('purchase.orders.approve', $order) }}">@csrf<button class="btn btn-success rounded-pill">Approve</button></form>
                <form method="POST" action="{{ route('purchase.orders.reject', $order) }}">@csrf<button class="btn btn-danger rounded-pill">Reject</button></form>
            @endif
            @if ($canUpdate)
                <form method="POST" action="{{ route('purchase.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
            @endif
        @elseif ($order->status === 'approved')
            @if ($canUpdate)
                <form method="POST" action="{{ route('purchase.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
            @endif
            @if ($canManage)
                <form method="POST" action="{{ route('purchase.orders.close', $order) }}">@csrf<button class="btn btn-secondary rounded-pill">Close</button></form>
            @endif
        @elseif ($order->status === 'partially_received')
            @if ($canUpdate)
                <form method="POST" action="{{ route('purchase.orders.cancel', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel</button></form>
            @endif
            @if ($canManage)
                <form method="POST" action="{{ route('purchase.orders.close', $order) }}">@csrf<button class="btn btn-secondary rounded-pill">Close</button></form>
            @endif
        @elseif ($order->status === 'fully_received')
            @if ($canManage)
                <form method="POST" action="{{ route('purchase.orders.close', $order) }}">@csrf<button class="btn btn-dark rounded-pill">Close</button></form>
            @endif
        @elseif ($order->status === 'cancelled')
            <span class="badge bg-dark align-self-center">Cancelled</span>
        @elseif ($order->status === 'closed')
            <span class="badge bg-dark align-self-center">Closed</span>
        @endif

        @if (in_array($order->status, ['draft','submitted']))
            <small class="text-muted align-self-center ms-2">Inventory will be updated on goods receipt; no stock movement yet.</small>
        @endif
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="text-muted"><i class="bi bi-box-seam me-1"></i>Inventory Integration</h6>
        <p class="small text-muted mb-0">This purchase order does <strong>not</strong> increase stock until goods are received. Use Goods Receipt to handle stock movements. No journal posted until bill/invoice.</p>
    </div>
</div>

@php
    $canReceive = in_array($order->status, ['approved','partially_received']) && ($u && method_exists($u,'hasPermission') ? $u->hasPermission('goods_receipt.create') : true);
    $receipts = \App\Models\GoodsReceipt::where('purchase_order_id',$order->id)->where('institute_id',$order->institute_id)->with('warehouse')->orderByDesc('id')->get();
@endphp
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-box-arrow-in-down me-1"></i>Goods Receipts</span>
        @if($canReceive)
            <a href="{{ route('purchase.receipts.create',['purchase_order_id'=>$order->id]) }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-plus-lg"></i> New Receipt</a>
        @endif
    </div>
    <div class="card-body p-0">
        @if($receipts->isEmpty())
            <p class="text-muted text-center py-3 mb-0">No receipts yet. @if($canReceive) Create one to receive goods. @endif</p>
        @else
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>GRN</th><th>Date</th><th>Warehouse</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach($receipts as $gr)
                            <tr>
                                <td><a href="{{ route('purchase.receipts.show',$gr) }}" class="fw-semibold">{{ $gr->receipt_number }}</a><br><small class="text-muted">{{ $gr->created_at?->format('Y-m-d H:i') }}</small></td>
                                <td>{{ $gr->receipt_date?->format('Y-m-d') }}</td>
                                <td>{{ $gr->warehouse?->name ?? '—' }}</td>
                                <td><span class="badge bg-{{ ['draft'=>'secondary','confirmed'=>'success','cancelled'=>'dark'][$gr->status] ?? 'secondary' }}">{{ ucfirst($gr->status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('purchase.receipts.show',$gr) }}" class="btn btn-sm btn-outline-primary rounded-pill">View</a>
                                    <a href="{{ route('purchase.receipts.print',$gr) }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill">Print</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @php
        $allLines = $order->lines;
        $totalOrdered = $allLines->sum('quantity');
        $totalReceived = $allLines->sum('received_quantity');
        $remaining = max(0, $totalOrdered - $totalReceived);
    @endphp
    <div class="card-footer d-flex justify-content-between small">
        <span>Ordered: <strong>{{ number_format($totalOrdered,2) }}</strong> • Received: <strong class="text-success">{{ number_format($totalReceived,2) }}</strong> • Remaining: <strong class="text-primary">{{ number_format($remaining,2) }}</strong></span>
        <span class="text-muted">PO: {{ $order->status }}</span>
    </div>
</div>
@endsection
