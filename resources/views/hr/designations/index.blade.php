@extends('layouts.institute')

@section('title', 'Designations — HR')
@section('page_title', 'Designations')

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Designations <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Job titles / positions — optionally tied to a department, ordered and active/inactive.</p>
    </div>
    <a href="{{ route('hr.designations.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-award me-1"></i>Manage</a>
</div>

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('hr.designations.index') }}" class="filter-layout">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or code">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select name="is_active" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('hr.designations.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>
</div>

@if ($canCreate)
<div class="admin-card p-3 mb-3">
    <h6 class="mb-3">New Designation</h6>
    <form method="POST" action="{{ route('hr.designations.store') }}">
        @csrf
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label small">Name *</label>
                <input type="text" name="name" class="form-control form-control-sm" required maxlength="120">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Code</label>
                <input type="text" name="code" class="form-control form-control-sm" maxlength="40" placeholder="Optional">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">— No department —</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Order</label>
                <input type="number" name="display_order" class="form-control form-control-sm" value="0" min="0" max="9999">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Create</button>
            </div>
        </div>
    </form>
</div>
@endif

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Department</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($designations as $des)
                    <tr>
                        <td class="text-muted">{{ $designations->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">{{ $des->name }}</td>
                        <td><code>{{ $des->code ?? '—' }}</code></td>
                        <td>{{ $des->department?->name ?? '—' }}</td>
                        <td>{{ $des->display_order }}</td>
                        <td><span class="badge {{ $des->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $des->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            @if ($canUpdate)
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDes{{ $des->id }}"><i class="bi bi-pencil-square"></i></button>
                                <form method="POST" action="{{ route('hr.designations.toggle', $des) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $des->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            @endif
                            @if ($canDelete)
                                <form method="POST" action="{{ route('hr.designations.destroy', $des) }}" class="d-inline" onsubmit="return confirm('Delete designation? It will be soft-deleted.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif

                            @if ($canUpdate)
                                <div class="modal fade" id="editDes{{ $des->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('hr.designations.update', $des) }}">
                                                @csrf @method('PUT')
                                                <div class="modal-header">
                                                    <h6 class="modal-title">Edit Designation</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-2">
                                                        <label class="form-label small">Name *</label>
                                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $des->name }}" required>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">Code</label>
                                                        <input type="text" name="code" class="form-control form-control-sm" value="{{ $des->code }}">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">Department</label>
                                                        <select name="department_id" class="form-select form-select-sm">
                                                            <option value="">—</option>
                                                            @foreach ($departments as $dept)
                                                                <option value="{{ $dept->id }}" @selected($des->department_id == $dept->id)>{{ $dept->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">Order</label>
                                                        <input type="number" name="display_order" class="form-control form-control-sm" value="{{ $des->display_order }}">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No designations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($designations->hasPages())
        <div class="p-2 border-top">{{ $designations->links() }}</div>
    @endif
</div>

@endsection
