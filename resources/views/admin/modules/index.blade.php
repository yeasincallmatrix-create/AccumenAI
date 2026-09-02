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
        <p class="page-header-desc">{{ mawa_e('modules.registry_description') }}</p>
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
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-puzzle-fill"></i> {{ mawa_e('modules.module_registry') }}
        </div>
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
                @forelse ($modules as $module)
                    <tr>
                        <td><code>{{ $module->key }}</code></td>
                        <td>{{ $module->name }}</td>
                        <td>
                            @php
                                $typeBadge = match($module->type ?? 'core') {
                                    'core' => 'bg-primary',
                                    'addon' => 'bg-info',
                                    'beta' => 'bg-warning text-dark',
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
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">{{ mawa_e('modules.no_modules') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($packages->isNotEmpty())
<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-grid-3x3-gap-fill"></i> {{ mawa_e('modules.package_matrix') }}
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width:150px">{{ mawa_e('modules.module') }}</th>
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
                @forelse ($modules as $module)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $module->name }}</span>
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
                @empty
                    <tr>
                        <td colspan="{{ $packages->count() + 1 }}" class="text-center text-muted py-4">{{ mawa_e('modules.no_modules') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
