@extends('layouts.admin')

@section('title', $package->name . ' - Modules - AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.package-industries.index', ['industry' => $industry]) }}" class="text-decoration-none">Packages by Industry</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $package->name }} &times; {{ $industryName }}</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $package->name }} &mdash; {{ $industryName }} modules</h4>
        <p class="page-header-desc">Modules this package unlocks for institutes in the {{ $industryName }} industry.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.package-industries.index', ['industry' => $industry]) }}">
            <i class="bi bi-arrow-left"></i> Back to Industry
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
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (! $mapped)
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>{{ $package->name }}</strong> is not currently offered in <strong>{{ $industryName }}</strong>.
        <a href="{{ route('admin.package-industries.index', ['industry' => $industry]) }}">Enable it first</a> so tenants can use this configuration.
    </div>
@endif

<form method="POST" action="{{ route('admin.package-industries.update-modules', ['package' => $package->id, 'industry' => $industry]) }}">
    @csrf
    @method('PUT')

    <div class="admin-card mb-4">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-puzzle-fill"></i>
                Module set
                <span class="badge {{ $source === 'industry' ? 'text-bg-success' : 'text-bg-light text-secondary border' }} ms-2">
                    {{ $source === 'industry' ? 'industry specific' : 'package default (not yet customized)' }}
                </span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAll">
                    <i class="bi bi-check-all"></i> Select all
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="selectNone">
                    <i class="bi bi-x-lg"></i> Select none
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg"></i> Save Modules
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:70px">On</th>
                        <th style="width:200px">Key</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th style="width:210px">Flags</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                        @php
                            $isCore = $service->isCoreModule($module->key);
                            $blocked = isset($industryDisabled[$module->key]);
                            $checked = $blocked ? false : ($isCore || isset($selection[$module->key]));
                        @endphp
                        <tr class="{{ $blocked ? 'table-secondary' : '' }}">
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input module-toggle" type="checkbox"
                                           name="modules[]"
                                           value="{{ $module->key }}"
                                           id="mod_{{ $module->key }}"
                                           {{ $checked ? 'checked' : '' }}
                                           {{ ($isCore || $blocked) ? 'disabled' : '' }}>
                                </div>
                            </td>
                            <td>
                                <label for="mod_{{ $module->key }}" class="form-check-label">
                                    <code>{{ $module->key }}</code>
                                </label>
                            </td>
                            <td>
                                <label for="mod_{{ $module->key }}" class="form-check-label fw-semibold">
                                    {{ $module->name }}
                                </label>
                            </td>
                            <td class="text-muted small">{{ $module->description ?: '-' }}</td>
                            <td>
                                @if ($isCore)
                                    <span class="badge text-bg-info">core (always on)</span>
                                @endif
                                @if ($blocked)
                                    <span class="badge text-bg-danger">not for {{ $industryName }}</span>
                                @endif
                                @if ($module->coming_soon)
                                    <span class="badge text-bg-warning">coming soon</span>
                                @endif
                                @if (! empty($module->parent_key))
                                    <span class="badge text-bg-light text-secondary border">{{ $module->parent_key }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No active modules registered.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                Core modules and modules unavailable in {{ $industryName }} cannot be changed here.
            </span>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg"></i> Save Modules
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    (function () {
        const selectAll = document.getElementById('selectAll');
        const selectNone = document.getElementById('selectNone');
        if (!selectAll || !selectNone) return;

        const toggles = () => Array.from(document.querySelectorAll('input.module-toggle:not(:disabled)'));

        selectAll.addEventListener('click', function () {
            toggles().forEach(function (el) { el.checked = true; });
        });
        selectNone.addEventListener('click', function () {
            toggles().forEach(function (el) { el.checked = false; });
        });
    })();
</script>
@endpush
