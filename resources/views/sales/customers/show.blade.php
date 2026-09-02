@extends('layouts.standalone')

@section('title', $customer->name . ' — AccumenAI')
@section('page_title', $customer->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">{{ $customer->name }}</h4>
        <span class="badge bg-{{ $customer->is_active ? 'success' : 'secondary' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('sales.customers.manage.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        <a href="{{ route('sales.customers.manage.edit', $customer) }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-pencil"></i> Edit</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Contact Information</h6>
                <p class="mb-1"><strong>Phone:</strong> {{ $customer->phone ?? '—' }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $customer->email ?? '—' }}</p>
                <p class="mb-1"><strong>Address:</strong> {{ $customer->address ?? '—' }}</p>
                <p class="mb-1"><strong>TIN:</strong> {{ $customer->tin ?? '—' }}</p>
            </div>
            <div class="col-md-6">
                <h6>Details</h6>
                <p class="mb-1"><strong>Type:</strong> {{ ucfirst($customer->type) }}</p>
                <p class="mb-1"><strong>Credit Limit:</strong> {{ $customer->credit_limit ? number_format($customer->credit_limit, 2) : '—' }}</p>
                <p class="mb-1"><strong>Group:</strong> {{ $customer->customerGroup?->name ?? '—' }}</p>
                <p class="mb-1"><strong>Billing Currency:</strong> {{ $customer->billingCurrency?->code ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>

@if ($quotations->count())
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Recent Quotations</h6></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Number</th><th>Date</th><th class="text-end">Total</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @foreach ($quotations as $q)
                    <tr>
                        <td>{{ $q->quotation_number }}</td>
                        <td>{{ $q->quotation_date->format('Y-m-d') }}</td>
                        <td class="text-end">{{ number_format($q->grand_total, 2) }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($q->status) }}</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('sales.quotations.show', $q) }}"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if ($orders->count())
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Recent Orders</h6></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Number</th><th>Date</th><th class="text-end">Total</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @foreach ($orders as $o)
                    <tr>
                        <td>{{ $o->order_number }}</td>
                        <td>{{ $o->order_date->format('Y-m-d') }}</td>
                        <td class="text-end">{{ number_format($o->grand_total, 2) }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($o->status) }}</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('sales.orders.show', $o) }}"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if ($invoices->count())
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Recent Invoices</h6></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Number</th><th>Date</th><th class="text-end">Total</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($invoices as $inv)
                    <tr>
                        <td>{{ $inv->invoice_number ?? $inv->id }}</td>
                        <td>{{ $inv->created_at->format('Y-m-d') }}</td>
                        <td class="text-end">{{ number_format($inv->grand_total ?? 0, 2) }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($inv->status ?? 'open') }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
