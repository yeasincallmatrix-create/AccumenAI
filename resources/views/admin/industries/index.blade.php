@extends('layouts.admin')

@section('title', 'Industries — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active">Industries</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Industries</h4>
        <p class="page-header-desc">Manage platform industries and their sub-industries.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('admin.industries.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Add Industry
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

<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th style="width:100px">Sub-Industries</th>
                    <th style="width:100px">Status</th>
                    <th class="text-end" style="width:220px">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($industries as $industry)
                    <tr>
                        <td>{{ $industry->sort_order }}</td>
                        <td class="fw-semibold">{{ $industry->name }}</td>
                        <td><code>{{ $industry->slug }}</code></td>
                        <td>
                            <a href="{{ route('admin.industries.sub-industries', $industry) }}" class="text-decoration-none">
                                {{ $industry->sub_industries_count }}
                                <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.industries.toggle', $industry) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $industry->status === 'active' ? 'btn-success' : 'btn-outline-secondary' }}">
                                    {{ ucfirst($industry->status) }}
                                </button>
                            </form>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.industries.sub-industries', $industry) }}" class="btn btn-sm btn-outline-info" title="Manage Sub-Industries">
                                <i class="bi bi-diagram-3"></i>
                            </a>
                            <a href="{{ route('admin.industries.edit', $industry) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.industries.destroy', $industry) }}" class="d-inline" onsubmit="return confirm('Delete {{ $industry->name }}?');">
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
                        <td colspan="6" class="text-center text-muted py-4">No industries configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
