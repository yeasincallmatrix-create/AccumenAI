@extends('layouts.admin')

@section('title', 'Scope Detail — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.packages.scopes.index', $scope->package) }}" class="text-decoration-none">Scoped Packages</a></li>
        <li class="breadcrumb-item active" aria-current="page">Scope #{{ $scope->id }}</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $scope->country->name ?? 'Global' }}
            <small class="text-muted">/ {{ $scope->industry->name ?? 'Any industry' }}@if ($scope->subIndustry) / {{ $scope->subIndustry->name }}@endif</small>
        </h4>
        <p class="page-header-desc">
            Package: <strong>{{ $scope->package->name ?? '—' }}</strong>
            <span class="badge {{ $scope->status === 'active' ? 'bg-success' : 'bg-secondary' }} ms-2">{{ ucfirst($scope->status) }}</span>
            @if ($scope->inherit_from_parent)
                <span class="badge bg-info ms-1"><i class="bi bi-arrow-up-circle"></i> Inherits from parent</span>
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('admin.scopes.edit', $scope) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        <form method="POST" action="{{ route('admin.scopes.destroy', $scope) }}" class="d-inline" onsubmit="return confirm('Delete this scope and all its scoped features?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
        </form>
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

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-4">
        <div class="admin-card mb-4">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-info-circle"></i> Scope Info</div>
            </div>
            <div class="p-3">
                <table class="table table-borderless mb-0">
                    <tr><td class="text-muted" style="width:130px">Package</td><td class="fw-semibold">{{ $scope->package->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Country</td><td>{{ $scope->country->name ?? 'Global' }}</td></tr>
                    <tr><td class="text-muted">Industry</td><td>{{ $scope->industry->name ?? 'Any' }}</td></tr>
                    <tr><td class="text-muted">Sub-industry</td><td>{{ $scope->subIndustry->name ?? 'Any' }}</td></tr>
                    <tr><td class="text-muted">Inherit</td><td>{{ $scope->inherit_from_parent ? 'Yes' : 'No' }}</td></tr>
                    <tr><td class="text-muted">Monthly</td><td>{{ $scope->price_monthly ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Yearly</td><td>{{ $scope->price_yearly ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Currency</td><td>{{ $scope->currency ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Effective</td><td class="small">{{ $scope->effectivePriceString() }}</td></tr>
                    <tr><td class="text-muted">Status</td><td>{{ ucfirst($scope->status) }}</td></tr>
                </table>
            </div>
        </div>

        <div class="admin-card mb-4">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-arrow-up-circle"></i> Parent Scope</div>
            </div>
            <div class="p-3">
                @if ($parentScope)
                    <a href="{{ route('admin.scopes.show', $parentScope) }}" class="text-decoration-none">
                        Scope #{{ $parentScope->id }} — {{ $parentScope->country->name ?? 'Global' }} / {{ $parentScope->industry->name ?? 'Any' }}
                    </a>
                @else
                    <span class="text-muted">No parent scope (most general level).</span>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="admin-card mb-4">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-grid-3x3-gap-fill"></i> Scoped Features</div>
            </div>
            <form method="POST" action="{{ route('admin.scopes.features.update', $scope) }}">
                @csrf
                @method('PUT')
                <div class="p-3">
                    @forelse ($scopedFeatures as $moduleKey => $features)
                        <h6 class="mt-3 mb-2"><code>{{ $moduleKey }}</code></h6>
                        <div class="row g-2">
                            @foreach ($features as $feature)
                                @php
                                    $isEnabled = (bool) ($scopedMap[$feature->feature_key] ?? false);
                                    $inherited = !$isEnabled && isset($parentMap[$feature->feature_key]);
                                @endphp
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]"
                                            value="{{ $feature->feature_key }}" id="feat-{{ $feature->id }}"
                                            @checked($isEnabled)>
                                        <label class="form-check-label" for="feat-{{ $feature->id }}">
                                            {{ $feature->name }}
                                            <br><small class="text-muted"><code>{{ $feature->feature_key }}</code></small>
                                            @if ($inherited)
                                                <span class="badge bg-info ms-1">inherited</span>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-muted mb-0">No features in the registry.</p>
                    @endforelse
                </div>
                <div class="p-3 border-top d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.packages.scopes.index', $scope->package) }}" class="btn btn-outline-secondary btn-sm">Back</a>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save Features</button>
                </div>
            </form>
        </div>

        <div class="admin-card mb-4">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-boxes"></i> Scoped Modules</div>
            </div>
            <form method="POST" action="{{ route('admin.scopes.modules.update', $scope) }}">
                @csrf
                @method('PUT')
                <div class="p-3">
                    @php
                        $groupedModules = $allModules->groupBy(fn ($m) => $m->parent_key ?? 'top');
                        $topModules = $groupedModules->pull('top', collect());
                    @endphp
                    @forelse ($topModules as $module)
                        <h6 class="mt-3 mb-2"><code>{{ $module->key }}</code> <span class="text-muted fw-normal">— {{ $module->name }}</span></h6>
                        <div class="row g-2">
                            @php
                                $isModuleEnabled = (bool) ($scopedModuleMap[$module->key] ?? false);
                                $moduleInherited = !$isModuleEnabled && isset($parentModuleMap[$module->key]);
                            @endphp
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="modules[]"
                                        value="{{ $module->key }}" id="mod-{{ $module->id }}"
                                        @checked($isModuleEnabled)>
                                    <label class="form-check-label" for="mod-{{ $module->id }}">
                                        Enable <code>{{ $module->key }}</code>
                                        @if ($moduleInherited)
                                            <span class="badge bg-info ms-1">inherited</span>
                                        @endif
                                    </label>
                                </div>
                            </div>
                            @foreach ($groupedModules->get($module->key, collect()) as $child)
                                @php
                                    $isChildEnabled = (bool) ($scopedModuleMap[$child->key] ?? false);
                                    $childInherited = !$isChildEnabled && isset($parentModuleMap[$child->key]);
                                @endphp
                                <div class="col-md-6 ps-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="modules[]"
                                            value="{{ $child->key }}" id="mod-{{ $child->id }}"
                                            @checked($isChildEnabled)>
                                        <label class="form-check-label" for="mod-{{ $child->id }}">
                                            {{ $child->name }}
                                            <br><small class="text-muted"><code>{{ $child->key }}</code></small>
                                            @if ($childInherited)
                                                <span class="badge bg-info ms-1">inherited</span>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-muted mb-0">No modules in the registry.</p>
                    @endforelse
                </div>
                <div class="p-3 border-top d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.packages.scopes.index', $scope->package) }}" class="btn btn-outline-secondary btn-sm">Back</a>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save Modules</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
