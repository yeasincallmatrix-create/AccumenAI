@extends('layouts.admin')

@section('title', 'Industry Sub-Categories — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active">Industry Sub-Categories</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Industry Sub-Categories</h4>
        <p class="page-header-desc">Sub-category taxonomy driving Layer 3 module resolution (mandatory / default / optional modules per tenant).</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('admin.industry-subcategories.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Add Sub-Category
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger py-2">
        @foreach ($errors->all() as $error)
            <div class="small">{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-funnel"></i> Filters
        </div>
        <div class="toolbar-actions">
            <form method="GET" action="{{ route('admin.industry-subcategories.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
                <select name="industry" class="form-select form-select-sm" style="width:auto;min-width:170px">
                    <option value="">All Industries</option>
                    @foreach ($industries as $key)
                        <option value="{{ $key }}" {{ request('industry') === $key ? 'selected' : '' }}>{{ $key }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" class="form-control form-control-sm" style="width:auto;min-width:180px" placeholder="Search name…" value="{{ request('search') }}">
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i> Filter</button>
                @if (request('industry') || request('search'))
                    <a href="{{ route('admin.industry-subcategories.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                @endif
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Industry</th>
                    <th>Sub-Category</th>
                    <th class="text-center" style="width:110px">Modules</th>
                    <th class="text-center" style="width:90px">Order</th>
                    <th class="text-center" style="width:90px">Status</th>
                    <th class="text-end" style="width:200px">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subcategories as $row)
                    <tr>
                        <td class="text-muted">{{ $row->id }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $row->industry_key }}</span></td>
                        <td>
                            <span class="fw-semibold">{{ $row->name }}</span>
                            <br><small class="text-muted"><code>{{ $row->subcategory_key }}</code></small>
                            @if ($row->description)
                                <br><small class="text-muted">{{ $row->description }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info-subtle text-info">{{ $moduleCounts[$row->id] ?? 0 }}</span>
                        </td>
                        <td class="text-center">{{ $row->sort_order }}</td>
                        <td class="text-center">
                            @if ($row->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.industry-subcategories.show', $row->id) }}" class="btn btn-outline-primary btn-sm" title="View"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.industry-subcategories.edit', $row->id) }}" class="btn btn-outline-secondary btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.industry-subcategories.destroy', $row->id) }}" class="d-inline" onsubmit="return confirm('Delete sub-category {{ $row->name }} and ALL its module mappings? This affects Layer 3 defaults for matching tenants.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No sub-categories found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-center">
    {{ $subcategories->links() }}
</div>
@endsection
