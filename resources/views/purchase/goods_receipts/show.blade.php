@extends('layouts.institute')
@section('title','Goods Receipt '.$receipt->receipt_number.' — AccumenAI')
@php
    $colors=['draft'=>'secondary','confirmed'=>'success','cancelled'=>'dark','reversed'=>'warning'];
    $u=auth()->user();
    $canConfirm=$u && method_exists($u,'hasPermission') ? $u->hasPermission('goods_receipt.confirm') : true;
    $canCancel=$u && method_exists($u,'hasPermission') ? $u->hasPermission('goods_receipt.cancel') : true;
    $canReverse=$u && method_exists($u,'hasPermission') ? ($u->hasPermission('goods_receipt.reverse') || $u->hasPermission('goods_receipt.confirm')) : true;
@endphp
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $receipt->receipt_number }} <span class="badge bg-{{ $colors[$receipt->status] ?? 'secondary' }}">{{ ucfirst($receipt->status) }}</span> @if($receipt->reversed_at)<span class="badge bg-warning">Reversed</span>@endif</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('purchase.receipts.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        <a href="{{ route('purchase.receipts.print',$receipt) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-printer"></i> Print</a>
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Purchase Order</h6>
                <p class="mb-1 fw-semibold"><a href="{{ route('purchase.orders.show',$receipt->purchaseOrder) }}">{{ $receipt->purchaseOrder?->order_number }}</a> <small class="text-muted">({{ $receipt->purchaseOrder?->status }})</small></p>
                <p class="mb-1"><strong>Supplier:</strong> {{ $receipt->supplier?->name }}<br><small class="text-muted">{{ $receipt->supplier?->phone }}</small></p>
                <p class="mb-1"><strong>Warehouse:</strong> {{ $receipt->warehouse?->name ?? '—' }} @if($receipt->warehouse?->code) <small class="text-muted">({{ $receipt->warehouse?->code }})</small>@endif</p>
                <p class="mb-1"><strong>Branch:</strong> {{ $receipt->branch?->name ?? 'Institute-wide' }}</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Receipt Date:</strong> {{ $receipt->receipt_date?->format('Y-m-d') }}</p>
                <p class="mb-1"><strong>Created:</strong> {{ $receipt->created_at?->format('Y-m-d H:i') }}</p>
                @if($receipt->confirmed_at)<p class="mb-1 text-success"><strong>Confirmed:</strong> {{ $receipt->confirmed_at->format('Y-m-d H:i') }} by #{{ $receipt->confirmed_by }}</p>@endif
                @if($receipt->cancelled_at)<p class="mb-1 text-danger"><strong>Cancelled:</strong> {{ $receipt->cancelled_at->format('Y-m-d H:i') }}<br><small>{{ $receipt->cancellation_reason }}</small></p>@endif
                @if($receipt->reversed_at)<p class="mb-1 text-warning"><strong>Reversed:</strong> {{ $receipt->reversed_at->format('Y-m-d H:i') }} by #{{ $receipt->reversed_by }}<br><small>{{ $receipt->reversal_reason }}</small></p>@endif
            </div>
        </div>
        @if($receipt->notes)<div class="mt-3"><strong>Notes:</strong><p class="text-muted">{{ $receipt->notes }}</p></div>@endif
    </div>
</div>

<div class="card mb-4">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>#</th><th>Description</th><th class="text-end">Ordered</th><th class="text-end">Prev Received</th><th class="text-end">Received</th><th class="text-end">Rejected</th><th class="text-end">Unit Cost</th><th>Batch / Expiry</th><th>Condition</th></tr></thead>
            <tbody>
                @foreach($receipt->items as $idx=>$it)
                    <tr>
                        <td>{{ $idx+1 }}</td>
                        <td>{{ $it->description ?? $it->inventoryItem?->name ?? '—' }}<br><small class="text-muted">PO Line #{{ $it->purchase_order_line_id }}</small>@if($it->inventoryItem) <small class="text-muted">({{ $it->inventoryItem->sku }})</small>@endif</td>
                        <td class="text-end">{{ number_format($it->ordered_quantity,2) }}</td>
                        <td class="text-end">{{ number_format($it->previously_received_quantity,2) }}</td>
                        <td class="text-end fw-semibold text-success">{{ number_format($it->received_quantity,2) }}</td>
                        <td class="text-end text-danger">{{ number_format($it->rejected_quantity,2) }}</td>
                        <td class="text-end">{{ number_format($it->unit_cost,2) }}</td>
                        <td>
                            @if($it->batch_number)<span class="badge bg-light text-dark border">Batch: {{ $it->batch_number }}</span><br>@endif
                            @if($it->lot_number)<small class="text-muted">Lot: {{ $it->lot_number }}</small><br>@endif
                            @if($it->expiry_date)<small class="text-muted">Exp: {{ \Carbon\Carbon::parse($it->expiry_date)->format('Y-m-d') }}</small><br>@endif
                            @if($it->manufacture_date)<small class="text-muted">Mfg: {{ \Carbon\Carbon::parse($it->manufacture_date)->format('Y-m-d') }}</small><br>@endif
                            @if($it->serial_numbers)<small class="text-muted">SN: {{ is_string($it->serial_numbers)? $it->serial_numbers : json_encode($it->serial_numbers) }}</small>@endif
                            @if(!$it->batch_number && !$it->expiry_date && !$it->serial_numbers)<span class="text-muted">—</span>@endif
                        </td>
                        <td><span class="badge bg-{{ ['good'=>'success','damaged'=>'danger','expired'=>'warning','quarantine'=>'info'][$it->received_condition] ?? 'secondary' }}">{{ ucfirst($it->received_condition ?? 'good') }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-2">
        @if($receipt->isDraft() && $canConfirm)
            <form method="POST" action="{{ route('purchase.receipts.confirm',$receipt) }}">@csrf<button class="btn btn-success rounded-pill" onclick="return confirm('Confirm receipt and increase stock?')"><i class="bi bi-check-circle"></i> Confirm & Post to Stock</button></form>
            <form method="POST" action="{{ route('purchase.receipts.cancel',$receipt) }}">@csrf<button class="btn btn-dark rounded-pill">Cancel Draft</button></form>
            <small class="text-muted align-self-center ms-2">Confirming will increase stock via Inventory service (preserves movement history, idempotent).</small>
        @elseif($receipt->isConfirmed() && $canReverse)
            @if(!$receipt->reversed_at)
                <form method="POST" action="{{ route('purchase.receipts.reverse',$receipt) }}" class="d-flex gap-2 align-items-center">
                    @csrf
                    <input type="text" name="reversal_reason" placeholder="Reversal reason" class="form-control form-control-sm" style="width:220px" required>
                    <button class="btn btn-warning rounded-pill" onclick="return confirm('Reverse this confirmed receipt? Stock will be reversed via inventory return. Cannot be undone.')">Reverse Receipt</button>
                </form>
                <small class="text-muted align-self-center">Reversal is explicit inventory reversal (never destructive edit).</small>
            @else
                <span class="badge bg-warning">Already reversed</span>
            @endif
        @elseif($receipt->isCancelled())
            <span class="badge bg-dark">Cancelled — no further actions</span>
        @else
            <span class="badge bg-secondary">No actions — {{ ucfirst($receipt->status) }}</span>
        @endif
    </div>
</div>

@php
    // Remaining quantity display for parent PO
    $po = $receipt->purchaseOrder->load('lines');
@endphp
<div class="card mb-4">
    <div class="card-header">PO Remaining Quantities (for context)</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>PO Line</th><th class="text-end">Ordered</th><th class="text-end">Received (total)</th><th class="text-end">Remaining</th></tr></thead>
            <tbody>
                @foreach($po->lines as $pl)
                    @php
                        $ordered=(float)$pl->quantity;
                        $received=(float)$pl->received_quantity;
                        $remaining=max(0,$ordered-$received);
                    @endphp
                    <tr>
                        <td>{{ $pl->description }} <small class="text-muted">#{{ $pl->id }}</small></td>
                        <td class="text-end">{{ number_format($ordered,2) }}</td>
                        <td class="text-end text-success">{{ number_format($received,2) }}</td>
                        <td class="text-end fw-bold {{ $remaining>0.0001 ? 'text-primary' : 'text-muted' }}">{{ number_format($remaining,2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">PO Status: <strong>{{ $po->status }}</strong> — automatically updated to partially_received / fully_received on confirm, and reverted on reversal.</div>
</div>
@endsection
