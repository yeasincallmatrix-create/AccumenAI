@extends('layouts.admin')

@section('title', $package->name . ' — ' . mawa_e('modules.package_modules') . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $package->name }} — {{ mawa_e('modules.package_modules') }}</h4>
        <p class="page-header-desc">{{ mawa_e('modules.package_modules_description') }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.modules.index') }}">
            <i class="bi bi-arrow-left"></i> {{ mawa_e('modules.back_to_registry') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="POST" action="{{ route('admin.packages.modules.update', $package) }}">
    @csrf
    @method('PUT')

    <div class="admin-card mb-4">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-puzzle-fill"></i> {{ mawa_e('modules.module_access') }}
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg"></i> {{ mawa_e('modules.save_changes') }}
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:60px"></th>
                        <th style="width:180px">{{ mawa_e('modules.key') }}</th>
                        <th>{{ mawa_e('modules.name') }}</th>
                        <th>{{ mawa_e('modules.description') }}</th>
                        <th>{{ mawa_e('modules.dependencies') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                        @php
                            $isEnabled = isset($packageModules[$module->key]) && $packageModules[$module->key];
                            $deps = $module->dependencies ?? [];
                            $missingDeps = [];
                            foreach ($deps as $dep) {
                                if (!isset($packageModules[$dep]) || !$packageModules[$dep]) {
                                    $missingDeps[] = $dep;
                                }
                            }
                        @endphp
                        <tr>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input module-toggle" type="checkbox"
                                           name="modules[]"
                                           value="{{ $module->key }}"
                                           id="module_{{ $module->key }}"
                                           {{ $isEnabled ? 'checked' : '' }}
                                           data-module-key="{{ $module->key }}"
                                           data-dependencies="{{ json_encode($deps) }}">
                                </div>
                            </td>
                            <td>
                                <label for="module_{{ $module->key }}" class="form-check-label">
                                    <code>{{ $module->key }}</code>
                                </label>
                            </td>
                            <td>
                                <label for="module_{{ $module->key }}" class="form-check-label fw-semibold">
                                    {{ $module->name }}
                                </label>
                            </td>
                            <td class="text-muted">{{ $module->description ?? '—' }}</td>
                            <td>
                                @if (!empty($deps))
                                    @foreach ($deps as $dep)
                                        @php
                                            $depEnabled = isset($packageModules[$dep]) && $packageModules[$dep];
                                        @endphp
                                        <span class="badge {{ $depEnabled ? 'bg-light text-success border' : 'bg-light text-danger border' }}">
                                            {{ $depEnabled ? '✓' : '✗' }} {{ $dep }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">{{ mawa_e('modules.no_modules') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($modules->isNotEmpty())
    <div class="admin-card">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-info-circle"></i> {{ mawa_e('modules.dependency_warnings') }}
            </div>
        </div>
        <div class="card-body">
            <div id="depWarnings" class="text-muted">
                {{ mawa_e('modules.no_warnings') }}
            </div>
        </div>
    </div>
    @endif
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('.module-toggle');
    const warningsEl = document.getElementById('depWarnings');

    function checkDependencies() {
        const enabled = new Set();
        toggles.forEach(function(t) {
            if (t.checked) enabled.add(t.dataset.moduleKey);
        });

        const warnings = [];
        toggles.forEach(function(t) {
            if (!t.checked) return;
            try {
                const deps = JSON.parse(t.dataset.dependencies || '[]');
                deps.forEach(function(dep) {
                    if (!enabled.has(dep)) {
                        warnings.push(
                            '<div class="alert alert-warning py-2 mb-2">' +
                            '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' +
                            '<strong>' + t.dataset.moduleKey + '</strong> {{ mawa_e("modules.depends_on") }} <strong>' + dep + '</strong>, {{ mawa_e("modules.which_is_disabled") }}' +
                            '</div>'
                        );
                    }
                });
            } catch(e) {}
        });

        if (warningsEl) {
            warningsEl.innerHTML = warnings.length > 0 ? warnings.join('') : '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> {{ mawa_e("modules.all_deps_satisfied") }}</span>';
        }
    }

    toggles.forEach(function(t) {
        t.addEventListener('change', checkDependencies);
    });

    checkDependencies();
});
</script>
@endpush
@endsection
