@extends('layouts.institute')

@section('title','Create Invoice — '.$order->order_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Create Invoice from Order {{ $order->order_number }} <small class="text-muted">{{ $order->customer?->name }} • {{ $order->currency?->code }}</small></h4>
    <a href="{{ route('sales.orders.show',$order) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back to Order</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="alert alert-info small">
    Invoices are posted via <strong>Finance InvoiceService</strong> (AR ledger, sale journal). For stockable products, quantity cannot exceed <strong>delivered − already invoiced</strong>. For services, quantity cannot exceed <strong>ordered − invoiced</strong>. Historical values (unit price, discount, tax) are frozen from the order — changing the catalogue later does not alter this invoice.
</div>

<div class="card mb-3">
    <div class="card-body">
        <p class="mb-1">Order Grand Total: <strong>{{ number_format($order->grand_total,2) }} {{ $order->currency?->code }}</strong> • Subtotal {{ number_format($order->subtotal,2) }} • Discount {{ number_format($order->discount_amount,2) }} • Tax {{ number_format($order->tax_amount,2) }}</p>
        <p class="mb-0 small text-muted">Status: {{ $order->status }} • Eligible: {{ app(\App\Services\Sales\SalesInvoiceService::class)->isEligible($order) ? 'YES' : 'NO' }}</p>
    </div>
</div>

<form method="POST" action="{{ route('sales.invoices.store',$order) }}">
    @csrf

    @if($deliveries->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-truck me-1"></i>Link Delivery (optional — limits invoicing to a specific confirmed delivery)</div>
            <div class="card-body">
                <select name="delivery_id" class="form-select">
                    <option value="">— Invoice without delivery filter (uses all delivered quantities) —</option>
                    @foreach($deliveries as $d)
                        <option value="{{ $d->id }}" {{ old('delivery_id')==$d->id?'selected':'' }}>{{ $d->delivery_number }} — {{ $d->delivery_date->format('Y-m-d') }} — {{ ucfirst($d->status) }} @if($d->warehouse) ({{ $d->warehouse->name }}) @endif</option>
                    @endforeach
                </select>
                <small class="text-muted">If a delivery is selected, only quantities from that delivery are considered. Otherwise, invoicing uses aggregate delivered quantities.</small>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-list-check me-1"></i>Lines — enter invoicing quantity (0 = skip line)</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th>#</th><th>Description</th><th class="text-end">Ordered</th><th class="text-end">Delivered</th><th class="text-end">Already Invoiced</th><th class="text-end">Max Invoicable</th><th style="width:140px">Invoice Qty</th><th class="text-end">Line Total (historical)</th></tr>
                </thead>
                <tbody>
                @foreach($order->lines as $line)
                    @php $r = $remaining[$line->id] ?? null; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $line->description }} @if($line->inventoryItem) <small class="text-muted">({{ $line->inventoryItem->sku }} • {{ $line->inventoryItem->item_type }})</small> @endif @if($r && $r['stockable']) <span class="badge bg-warning text-dark">Stockable</span> @else <span class="badge bg-info">Service</span> @endif</td>
                        <td class="text-end">{{ number_format($r['ordered'] ?? $line->quantity,2) }}</td>
                        <td class="text-end">{{ number_format($r['delivered'] ?? 0,2) }}</td>
                        <td class="text-end">{{ number_format($r['invoiced'] ?? 0,2) }}</td>
                        <td class="text-end fw-semibold {{ ($r['max_invoicable'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">{{ number_format($r['max_invoicable'] ?? 0,2) }}</td>
                        <td>
                            <input type="number" step="0.0001" min="0" max="{{ $r['max_invoicable'] ?? 0 }}" name="lines[{{ $line->id }}]" value="{{ old('lines.'.$line->id, $r['max_invoicable'] ?? 0) }}" class="form-control form-control-sm text-end" {{ ($r['max_invoicable'] ?? 0) <=0.00005 ? 'disabled' : '' }}>
                        </td>
                        <td class="text-end">{{ number_format($line->line_total,2) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light"><th colspan="7" class="text-end">Grand Total (order)</th><th class="text-end">{{ number_format($order->grand_total,2) }}</th></tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer small text-muted">
            For stockable items, Max = Delivered − Invoiced. For services / manual lines, Max = Ordered − Invoiced. Invoice amounts are prorated from historical line values; header discount is allocated proportionally.
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label">Note (optional, stored in invoice_meta)</label>
            <input type="text" name="note" value="{{ old('note') }}" class="form-control" maxlength="1000" placeholder="e.g. Invoice for delivery DEL-...">
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success rounded-pill"><i class="bi bi-receipt"></i> Create Finance Invoice (AR)</button>
        <a href="{{ route('sales.orders.show',$order) }}" class="btn btn-outline-secondary rounded-pill">Cancel</a>
    </div>
    <p class="small text-muted mt-2">Posting respects <code>invoice_auto_post</code> setting and accounting period closure (via <code>JournalPostingService</code>). Draft invoices must be posted in Finance.</p>
</form>

@if($invoices->isNotEmpty())
    <div class="card mt-4">
        <div class="card-header">Previous Invoices for this Order</div>
        <ul class="list-group list-group-flush">
            @foreach($invoices as $inv)
                <li class="list-group-item d-flex justify-content-between">
                    <span>{{ $inv->invoice_number }} • {{ ucfirst($inv->status) }} • {{ number_format($inv->payable_amount,2) }} payable • Due {{ number_format($inv->due_amount,2) }}</span>
                    <a href="{{ route('finance.invoices.show',$inv) }}" class="btn btn-sm btn-outline-primary">View</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
