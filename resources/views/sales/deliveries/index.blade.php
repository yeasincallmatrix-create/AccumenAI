@extends('layouts.institute')

@section('title', 'Deliveries — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-truck me-2"></i>Deliveries</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('sales.deliveries.create') }}"><i class="bi bi-plus-lg me-1"></i>New Delivery</a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Delivery or order number">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
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
                <a href="{{ route('sales.deliveries.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Delivery</th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deliveries as $d)
                    <tr>
                        <td class="fw-semibold">{{ $d->delivery_number }}</td>
                        <td>{{ $d->order?->order_number ?? '—' }}</td>
                        <td>{{ $d->customer?->name ?? '—' }}</td>
                        <td>{{ $d->delivery_date->format('Y-m-d') }}</td>
                        <td>
                            @php $colors = ['draft'=>'secondary','confirmed'=>'success','delivered'=>'primary','cancelled'=>'dark']; @endphp
                            <span class="badge bg-{{ $colors[$d->status] ?? 'secondary' }}">{{ ucfirst($d->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('sales.deliveries.show', $d) }}"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No deliveries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($deliveries->hasPages())
        <div class="card-footer">{{ $deliveries->links() }}</div>
    @endif
</div>
@endsection
