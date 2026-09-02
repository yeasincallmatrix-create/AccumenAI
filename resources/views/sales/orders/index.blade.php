@extends('layouts.institute')

@section('title', 'Sales Orders — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bag-check me-2"></i>Sales Orders</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm rounded-pill" href="{{ route('sales.quotations.index') }}"><i class="bi bi-file-earmark-text me-1"></i>Quotations</a>
        <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('sales.orders.create') }}"><i class="bi bi-plus-lg me-1"></i>New Order</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Order / Quotation / Customer">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('sales.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Quotation</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Expected Delivery</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $o)
                    <tr>
                        <td class="fw-semibold">{{ $o->order_number }}</td>
                        <td>{{ $o->quotation?->quotation_number ?? '—' }}</td>
                        <td>{{ $o->customer?->name ?? '—' }}</td>
                        <td>{{ $o->order_date->format('Y-m-d') }}</td>
                        <td>{{ $o->expected_delivery_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-end">{{ number_format($o->grand_total, 2) }} {{ $o->currency?->code }}</td>
                        <td>
                            @php $colors = ['draft'=>'secondary','pending_approval'=>'warning','approved'=>'info','rejected'=>'danger','processing'=>'primary','ready_for_delivery'=>'success','completed'=>'dark','cancelled'=>'dark']; @endphp
                            <span class="badge bg-{{ $colors[$o->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$o->status)) }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('sales.orders.show', $o) }}"><i class="bi bi-eye"></i></a>
                            @if (in_array($o->status, ['draft','rejected']))
                                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('sales.orders.edit', $o) }}"><i class="bi bi-pencil"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())
        <div class="card-footer">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
