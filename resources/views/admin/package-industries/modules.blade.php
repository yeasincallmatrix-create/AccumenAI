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
        <p class="page-header-desc">Modules this package unlocks for institutes in the {{ $industryName }} industry — grouped by parent module.</p>
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
                <span class="badge text-bg-light text-secondary border ms-1">{{ $modules->count() }} module(s)</span>
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

        @foreach ($groupedModules as $section)
            @php
                $sectionParents = $section['parents'];
                $sectionTotal = collect($sectionParents)->sum(fn($p) => count($p['children']));
                $sectionSelected = 0;
                foreach ($sectionParents as $pg) {
                    foreach ($pg['children'] as $m) {
                        $mBlocked = isset($industryDisabled[$m->key]);
                        $mCat = $matrix[$m->key] ?? null;
                        if ($mCat === null) {
                            $mCat = $mBlocked ? 'hidden' : ($service->isCoreModule($m->key) || isset($selection[$m->key]) ? 'default' : 'optional');
                        }
                        if (in_array($mCat, ['mandatory', 'default'], true)) {
                            $sectionSelected++;
                        }
                    }
                }
                $sectionOpen = in_array($section['key'], ['industry', 'core'], true);
            @endphp
            <details class="border-bottom" {{ $sectionOpen ? 'open' : '' }}>
                <summary class="px-3 py-2 d-flex flex-wrap align-items-center gap-2" style="cursor:pointer;list-style:none">
                    <i class="bi {{ $section['key'] === 'industry' ? 'bi-building-fill' : ($section['key'] === 'core' ? 'bi-grid-fill' : ($section['key'] === 'blocked' ? 'bi-slash-circle' : 'bi-boxes')) }} text-muted"></i>
                    <span class="fw-bold">{{ $section['label'] }}</span>
                    <span class="badge text-bg-primary">{{ count($sectionParents) }} parent(s)</span>
                    <span class="badge text-bg-info">{{ $sectionTotal }} module(s)</span>
                    <span class="badge text-bg-success">{{ $sectionSelected }} on</span>
                    @if ($section['key'] === 'blocked')
                        <span class="text-muted small">— forced off on save, cannot be enabled here</span>
                    @endif
                    @if ($section['key'] === 'other')
                        <span class="text-muted small">— belongs to other industries</span>
                    @endif
                </summary>
                <div class="p-2">
                    @foreach ($sectionParents as $parentKey => $pg)
                        @php
                            $parent = $pg['parent'];
                            $children = $pg['children'];
                            $groupSelected = 0;
                            foreach ($children as $m) {
                                $mBlocked = isset($industryDisabled[$m->key]);
                                $mCat = $matrix[$m->key] ?? null;
                                if ($mCat === null) {
                                    $mCat = $mBlocked ? 'hidden' : ($service->isCoreModule($m->key) || isset($selection[$m->key]) ? 'default' : 'optional');
                                }
                                if (in_array($mCat, ['mandatory', 'default'], true)) {
                                    $groupSelected++;
                                }
                            }
                        @endphp
                        <div class="border rounded mb-2" data-group-card="{{ $parentKey }}">
                            <div class="px-3 py-2 bg-body-secondary d-flex flex-wrap align-items-center gap-2">
                                @if($parent?->icon)
                                    <i class="bi {{ $parent->icon }}"></i>
                                @else
                                    <i class="bi bi-folder-fill text-muted"></i>
                                @endif
                                <span class="fw-semibold">{{ $parent?->name ?? ucwords(str_replace(['_', '.'], [' ', ' '], $parentKey)) }}</span>
                                <code class="small">{{ $parentKey }}</code>
                                @if($pg['blocked'])
                                    <span class="badge text-bg-danger">not for {{ $industryName }}</span>
                                @endif
                                <span class="badge text-bg-light border">{{ $groupSelected }}/{{ count($children) }} on</span>
                                <span class="ms-auto d-flex gap-1">
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-group-select="{{ $parentKey }}">All</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-group-clear="{{ $parentKey }}">None</button>
                                </span>
                            </div>
