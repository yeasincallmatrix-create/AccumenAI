@extends('layouts.admin')

@section('title', ($subcategory->name ?? '') . ' — Sub-Category — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.industry-subcategories.index') }}" class="text-decoration-none">Sub-Categories</a></li>
        <li class="breadcrumb-item active">{{ $subcategory->name }}</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $subcategory->name }}</h4>
        <p class="page-header-desc">
            Industry: <span class="badge bg-light text-dark border">{{ $subcategory->industry_key }}</span>
            — Key: <code>{{ $subcategory->subcategory_key }}</code>
            @if ($subcategory->is_active)
                <span class="badge bg-success ms-1">Active</span>
            @else
                <span class="badge bg-secondary ms-1">Inactive</span>
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.industry-subcategories.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <a class="btn btn-primary btn-sm" href="{{ route('admin.industry-subcategories.edit', $subcategory->id) }}">
            <i class="bi bi-pencil"></i> Edit
        </a>
    </div>
</div>

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
            <i class="bi bi-puzzle-fill"></i> Default Modules — {{ $modules->count() }} total
            <span class="text-muted ms-2">Layer 3: mandatory + default are base-enabled for matching tenants; optional requires tenant override.</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Module</th>
                    <th style="width:38%">Key</th>
                    <th class="text-center" style="width:130px">Category</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($modules as $m)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $moduleNames[$m->module_key] ?? '—' }}</td>
                        <td><code>{{ $m->module_key }}</code></td>
                        <td class="text-center">
                            @if ($m->category === 'mandatory')
                                <span class="badge bg-danger-subtle text-danger">mandatory</span>
                            @elseif ($m->category === 'default')
                                <span class="badge bg-success-subtle text-success">default</span>
                            @else
                                <span class="badge bg-secondary">optional</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No module mappings — add them via the module registry mapping seeder or future UI.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card p-3">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-info-circle text-primary fs-5"></i>
        <div>
            <div class="fw-semibold">Sub-category description</div>
            <small class="text-muted">{{ $subcategory->description ?: 'No description.' }}</small>
        </div>
    </div>
</div>
@endsection
