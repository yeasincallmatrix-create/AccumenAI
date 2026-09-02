@extends('layouts.institute')

@section('title', 'Departments — HR')
@section('page_title', 'Departments')

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Departments <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Industry-neutral organizational units — per institute, optionally per branch, hierarchical.</p>
    </div>
    <a href="{{ route('hr.departments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-diagram-3 me-1"></i>Manage</a>
</div>

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('hr.departments.index') }}" class="filter-layout">
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
                <a href="{{ route('hr.departments.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>
</div>

@if ($canCreate)
<div class="admin-card p-3 mb-3">
    <h6 class="mb-3">New Department</h6>
    <form method="POST" action="{{ route('hr.departments.store') }}">
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
            <div class="col-md-2">
                <label class="form-label small">Branch</label>
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="">Institute-wide</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Parent</label>
                <select name="parent_department_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
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
                    <th>Branch</th>
                    <th>Parent</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($departments as $dept)
                    <tr>
                        <td class="text-muted">{{ $departments->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">{{ $dept->name }}</td>
                        <td><code>{{ $dept->code ?? '—' }}</code></td>
                        <td>{{ $dept->branch?->name ?? 'Institute-wide' }}</td>
                        <td>{{ $dept->parent?->name ?? '—' }}</td>
                        <td>{{ $dept->display_order }}</td>
                        <td><span class="badge {{ $dept->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $dept->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            @if ($canUpdate)
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDept{{ $dept->id }}"><i class="bi bi-pencil-square"></i></button>
                                <form method="POST" action="{{ route('hr.departments.toggle', $dept) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Toggle active">{{ $dept->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            @endif
                            @if ($canDelete)
                                <form method="POST" action="{{ route('hr.departments.destroy', $dept) }}" class="d-inline" onsubmit="return confirm('Delete department? It will be soft-deleted.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif

                            @if ($canUpdate)
                                <div class="modal fade" id="editDept{{ $dept->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('hr.departments.update', $dept) }}">
                                                @csrf @method('PUT')
                                                <div class="modal-header">
                                                    <h6 class="modal-title">Edit Department</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-2">
                                                        <label class="form-label small">Name *</label>
                                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $dept->name }}" required>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">Code</label>
                                                        <input type="text" name="code" class="form-control form-control-sm" value="{{ $dept->code }}">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">Parent</label>
                                                        <select name="parent_department_id" class="form-select form-select-sm">
                                                            <option value="">—</option>
                                                            @foreach ($parents as $parent)
                                                                @if ($parent->id !== $dept->id)
                                                                    <option value="{{ $parent->id }}" @selected($dept->parent_department_id == $parent->id)>{{ $parent->name }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small">Order</label>
                                                        <input type="number" name="display_order" class="form-control form-control-sm" value="{{ $dept->display_order }}">
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
                    <tr><td colspan="8" class="text-center text-muted py-4">No departments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($departments->hasPages())
        <div class="p-2 border-top">{{ $departments->links() }}</div>
    @endif
</div>

@endsection
