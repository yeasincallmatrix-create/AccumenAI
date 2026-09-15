@extends('layouts.standalone')
@php $backUrl = route('admin.platform-settings.index'); @endphp
@section('title', 'Home Page Manager — Accumen AI')
@section('page_title', 'Home Page Manager')

@section('content')
<div class="standalone-heading d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4><i class="bi bi-house-page"></i> Home Page Manager</h4>
        <p>Manage landing pages and assign them to specific countries or globally.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
        <a href="{{ route('admin.home-pages.create') }}" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-plus-lg me-1"></i>Create Home Page
        </a>
    </div>
</div>

@if(session('status'))
<div class="alert alert-success" data-auto-dismiss>
    <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
</div>
@endif

{{-- Summary Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card p-3 text-center">
            <div class="fs-2 fw-bold text-primary">{{ $pages->count() }}</div>
            <div class="text-muted small">Total Pages</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center">
            <div class="fs-2 fw-bold text-success">{{ $pages->where('is_active')->count() }}</div>
            <div class="text-muted small">Active</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center">
            <div class="fs-2 fw-bold text-info">{{ $pages->where('is_global')->count() }}</div>
            <div class="text-muted small">Global Default</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center">
            <div class="fs-2 fw-bold text-violet">{{ $pages->sum(fn($p) => $p->countries->count()) }}</div>
            <div class="text-muted small">Country Assignments</div>
        </div>
    </div>
</div>

{{-- Pages List --}}
<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-list-ul"></i> All Home Pages
        </div>
    </div>

    @if($pages->isEmpty())
        <div class="text-center py-5">
            <i class="bi bi-house-door display-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No Home Pages Yet</h5>
            <p class="text-muted">Create your first home page to get started.</p>
            <a href="{{ route('admin.home-pages.create') }}" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-plus-lg me-1"></i>Create Home Page
            </a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Template</th>
                        <th>Countries</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Global</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pages as $page)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $page->name }}</div>
                            @if($page->description)
                                <div class="text-muted small text-truncate" style="max-width:250px">{{ $page->description }}</div>
                            @endif
                        </td>
                        <td><code>{{ $page->slug }}</code></td>
                        <td>
                            @php
                                $tplName = \App\Models\HomePage::availableTemplates()[$page->slug] ?? $page->slug;
                                $tplShort = Str::before($tplName, ' — ');
                            @endphp
                            <span class="badge bg-light text-dark">{{ $tplShort }}</span>
                        </td>
                        <td>
                            @if($page->is_global)
                                <span class="badge bg-info"><i class="bi bi-globe me-1"></i>Global</span>
                            @elseif($page->countries->isEmpty())
                                <span class="text-muted small">No countries assigned</span>
                            @else
                                @foreach($page->countries->take(3) as $country)
                                    <span class="badge bg-light text-dark">{{ $country->iso2 }}</span>
                                @endforeach
                                @if($page->countries->count() > 3)
                                    <span class="badge bg-secondary">+{{ $page->countries->count() - 3 }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('admin.home-pages.toggle', $page) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $page->is_active ? 'btn-success' : 'btn-outline-secondary' }} rounded-pill px-3" title="Toggle active">
                                    {{ $page->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="text-center">
                            @if($page->is_global)
                                <span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i>Default</span>
                            @else
                                <form method="POST" action="{{ route('admin.home-pages.set-global', $page) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill" title="Set as global default">
                                        <i class="bi bi-star"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.home-pages.preview', $page) }}" target="_blank" class="btn btn-outline-info" title="Preview">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.home-pages.edit', $page) }}" class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.home-pages.destroy', $page) }}" class="d-inline"
                                      onsubmit="return confirm('Delete &quot;{{ addslashes($page->name) }}&quot;? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
