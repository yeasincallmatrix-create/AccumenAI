@extends('layouts.standalone')

@section('title', 'Parties — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Parties</h4>
    <p>Customers, suppliers and both — the counterparties behind receivables and payables. Duplicate phone numbers are blocked within the same scope and type.</p>
    <a href="{{ route('finance.parties.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Party</a>
</div>

<div class="filter-card mb-3">
    <div class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" wire:model.live.debounce.350ms="search" placeholder="Name, phone or email">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" wire:model.live="filters.type">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" wire:model.live="filters.status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="button" wire:click="resetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    @if (in_array('serial', $visibleColumns, true))<th class="text-muted">#</th>@endif
                    @if (in_array('name', $visibleColumns, true))<th>Party</th>@endif
                    @if (in_array('phone', $visibleColumns, true))<th>Phone</th>@endif
                    @if (in_array('email', $visibleColumns, true))<th>Email</th>@endif
                    @if (in_array('type', $visibleColumns, true))<th>Type</th>@endif
                    @if (in_array('status', $visibleColumns, true))<th>Status</th>@endif
                    @if (in_array('action', $visibleColumns, true))<th class="text-end">Actions</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($parties as $party)
                    <tr>
                        @if (in_array('serial', $visibleColumns, true))<td class="text-muted">{{ $parties->firstItem() + $loop->index }}</td>@endif
                        @if (in_array('name', $visibleColumns, true))<td>
                            <span class="fw-semibold">{{ $party->name }}</span>
                            @if ($party->branch)
                                <div class="text-muted small">{{ $party->branch->name }}</div>
                            @endif
                        </td>@endif
                        @if (in_array('phone', $visibleColumns, true))<td>{{ $party->phone ?? '—' }}</td>@endif
                        @if (in_array('email', $visibleColumns, true))<td>{{ $party->email ?? '—' }}</td>@endif
                        @if (in_array('type', $visibleColumns, true))<td><span class="badge text-bg-light border">{{ ucfirst($party->type) }}</span></td>@endif
                        @if (in_array('status', $visibleColumns, true))<td>
                            <span class="badge text-bg-{{ $party->is_active ? 'success' : 'secondary' }}">{{ $party->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>@endif
                        @if (in_array('action', $visibleColumns, true))<td class="text-end">
                            <a href="{{ route('finance.parties.edit', $party) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                            <form method="POST" action="{{ route('finance.parties.destroy', $party) }}" class="d-inline" data-ajax-delete="1" data-confirm="Move this party to trash?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>@endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No parties found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($parties->hasPages())
        <div class="p-2 border-top">{{ $parties->links('pagination::bootstrap-5') }}</div>
    @endif
</div>

@endsection
