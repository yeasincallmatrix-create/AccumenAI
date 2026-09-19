@extends('layouts.admin')

@section('title', 'Feature Management — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Feature Management</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Feature Management</h4>
        <p class="page-header-desc">Manage feature availability across subscription packages.</p>
    </div>
    <div class="page-header-actions">
        <span class="badge text-bg-primary badge-soft">{{ $features->total() }} features × {{ $packages->count() }} packages</span>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        {{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('admin.features.index') }}">
        <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by feature key or name..." value="{{ request('q', '') }}">
            </div>

            <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                <label class="form-label mb-1">Module</label>
                <select class="form-select form-select-sm" name="module">
                    <option value="">All Modules</option>
                    @foreach ($modules as $mod)
                        <option value="{{ $mod }}" @selected(request('module') === $mod)>{{ ucfirst($mod) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:150px">
                <label class="form-label mb-1">Package</label>
                <select class="form-select form-select-sm" name="package">
                    <option value="">All Packages</option>
                    @php
                        $allPackages = \App\Models\SubscriptionPackage::where('status', 'active')->orderBy('id')->get();
                    @endphp
                    @foreach ($allPackages as $pkg)
                        <option value="{{ $pkg->id }}" @selected(request('package') == $pkg->id)>{{ $pkg->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.features.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-grid-3x3-gap-fill"></i> Feature x Package Matrix
        </div>
        <div class="toolbar-info">
            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Enabled</span>
            <span class="badge bg-secondary ms-1"><i class="bi bi-x-circle"></i> Disabled</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width:180px">Feature</th>
                    <th style="min-width:200px">Description</th>
                    @foreach ($packages as $pkg)
                        <th class="text-center" style="min-width:110px">
                            {{ $pkg->name }}
                            <br><small class="text-muted">{{ $pkg->slug }}</small>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($features as $feature)
                    <tr>
                        <td>
                            <a href="{{ route('admin.features.show', $feature->feature_key) }}" class="text-decoration-none fw-semibold">
                                {{ $feature->name }}
                            </a>
                            <br><small class="text-muted"><code>{{ $feature->feature_key }}</code></small>
                        </td>
                        <td class="text-muted">{{ $feature->description ?? '—' }}</td>
                        @foreach ($packages as $pkg)
                            <td class="text-center">
                                @php
                                    $isEnabled = in_array($feature->feature_key, $packageFeatures[$pkg->id] ?? [], true);
                                @endphp
                                <form method="POST" action="{{ route('admin.features.toggle-package', ['feature_key' => $feature->feature_key, 'package_id' => $pkg->id]) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="enabled" value="{{ $isEnabled ? '0' : '1' }}">
                                    <button type="submit" class="btn btn-sm {{ $isEnabled ? 'btn-success' : 'btn-outline-secondary' }}" title="{{ $isEnabled ? 'Click to disable' : 'Click to enable' }}">
                                        @if ($isEnabled)
                                            <i class="bi bi-check-circle-fill"></i>
                                        @else
                                            <i class="bi bi-x-circle"></i>
                                        @endif
                                    </button>
                                </form>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $packages->count() + 2 }}" class="text-center text-muted py-4">
                            @if (request('q') || request('module') || request('package'))
                                <i class="bi bi-search fs-3 d-block mb-2"></i>
                                No features match your filters.
                            @else
                                <i class="bi bi-grid-3x3-gap fs-3 d-block mb-2"></i>
                                No features configured.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($features->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $features->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
