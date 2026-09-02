@extends('layouts.institute')
@section('title','GRN '.$receipt->receipt_number.' — Print')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Goods Receipt Note — {{ $receipt->receipt_number }}</h4>
    <button class="btn btn-sm btn-primary rounded-pill d-print-none" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>
<div class="card">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-6"><strong>Institute:</strong> {{ $receipt->institute?->name }}<br><strong>Branch:</strong> {{ $receipt->branch?->name ?? 'Institute-wide' }}<br><strong>PO:</strong> {{ $receipt->purchaseOrder?->order_number }} ({{ $receipt->purchaseOrder?->status }})<br><strong>Warehouse:</strong> {{ $receipt->warehouse?->name }}</div>
            <div class="col-6 text-end"><strong>Receipt Date:</strong> {{ $receipt->receipt_date?->format('Y-m-d') }}<br><strong>Status:</strong> {{ ucfirst($receipt->status) }}<br><strong>Supplier:</strong> {{ $receipt->supplier?->name }}<br><strong>GRN Date:</strong> {{ $receipt->created_at?->format('Y-m-d H:i') }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead><tr><th>#</th><th>Item</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Rejected</th><th class="text-end">Unit Cost</th><th>Batch / Lot / Expiry</th></tr></thead>
                <tbody>
                    @foreach($receipt->items as $idx=>$it)
                        <tr>
                            <td>{{ $idx+1 }}</td>
                            <td>{{ $it->description ?? $it->inventoryItem?->name }}<br><small class="text-muted">{{ $it->inventoryItem?->sku }}</small></td>
                            <td class="text-end">{{ number_format($it->ordered_quantity,2) }}</td>
                            <td class="text-end">{{ number_format($it->received_quantity,2) }}</td>
                            <td class="text-end">{{ number_format($it->rejected_quantity,2) }}</td>
                            <td class="text-end">{{ number_format($it->unit_cost,2) }}</td>
                            <td>@if($it->batch_number)Batch: {{ $it->batch_number }}<br>@endif @if($it->lot_number)Lot: {{ $it->lot_number }}<br>@endif @if($it->expiry_date)Exp: {{ $it->expiry_date }}<br>@endif @if($it->manufacture_date)Mfg: {{ $it->manufacture_date }}@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4 d-flex justify-content-between small text-muted">
            <span>Created by #{{ $receipt->created_by }} • Confirmed by #{{ $receipt->confirmed_by ?? '—' }} • {{ $receipt->notes }}</span>
            <span>{{ now()->format('Y-m-d H:i') }} • AccumenAI</span>
        </div>
        <div class="mt-4 d-flex justify-content-between d-print-flex">
            <div class="text-center"><div style="border-top:1px solid #000; width:180px; margin-top:40px; padding-top:6px;">Receiver Signature</div></div>
            <div class="text-center"><div style="border-top:1px solid #000; width:180px; margin-top:40px; padding-top:6px;">Supplier Signature</div></div>
            <div class="text-center"><div style="border-top:1px solid #000; width:180px; margin-top:40px; padding-top:6px;">Approver</div></div>
        </div>
    </div>
</div>
@endsection
