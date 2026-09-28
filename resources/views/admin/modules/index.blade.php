@extends('layouts.admin')

@section('title', mawa_e('modules.title') . ' — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Modules &amp; Packages</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('modules.title') }}</h4>
        <p class="page-header-desc">{{ mawa_e('modules.registry_description') }} — grouped by Industry → Parent Module.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.modules.access-logs') }}">
            <i class="bi bi-clock-history"></i> {{ mawa_e('modules.access_logs') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="admin-card mb-4">
    <ul class="nav nav-tabs" id="moduleTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="registry-tab" data-bs-toggle="tab" data-bs-target="#registry-pane" type="button" role="tab" aria-controls="registry-pane" aria-selected="true">
                <i class="bi bi-puzzle-fill me-1"></i> {{ mawa_e('modules.module_registry') }}
                <span class="badge text-bg-primary badge-soft ms-1">{{ $modules->count() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="matrix-tab" data-bs-toggle="tab" data-bs-target="#matrix-pane" type="button" role="tab" aria-controls="matrix-pane" aria-selected="false">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> {{ mawa_e('modules.package_matrix') }}
                <span class="badge text-bg-info badge-soft ms-1">{{ $packages->count() }}</span>
            </button>
        </li>
    </ul>
    <div class="tab-content">
        {{-- Module Registry Tab — Industry → Parent Module --}}
        <div class="tab-pane fade show active" id="registry-pane" role="tabpanel" aria-labelledby="registry-tab">
            <div class="p-3">
                <form method="GET" action="{{ route('admin.modules.index') }}" class="mb-3" id="registryFilterForm">
                    <input type="hidden" name="tab" value="registry">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="form-label fw-semibold small"><i class="bi bi-funnel me-1"></i> Filter by Industry</label>
                            <select name="industry_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
                                <option value="">All Industries</option>
                                @foreach ($industries as $ind)
                                    <option value="{{ $ind->id }}" {{ $selectedIndustryId == $ind->id ? 'selected' : '' }}>
                                        {{ $ind->name }} ({{ $ind->slug }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <span class="text-muted small">
                                <i class="bi bi-info-circle me-1"></i>
                                Showing {{ $modules->count() }} module(s) in {{ count($groupedModules) }} industrie(s){{ $selectedIndustryId ? ' for selected industry (+ Shared)' : '' }}
                            </span>
                        </div>
                    </div>
                </form>

                <div class="accordion" id="industryAccordion">
                    @forelse ($groupedModules as $industrySlug => $group)
                        @php
                            $parentGroups = $group['parents'];
                            $totalMods = collect($parentGroups)->sum(fn($p) => count($p['children']));
                        @endphp
                        <div class="accordion-item mb-2 border rounded overflow-hidden">
                            <h2 class="accordion-header" id="heading_{{ $industrySlug }}">
                                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_{{ $industrySlug }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse_{{ $industrySlug }}">
                                    <i class="bi bi-building me-2"></i>
                                    <span class="fw-bold">{{ $group['label'] }}</span>
                                    <span class="badge bg-secondary ms-2">{{ $industrySlug }}</span>
                                    <span class="badge text-bg-primary ms-2">{{ count($parentGroups) }} parent(s)</span>
                                    <span class="badge text-bg-info ms-1">{{ $totalMods }} module(s)</span>
                                </button>
                            </h2>
                            <div id="collapse_{{ $industrySlug }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="heading_{{ $industrySlug }}">
                                <div class="accordion-body p-2">
                                    @foreach ($parentGroups as $parentKey => $pg)
                                        @php
                                            $parent = $pg['parent'];
                                            $children = $pg['children'];
                                        @endphp
                                        <div class="border rounded mb-2">
                                            <div class="px-3 py-2 bg-body-secondary d-flex flex-wrap align-items-center gap-2">
                                                @if($parent?->icon)
                                                    <i class="bi {{ $parent->icon }}"></i>
                                                @else
                                                    <i class="bi bi-folder-fill text-muted"></i>
                                                @endif
                                                <span class="fw-semibold">{{ $parent?->name ?? ucwords(str_replace(['_', '.'], [' ', ' '], $parentKey)) }}</span>
                                                <code class="small">{{ $parentKey }}</code>
                                                @if($parent)
                                                    @php
                                                        $typeBadge = match($parent->type ?? 'core') {
                                                            'core' => 'bg-primary',
                                                            'addon' => 'bg-info',
                                                            'beta' => 'bg-warning text-dark',
                                                            'industry' => 'bg-success',
                                                            default => 'bg-secondary',
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $typeBadge }}">{{ ucfirst($parent->type ?? 'core') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">group</span>
                                                @endif
                                                <span class="badge text-bg-light border ms-auto">{{ count($children) }} module(s)</span>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-hover align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:180px">{{ mawa_e('modules.key') }}</th>
                                                            <th>{{ mawa_e('modules.name') }}</th>
                                                            <th style="width:100px">{{ mawa_e('modules.type') }}</th>
                                                            <th>{{ mawa_e('modules.description') }}</th>
                                                            <th style="width:120px">{{ mawa_e('modules.status') }}</th>
                                                            <th>{{ mawa_e('modules.dependencies') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($children as $module)
                                                            <tr class="{{ $module->key === $parentKey ? 'table-primary' : '' }}">
                                                                <td>
                                                                    <code>{{ $module->key }}</code>
                                                                    @if($module->key === $parentKey)
                                                                        <span class="badge bg-primary ms-1" style="font-size:.65rem">parent</span>
                                                                    @endif
                                                                </td>
                                                                <td>{{ $module->name }}</td>
                                                                <td>
                                                                    @php
                                                                        $typeBadge = match($module->type ?? 'core') {
                                                                            'core' => 'bg-primary',
                                                                            'addon' => 'bg-info',
                                                                            'beta' => 'bg-warning text-dark',
                                                                            'industry' => 'bg-success',
                                                                            default => 'bg-secondary',
                                                                        };
                                                                    @endphp
                                                                    <span class="badge {{ $typeBadge }}">{{ ucfirst($module->type ?? 'core') }}</span>
                                                                </td>
                                                                <td class="text-muted">{{ $module->description ?? '—' }}</td>
                                                                <td>
                                                                    <form method="POST" action="{{ route('admin.modules.update', $module) }}" class="d-inline">
                                                                        @csrf
                                                                        @method('PUT')
                                                                        <input type="hidden" name="status" value="{{ $module->status === 'active' ? 'inactive' : 'active' }}">
                                                                        <button type="submit" class="btn btn-sm {{ $module->status === 'active' ? 'btn-success' : 'btn-outline-secondary' }}">
                                                                            {{ $module->status === 'active' ? mawa_e('modules.active') : mawa_e('modules.inactive') }}
                                                                        </button>
                                                                    </form>
                                                                </td>
                                                                <td>
                                                                    @if (!empty($module->dependencies) && is_array($module->dependencies))
                                                                        @foreach ($module->dependencies as $dep)
                                                                            <span class="badge bg-light text-dark border">{{ $dep }}</span>
                                                                        @endforeach
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">{{ mawa_e('modules.no_modules') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Package Matrix Tab — same Industry → Parent grouping --}}
        <div class="tab-pane fade" id="matrix-pane" role="tabpanel" aria-labelledby="matrix-tab">
            <div class="p-3">
                @if ($packages->isNotEmpty())
                    <form method="GET" action="{{ route('admin.modules.index') }}" class="mb-3" id="matrixFilterForm">
                        <input type="hidden" name="tab" value="matrix">
                        <div class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label fw-semibold small"><i class="bi bi-funnel me-1"></i> Filter by Industry</label>
                                <select name="industry_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
                                    <option value="">All Industries</option>
                                    @foreach ($industries as $ind)
                                        <option value="{{ $ind->id }}" {{ $selectedIndustryId == $ind->id ? 'selected' : '' }}>
                                            {{ $ind->name }} ({{ $ind->slug }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <span class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Showing {{ $packages->count() }} package(s) × {{ $modules->count() }} module(s), grouped by Industry → Parent
                                </span>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="min-width:220px">{{ mawa_e('modules.module') }}</th>
                                    @foreach ($packages as $pkg)
                                        <th class="text-center" style="min-width:120px">
                                            <a href="{{ route('admin.packages.modules', $pkg) }}" class="text-decoration-none fw-semibold">
                                                {{ $pkg->name }}
                                            </a>
                                            <br><small class="text-muted">{{ $pkg->slug }}</small>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($groupedModules as $industrySlug => $group)
                                    <tr class="table-secondary">
                                        <td colspan="{{ $packages->count() + 1 }}">
                                            <i class="bi bi-building me-1"></i>
                                            <span class="fw-bold">{{ $group['label'] }}</span>
                                            <span class="badge bg-secondary ms-1">{{ $industrySlug }}</span>
                                        </td>
                                    </tr>
                                    @foreach ($group['parents'] as $parentKey => $pg)
                                        @php $parent = $pg['parent']; @endphp
                                        <tr class="table-light">
                                            <td colspan="{{ $packages->count() + 1 }}">
                                                <i class="bi bi-folder-fill me-1 text-muted"></i>
                                                <span class="fw-semibold">{{ $parent?->name ?? ucwords(str_replace(['_', '.'], [' ', ' '], $parentKey)) }}</span>
                                                <code class="small ms-1">{{ $parentKey }}</code>
                                                <span class="badge text-bg-light border ms-1">{{ count($pg['children']) }}</span>
                                            </td>
                                        </tr>
                                        @foreach ($pg['children'] as $module)
                                            <tr>
                                                <td class="ps-4">
                                                    <span class="fw-semibold">{{ $module->name }}</span>
                                                    @if($module->key === $parentKey)
                                                        <span class="badge bg-primary ms-1" style="font-size:.65rem">parent</span>
                                                    @endif
                                                    <br><small class="text-muted"><code>{{ $module->key }}</code></small>
                                                </td>
                                                @foreach ($packages as $pkg)
                                                    <td class="text-center">
                                                        @if (isset($packageModules[$pkg->id][$module->key]) && $packageModules[$pkg->id][$module->key])
                                                            <a href="{{ route('admin.packages.modules', $pkg) }}" class="text-decoration-none">
                                                                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                                            </a>
                                                        @else
                                                            <a href="{{ route('admin.packages.modules', $pkg) }}" class="text-decoration-none">
                                                                <i class="bi bi-x-circle text-muted fs-5"></i>
                                                            </a>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="{{ $packages->count() + 1 }}" class="text-center text-muted py-4">{{ mawa_e('modules.no_modules') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-grid-3x3-gap fs-1 d-block mb-2"></i>
                        {{ mawa_e('modules.no_packages') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabParam = new URLSearchParams(window.location.search).get('tab');
    if (tabParam === 'matrix') {
        const matrixTab = document.getElementById('matrix-tab');
        if (matrixTab) {
            const tab = new bootstrap.Tab(matrixTab);
            tab.show();
        }
    }
});
</script>
@endsection
