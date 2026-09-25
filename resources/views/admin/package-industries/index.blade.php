@extends('layouts.admin')

@section('title', 'Packages by Industry - AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Packages by Industry</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Package Configuration by Industry</h4>
        <p class="page-header-desc">Choose which subscription packages are offered in each industry, their price and their module set.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.modules.index') }}">
            <i class="bi bi-puzzle-fill"></i> Modules &amp; Packages
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

<div class="admin-card mb-4">
    <ul class="nav nav-tabs" role="tablist">
        @foreach ($industries as $ind)
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $ind->slug === $industry ? 'active' : '' }}"
                   href="{{ route('admin.package-industries.index', ['industry' => $ind->slug]) }}">
                    {{ $ind->name }}
                </a>
            </li>
        @endforeach
    </ul>
</div>

<form method="POST" action="{{ route('admin.package-industries.update') }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="industry" value="{{ $industry }}">

@if (! $totals['configured'])
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-1"></i>
        <strong>{{ $industryName }}</strong> has no package configuration yet: every active package stays available.
        Tick the packages you want to offer, then save.
    </div>
@endif

<div class="admin-card mb-4">

        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-box-seam"></i>
                INDUSTRY: <strong>{{ $industryName }}</strong>
                <span class="badge text-bg-primary badge-soft ms-2">{{ $totals['enabled'] }} / {{ $totals['packages'] }} packages enabled</span>
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg"></i> Save Configuration
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:70px">Offer</th>
                        <th>Package</th>
                        <th style="width:140px">Modules</th>
                        <th style="width:170px">Price / month</th>
                        <th style="width:170px">Price / year</th>
                        <th style="width:110px">Sort</th>
                        <th style="width:190px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $pkg = $row['package']; @endphp
                        <tr class="{{ $row['enabled'] ? '' : 'table-secondary' }}">
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           name="packages[]"
                                           value="{{ $pkg->id }}"
                                           id="pkg_{{ $pkg->id }}"
                                           {{ $row['enabled'] ? 'checked' : '' }}>
                                </div>
                            </td>
                            <td>
                                <label for="pkg_{{ $pkg->id }}" class="form-check-label fw-semibold">
                                    {{ $pkg->name }}
                                    @if ($pkg->is_default)
                                        <span class="badge text-bg-info badge-soft">default</span>
                                    @endif
                                </label>
                                <div class="text-muted small"><code>{{ $pkg->slug }}</code></div>
                            </td>
                            <td>
                                <span class="badge {{ $row['has_industry_modules'] ? 'text-bg-success' : 'text-bg-light text-secondary border' }}">
                                    {{ $row['module_count'] }} modules
                                </span>
                                <div class="text-muted small">
                                    {{ $row['has_industry_modules'] ? 'industry set' : 'package default' }}
                                </div>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">BDT</span>
                                    <input type="number" step="0.01" min="0" class="form-control"
                                           name="price_monthly[{{ $pkg->id }}]"
                                           value="{{ $row['price_monthly'] }}"
                                           placeholder="{{ $pkg->price_monthly }}">
                                </div>
                                @if ($row['has_price_override'])
                                    <div class="text-muted small">override active</div>
                                @else
                                    <div class="text-muted small">package default</div>
                                @endif
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">BDT</span>
                                    <input type="number" step="0.01" min="0" class="form-control"
                                           name="price_yearly[{{ $pkg->id }}]"
                                           value="{{ $row['price_yearly'] }}"
                                           placeholder="{{ $pkg->price_yearly }}">
                                </div>
                            </td>
                            <td>
                                <input type="number" min="0" max="9999" class="form-control form-control-sm"
                                       name="sort_order[{{ $pkg->id }}]"
                                       value="{{ $row['sort_order'] }}">
                            </td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary btn-sm"
                                   href="{{ route('admin.package-industries.show-modules', ['package' => $pkg->id, 'industry' => $industry]) }}">
                                    <i class="bi bi-puzzle"></i> Configure Modules
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No active packages found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                Unchecked packages are not offered to institutes in <strong>{{ $industryName }}</strong>.
                Tenants holding a non-offered package fall back to <strong>FREE</strong>.
            </span>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg"></i> Save Configuration
            </button>
        </div>
    </div>
</form>
@endsection
