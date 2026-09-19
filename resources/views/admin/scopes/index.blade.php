@extends('layouts.admin')

@section('title', 'Scoped Packages — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Scoped Packages</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Scoped Packages</h4>
        <p class="page-header-desc">
            Manage per-country / per-industry package scopes and their pricing.
            @if (!empty($package))
                <span class="badge text-bg-primary badge-soft ms-1">{{ $package->name }} ({{ $package->slug }})</span>
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        @if (!empty($package))
            <a href="{{ route('admin.packages.scopes.create', $package) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Create Scope
            </a>
        @endif
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
    <form class="filter-layout" method="GET" action="{{ !empty($package) ? route('admin.packages.scopes.index', $package) : route('admin.features.index') }}">
        <div class="filter-search-row align-items-end">
            <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                <label class="form-label mb-1">Package</label>
                <select class="form-select form-select-sm" name="package_id">
                    <option value="">All Packages</option>
                    @foreach ($packages as $pkg)
                        <option value="{{ $pkg->id }}" @selected((string) request('package_id', $package?->id ?? '') === (string) $pkg->id)>{{ $pkg->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                <label class="form-label mb-1">Country</label>
                <select class="form-select form-select-sm" name="country_id">
                    <option value="">All Countries</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected(request('country_id') == $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:150px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ !empty($package) ? route('admin.packages.scopes.index', $package) : url()->current() }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-diagram-3-fill"></i> Package Scopes
            <span class="badge bg-primary ms-2">{{ $scopes->total() }}</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Scope Key</th>
                    <th class="text-center">Inherit</th>
                    <th class="text-center">Features</th>
                    <th>Price</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($scopes as $scope)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $scope->package->name ?? '—' }}</span>
                            <br><small class="text-muted">{{ $scope->package->slug ?? '' }}</small>
                        </td>
                        <td>
                            <code>{{ $scope->country->name ?? 'Global' }}</code>
                            <br><small class="text-muted">
                                {{ $scope->industry->name ?? 'Any industry' }}
                                @if ($scope->subIndustry) / {{ $scope->subIndustry->name }} @endif
                            </small>
                        </td>
                        <td class="text-center">
                            @if ($scope->inherit_from_parent)
                                <span class="badge bg-info"><i class="bi bi-arrow-up-circle"></i> Yes</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary">{{ $featureCounts[$scope->id] ?? 0 }}</span>
                        </td>
                        <td class="text-muted small">{{ $scope->effectivePriceString() }}</td>
                        <td class="text-center">
                            <span class="badge {{ $scope->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($scope->status) }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.scopes.show', $scope) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.scopes.edit', $scope) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-diagram-3 fs-3 d-block mb-2"></i>
                            No scopes found for the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($scopes->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $scopes->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
