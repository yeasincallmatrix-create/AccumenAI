@extends('layouts.standalone')

@section('title', 'SR Orders — AccumenAI')
@section('page_title', 'SR Orders')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cart-check me-2"></i>এসআর অর্ডার (SR Orders)</h4>
</div>

<div class="filter-card card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('dealership.orders.index') }}" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">SR (sales_force_id)</label>
                <input type="number" name="sr" class="form-control" value="{{ $filters['sr'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status / অবস্থা</label>
                <select name="status" class="form-select">
                    <option value="">— All —</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From / হইতে</label>
                <x-tdate-input name="from" :value="$filters['from'] ?? null" />
            </div>
            <div class="col-md-2">
                <label class="form-label">To / পর্যন্ত</label>
                <x-tdate-input name="to" :value="$filters['to'] ?? null" />
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary btn-sm" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="admin-card card mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr><th>Order No</th><th>Customer</th><th>SR</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><code>{{ $order->order_no }}</code></td>
                        <td>{{ $order->customer_id }}</td>
                        <td>{{ $order->sales_force_id }}</td>
                        <td>{{ number_format($order->total, 2) }}</td>
                        <td><span class="badge bg-secondary">{{ $order->status }}</span></td>
                        <td>{{ $order->created_at }}</td>
                        <td><a href="{{ route('dealership.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">কোনো অর্ডার নেই (No orders yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $orders->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন অর্ডার (New Order)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.orders.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2"><label class="form-label">Customer ID</label><input type="number" name="customer_id" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">SR ID</label><input type="number" name="sales_force_id" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Channel</label>
                <select name="channel" class="form-select"><option value="general">general</option><option value="retail">retail</option><option value="wholesale">wholesale</option><option value="sub_dealer">sub_dealer</option></select>
            </div>
            <div class="col-md-2"><label class="form-label">Product ID</label><input type="number" name="items[0][product_id]" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Qty</label><input type="number" step="0.001" name="items[0][qty]" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Unit Price</label><input type="number" step="0.01" name="items[0][unit_price]" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Discount</label><input type="number" step="0.01" name="discount" class="form-control" value="0"></div>
            <div class="col-md-7"><label class="form-label">Remarks / মন্তব্য</label><input type="text" name="remarks" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success btn-sm" type="submit">Submit Order</button></div>
        </form>
    </div>
</div>
@endsection