@php
    $categoryMeta = [
        'mandatory' => ['label' => 'Mandatory', 'class' => 'text-danger', 'hint' => 'Always on'],
        'default' => ['label' => 'Default', 'class' => 'text-success', 'hint' => 'On, tenant may switch off'],
        'optional' => ['label' => 'Optional', 'class' => 'text-secondary', 'hint' => 'Off, tenant may switch on'],
        'hidden' => ['label' => 'Hidden', 'class' => 'text-dark', 'hint' => 'Not listed to tenant'],
    ];
    $categories = array_keys($categoryMeta);
    $lockedMap = $locked ?? [];
@endphp
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                            <thead>
                                        <tr>
                                            <th style="width:200px">Key</th>
                                            <th>Name</th>
                                            @foreach ($categories as $category)
                                                <th class="text-center {{ $categoryMeta[$category]['class'] }}" title="{{ $categoryMeta[$category]['hint'] }}">{{ $categoryMeta[$category]['label'] }}</th>
                                            @endforeach
                                            <th class="text-center text-warning" title="Off here, on in a higher-tier package — needs upgrade">Locked 🔒</th>
                                            <th style="width:210px">Flags</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($children as $module)
                                            @php
                                                $isCore = $service->isCoreModule($module->key);
                                                $blocked = isset($industryDisabled[$module->key]);
                                                $locked = $isCore || $blocked;
                                                $isLocked = isset($lockedMap[$module->key]);
                                                $current = $matrix[$module->key]
                                                    ?? ($isCore ? 'mandatory' : ($blocked ? 'hidden' : (isset($selection[$module->key]) ? 'default' : 'optional')));
                                                $rowName = $parentKey . '_' . $loop->index;
                                            @endphp
                                            <tr class="{{ $blocked ? 'table-secondary' : '' }} {{ $module->key === $parentKey ? 'table-primary' : '' }}">
                                                <td>
                                                    <input type="hidden" name="modules[{{ $rowName }}][module_key]" value="{{ $module->key }}">
                                                    <code>{{ $module->key }}</code>
                                                    @if($module->key === $parentKey)
                                                        <span class="badge bg-primary ms-1" style="font-size:.65rem">parent</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="fw-semibold">
                                                        {{ $module->name }}
                                                    </span>
                                                    <div class="text-muted small">{{ $module->description ?: '-' }}</div>
                                                </td>
                                                @foreach ($categories as $category)
                                                    <td class="text-center">
                                                        <label class="d-block m-0 py-1" title="{{ $categoryMeta[$category]['hint'] }}">
                                                            <input type="radio" class="module-category"
                                                                name="modules[{{ $rowName }}][category]"
                                                                value="{{ $category }}"
                                                                data-parent-group="{{ $parentKey }}"
                                                                {{ $current === $category ? 'checked' : '' }}
                                                                {{ $locked ? 'disabled' : '' }}>
                                                        </label>
                                                    </td>
                                                @endforeach
                                                <td class="text-center">
                                                    @if ($isLocked)
                                                        <span class="badge text-bg-warning" title="Available in: {{ implode(', ', $lockedMap[$module->key]) }} — needs upgrade">🔒 {{ implode('+', array_map(fn ($s) => preg_replace('/^(medical|retail|education|restaurant|real_estate|manufacturing|training_center)_/', '', $s), $lockedMap[$module->key])) }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
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
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach

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
        const radios = (group) => Array.from(document.querySelectorAll('input.module-category[data-parent-group="' + group + '"]:not(:disabled)'));
        const allRadios = () => Array.from(document.querySelectorAll('input.module-category:not(:disabled)'));
        const setCategory = (scope, category) => {
            const list = scope ? radios(scope) : allRadios();
            const byName = {};
            list.forEach(function (el) { (byName[el.name] = byName[el.name] || []).push(el); });
            Object.values(byName).forEach(function (group) {
                const target = group.find(function (el) { return el.value === category; });
                if (target) { target.checked = true; }
            });
        };
        const selectAll = document.getElementById('selectAll');
        const selectNone = document.getElementById('selectNone');

        if (selectAll) {
            selectAll.addEventListener('click', function () { setCategory(null, 'default'); });
        }
        if (selectNone) {
            selectNone.addEventListener('click', function () { setCategory(null, 'hidden'); });
        }

        document.querySelectorAll('[data-group-select]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setCategory(btn.getAttribute('data-group-select'), 'default');
            });
        });
        document.querySelectorAll('[data-group-clear]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setCategory(btn.getAttribute('data-group-clear'), 'hidden');
            });
        });
    })();
</script>
@endpush
