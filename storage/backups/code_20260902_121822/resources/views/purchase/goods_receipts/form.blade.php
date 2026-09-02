@extends('layouts.institute')
@section('title','New Goods Receipt — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-box-arrow-in-down me-1"></i>New Goods Receipt</h4>
    <a href="{{ route('purchase.receipts.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('purchase.receipts.create') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Purchase Order <span class="text-danger">*</span></label>
                <select name="purchase_order_id" class="form-select" onchange="this.form.submit()">
                    <option value="">— Select Approved PO —</option>
                    @foreach($pos as $po)
                        <option value="{{ $po->id }}" @selected(($purchaseOrder?->id)==$po->id)>{{ $po->order_number }} — {{ $po->supplier?->name ?? 'Supplier #'.$po->supplier_id }} ({{ $po->status }}) — {{ $po->order_date?->format('Y-m-d') }}</option>
                    @endforeach
                </select>
                <small class="text-muted">Only Approved / Partially Received POs are listed.</small>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary rounded-pill">Load PO</button></div>
        </form>
    </div>
</div>

@if($purchaseOrder)
@php
    $canReceive = in_array($purchaseOrder->status, ['approved','partially_received']);
@endphp
<form method="POST" action="{{ route('purchase.receipts.store') }}">
    @csrf
    <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between"><span>PO: {{ $purchaseOrder->order_number }} — {{ ucfirst($purchaseOrder->status) }}</span><span class="small text-muted">{{ $purchaseOrder->order_date?->format('Y-m-d') }} • {{ $purchaseOrder->currency?->code }}</span></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6"><h6>Supplier</h6><p class="mb-1 fw-semibold">{{ $purchaseOrder->supplier?->name }}</p><p class="mb-1 small text-muted">{{ $purchaseOrder->supplier?->phone }}</p></div>
                <div class="col-md-6 text-md-end"><p class="mb-1"><strong>Grand Total:</strong> {{ number_format($purchaseOrder->grand_total,2) }}</p><p class="mb-1"><strong>Status:</strong> {{ ucfirst(str_replace('_',' ',$purchaseOrder->status)) }}</p></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                <select name="warehouse_id" class="form-select" required>
                    <option value="">— Select Warehouse —</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" @selected(old('warehouse_id', $purchaseOrder->warehouse_id)==$wh->id)>{{ $wh->name }} — {{ $wh->code }} @if($wh->branch) ({{ $wh->branch->name }}) @endif</option>
                    @endforeach
                </select>
                <small class="text-muted">Correct location must be selected; branch-scoped.</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Receipt Date</label>
                <input type="date" name="receipt_date" value="{{ old('receipt_date', now()->toDateString()) }}" class="form-control">
            </div>
            <div class="col-md-5">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="form-control" placeholder="Optional notes / challan no">
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><span>Lines — Ordered vs Remaining vs Receiving</span><span class="small text-muted">Partial receiving allowed; over-receiving blocked</span></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>#</th><th>Description</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Remaining</th><th style="width:130px">Receiving Qty <span class="text-danger">*</span></th><th style="width:90px">Rejected</th><th style="width:110px">Unit Cost</th></tr></thead>
                <tbody>
                    @foreach($poLines as $idx=>$line)
                        @php
                            $ordered = (float) $line->quantity;
                            $received = (float) $line->received_quantity;
                            $remaining = max(0, $ordered - $received);
                            $oldQty = old("lines.$idx.received_quantity", $remaining > 0 ? $remaining : 0);
                        @endphp
                        <tr>
                            <td>{{ $idx+1 }}</td>
                            <td>{{ $line->description }} @if($line->inventoryItem) <small class="text-muted">({{ $line->inventoryItem->sku }})</small>@endif<br><small class="text-muted">PO Line #{{ $line->id }}</small></td>
                            <td class="text-end">{{ number_format($ordered,2) }}</td>
                            <td class="text-end text-success">{{ number_format($received,2) }}</td>
                            <td class="text-end fw-bold {{ $remaining>0.0001 ? 'text-primary' : 'text-muted' }}">{{ number_format($remaining,2) }}</td>
                            <td>
                                <input type="hidden" name="lines[{{ $idx }}][purchase_order_line_id]" value="{{ $line->id }}">
                                <input type="hidden" name="lines[{{ $idx }}][inventory_item_id]" value="{{ $line->inventory_item_id }}">
                                <input type="number" step="0.0001" min="0" max="{{ $remaining }}" name="lines[{{ $idx }}][received_quantity]" value="{{ $remaining>0 ? $oldQty : 0 }}" class="form-control form-control-sm" placeholder="0" {{ $remaining<=0.0001 ? 'disabled' : '' }}>
                            </td>
                            <td><input type="number" step="0.0001" min="0" name="lines[{{ $idx }}][rejected_quantity]" value="{{ old("lines.$idx.rejected_quantity", 0) }}" class="form-control form-control-sm" placeholder="0" {{ $remaining<=0.0001 ? 'disabled' : '' }}></td>
                            <td><input type="number" step="0.0001" min="0" name="lines[{{ $idx }}][unit_cost]" value="{{ old("lines.$idx.unit_cost", $line->unit_price) }}" class="form-control form-control-sm"></td>
                        </tr>
                        <tr class="table-light">
                            <td></td>
                            <td colspan="7">
                                <div class="row g-2 small">
                                    <div class="col-md-2"><input type="text" name="lines[{{ $idx }}][batch_number]" value="{{ old("lines.$idx.batch_number") }}" placeholder="Batch / Lot No" class="form-control form-control-sm" {{ $remaining<=0.0001 ? 'disabled' : '' }}></div>
                                    <div class="col-md-2"><input type="text" name="lines[{{ $idx }}][lot_number]" value="{{ old("lines.$idx.lot_number") }}" placeholder="Lot No" class="form-control form-control-sm" {{ $remaining<=0.0001 ? 'disabled' : '' }}></div>
                                    <div class="col-md-2"><input type="date" name="lines[{{ $idx }}][manufacture_date]" value="{{ old("lines.$idx.manufacture_date") }}" class="form-control form-control-sm" {{ $remaining<=0.0001 ? 'disabled' : '' }} title="Mfg Date"></div>
                                    <div class="col-md-2"><input type="date" name="lines[{{ $idx }}][expiry_date]" value="{{ old("lines.$idx.expiry_date") }}" class="form-control form-control-sm" {{ $remaining<=0.0001 ? 'disabled' : '' }} title="Expiry Date"></div>
                                    <div class="col-md-2"><input type="text" name="lines[{{ $idx }}][serial_numbers]" value="{{ old("lines.$idx.serial_numbers") }}" placeholder="Serials comma-sep" class="form-control form-control-sm" {{ $remaining<=0.0001 ? 'disabled' : '' }}></div>
                                    <div class="col-md-2">
                                        <select name="lines[{{ $idx }}][received_condition]" class="form-select form-select-sm" {{ $remaining<=0.0001 ? 'disabled' : '' }}>
                                            <option value="good" selected>Good</option>
                                            <option value="damaged" @selected(old("lines.$idx.received_condition")=='damaged')>Damaged</option>
                                            <option value="expired" @selected(old("lines.$idx.received_condition")=='expired')>Expired</option>
                                            <option value="quarantine" @selected(old("lines.$idx.received_condition")=='quarantine')>Quarantine</option>
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted">Batch/Serial/Expiry captured only if Inventory supports it (medicine/batch-tracked). Leave blank otherwise.</small>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <small class="text-muted">PO: 100 → GRN 1:40 → Remaining 60 → GRN 2:60 → Fully Received. Over-receiving blocked.</small>
            @if($canReceive)
                <button class="btn btn-success rounded-pill"><i class="bi bi-check-lg"></i> Create Draft Receipt</button>
            @else
                <span class="badge bg-secondary">PO not approved for receiving</span>
            @endif
        </div>
    </div>
</form>
@else
    <div class="alert alert-info">Select an <strong>Approved</strong> Purchase Order to create a Goods Receipt. Only Approved / Partially Received POs are eligible.</div>
@endif
@endsection
