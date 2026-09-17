@extends('layouts.admin')

@section('title', $industry->name . ' — Sub-Industries — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.industries.index') }}" class="text-decoration-none">Industries</a></li>
        <li class="breadcrumb-item active">{{ $industry->name }}</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $industry->name }} → Sub-Industries</h4>
        <p class="page-header-desc">Manage sub-industries and their country availability.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('admin.industries.sub-industry.create', $industry) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Add Sub-Industry
        </a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger py-2">
        @foreach ($errors->all() as $error)
            <div class="small">{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="GET" action="{{ route('admin.industries.sub-industries', $industry) }}" class="mb-3">
    <div class="d-flex gap-2 align-items-end flex-wrap">
        <div>
            <label class="form-label form-label-sm mb-0">Search</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name or slug..." style="min-width:200px">
        </div>
        <div>
            <label class="form-label form-label-sm mb-0">Country</label>
            <select name="country" class="form-select form-select-sm" style="min-width:180px" onchange="this.form.submit()">
                <option value="">All Countries</option>
                <option value="global" @selected(request('country') === 'global')>Global</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @selected(request('country') == $country->id)>{{ $country->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label form-label-sm mb-0">Status</label>
            <select name="status" class="form-select form-select-sm" style="min-width:130px" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        @if (request()->hasAny(['country', 'status', 'q']))
            <a href="{{ route('admin.industries.sub-industries', $industry) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-lg"></i> Clear
            </a>
        @endif
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Countries</th>
                    <th style="width:100px">Status</th>
                    <th class="text-end" style="width:180px">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subIndustries as $sub)
                    <tr>
                        <td>{{ $sub->sort_order }}</td>
                        <td class="fw-semibold">{{ $sub->name }}</td>
                        <td><code>{{ $sub->slug }}</code></td>
                        <td>
                            @if ($sub->country_id === null)
                                <span class="badge bg-info">Global</span>
                            @else
                                <span class="badge bg-light text-dark border">{{ $sub->country->name ?? '—' }}</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.industries.sub-industry.toggle', [$industry, $sub]) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $sub->status === 'active' ? 'btn-success' : 'btn-outline-secondary' }}">
                                    {{ ucfirst($sub->status) }}
                                </button>
                            </form>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.industries.sub-industry.edit', [$industry, $sub]) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.industries.sub-industry.destroy', [$industry, $sub]) }}" class="d-inline" onsubmit="return confirm('Delete {{ $sub->name }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No sub-industries configured for this industry.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
