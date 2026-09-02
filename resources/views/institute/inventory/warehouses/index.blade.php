@extends('layouts.standalone')
@section('title', 'Warehouses — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>Warehouses</h4>
    <p>Manage storage locations. Code is unique per scope. Deleting a warehouse with stock is blocked.</p>
    <a href="{{ route('inventory.warehouses.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Warehouse</a>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('inventory.warehouses.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Name, code or location">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="active" @selected(request('status')==='active')>Active</option>
                    <option value="inactive" @selected(request('status')==='inactive')>Inactive</option>
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('inventory.warehouses.index') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

@if(($createMode ?? false) && (!isset($editWarehouse)))
<div class="card mb-3">
    <div class="card-body">
        <h6>New Warehouse</h6>
        <form method="POST" action="{{ route('inventory.warehouses.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="code" value="{{ old('code') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-control" name="location" value="{{ old('location') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="is_active">
                        <option value="1" @selected(old('is_active', 1))>Active</option>
                        <option value="0" @selected(old('is_active', 0)==0)>Inactive</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm mt-3 rounded-pill"><i class="bi bi-check-circle me-1"></i>Create</button>
        </form>
    </div>
</div>
@endif

@if(($createMode ?? false) && isset($editWarehouse))
<div class="card mb-3">
    <div class="card-body">
        <h6>Edit Warehouse: {{ $editWarehouse->name }}</h6>
        <form method="POST" action="{{ route('inventory.warehouses.update', $editWarehouse) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" value="{{ old('name', $editWarehouse->name) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="code" value="{{ old('code', $editWarehouse->code) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-control" name="location" value="{{ old('location', $editWarehouse->location) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="is_active">
                        <option value="1" @selected(old('is_active', $editWarehouse->is_active))>Active</option>
                        <option value="0" @selected(old('is_active', $editWarehouse->is_active)==0)>Inactive</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm mt-3 rounded-pill"><i class="bi bi-check-circle me-1"></i>Update</button>
        </form>
    </div>
</div>
@endif

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($warehouses as $wh)
                    <tr>
                        <td class="text-muted">{{ $warehouses->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('inventory.warehouses.show', $wh) }}" class="fw-semibold">{{ $wh->name }}</a></td>
                        <td>{{ $wh->code }}</td>
                        <td>{{ $wh->location ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $wh->is_active ? 'success' : 'secondary' }}">{{ $wh->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('inventory.warehouses.edit', $wh) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                            <form method="POST" action="{{ route('inventory.warehouses.destroy', $wh) }}" class="d-inline" data-ajax-delete="1" data-confirm="Delete this warehouse?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No warehouses found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($warehouses->hasPages())
        <div class="p-2 border-top">{{ $warehouses->links() }}</div>
    @endif
</div>
@endsection
