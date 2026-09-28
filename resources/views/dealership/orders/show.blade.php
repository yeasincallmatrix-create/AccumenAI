@extends('layouts.standalone')

@section('title', 'Order ' . $order->order_no . ' — AccumenAI')
@section('page_title', 'Order Detail')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-receipt me-2"></i>অর্ডার <code>{{ $order->order_no }}</code></h4>
    <span class="badge bg-secondary">{{ $order->status }}</span>
</div>

<div class="admin-card card mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Customer ID / কাস্টমার</dt><dd class="col-sm-3">{{ $order->customer_id }}</dd>
            <dt class="col-sm-3">SR ID / এসআর</dt><dd class="col-sm-3">{{ $order->sales_force_id }}</dd>
            <dt class="col-sm-3">Subtotal</dt><dd class="col-sm-3">{{ number_format($order->subtotal, 2) }}</dd>
            <dt class="col-sm-3">Discount</dt><dd class="col-sm-3">{{ number_format($order->discount, 2) }}</dd>
            <dt class="col-sm-3">Total</dt><dd class="col-sm-3"><strong>{{ number_format($order->total, 2) }}</strong></dd>
            <dt class="col-sm-3">Submitted</dt><dd class="col-sm-3">{{ $order->submitted_at ?? '—' }}</dd>
        </dl>
    </div>
</div>

<div class="admin-card card mb-3">
    <div class="card-header">Items / পণ্য</div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr><td>{{ $item->product_id }}</td><td>{{ $item->qty }}</td><td>{{ number_format($item->unit_price, 2) }}</td><td>{{ number_format($item->line_total, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card card mb-3">
    <div class="card-header">Approval Trail / অনুমোদন</div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>Action</th><th>Actor</th><th>Note</th><th>At</th></tr></thead>
            <tbody>
                @foreach($order->approvals as $audit)
                    <tr><td>{{ $audit->action }}</td><td>{{ $audit->actor_id ?? '—' }}</td><td>{{ $audit->note ?? '—' }}</td><td>{{ $audit->created_at }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($order->status === 'submitted')
<div class="d-flex gap-2">
    <form method="POST" action="{{ route('dealership.orders.approve', $order) }}">@csrf<button class="btn btn-success btn-sm" type="submit">Approve / অনুমোদন</button></form>
    <form method="POST" action="{{ route('dealership.orders.reject', $order) }}">@csrf<button class="btn btn-danger btn-sm" type="submit">Reject / বাতিল</button></form>
</div>
@endif
@endsection
