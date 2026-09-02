@extends('layouts.standalone')

@section('title', 'Customers — AccumenAI')
@section('page_title', 'Customers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>Customers</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('sales.customers.manage.create') }}"><i class="bi bi-plus-lg me-1"></i>New Customer</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control form-control-sm" wire:model.live.debounce.350ms="search" placeholder="Name, phone or email">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select form-select-sm" wire:model.live="filters.status">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-5">
                <button class="btn btn-sm btn-primary rounded-pill" type="button" wire:click="resetFilters"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    @if (in_array('name', $visibleColumns, true))<th>Name</th>@endif
                    @if (in_array('phone', $visibleColumns, true))<th>Phone</th>@endif
                    @if (in_array('email', $visibleColumns, true))<th>Email</th>@endif
                    @if (in_array('status', $visibleColumns, true))<th>Status</th>@endif
                    @if (in_array('action', $visibleColumns, true))<th class="text-end">Actions</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        @if (in_array('name', $visibleColumns, true))<td class="fw-semibold">{{ $customer->name }}</td>@endif
                        @if (in_array('phone', $visibleColumns, true))<td>{{ $customer->phone ?? '—' }}</td>@endif
                        @if (in_array('email', $visibleColumns, true))<td>{{ $customer->email ?? '—' }}</td>@endif
                        @if (in_array('status', $visibleColumns, true))<td>
                            <span class="badge bg-{{ $customer->is_active ? 'success' : 'secondary' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>@endif
                        @if (in_array('action', $visibleColumns, true))<td class="text-end">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('sales.customers.manage.show', $customer) }}"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('sales.customers.manage.edit', $customer) }}"><i class="bi bi-pencil"></i></a>
                        </td>@endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($customers->hasPages())
        <div class="card-footer">{{ $customers->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
