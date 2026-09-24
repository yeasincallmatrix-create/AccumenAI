@extends('layouts.admin')

@section('title', 'Modules Overview — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active">Modules Overview</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Bulk Module Overview</h4>
        <p class="page-header-desc">All tenants with override counts and active emergency overrides. High-activity tenants highlighted.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.modules.access-logs') }}">
            <i class="bi bi-clock-history"></i> Global Access Logs
        </a>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-funnel"></i> Filters
        </div>
        <div class="toolbar-actions">
            <form method="GET" action="{{ route('admin.institutes.modules-overview') }}" class="d-flex align-items-center gap-2 flex-wrap">
                <select name="industry" class="form-select form-select-sm" style="width:auto;min-width:160px">
                    <option value="">All Industries</option>
                    @foreach ($industries as $ind)
                        <option value="{{ $ind }}" {{ request('industry') === $ind ? 'selected' : '' }}>{{ $ind }}</option>
                    @endforeach
                </select>
                <select name="package" class="form-select form-select-sm" style="width:auto;min-width:150px">
                    <option value="">All Packages</option>
                    @foreach ($packages as $pkg)
                        <option value="{{ $pkg->id }}" {{ request('package') == $pkg->id ? 'selected' : '' }}>{{ $pkg->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" class="form-control form-control-sm" style="width:auto;min-width:170px" placeholder="Search tenant…" value="{{ request('search') }}">
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i> Filter</button>
                @if (request()->hasAny(['industry', 'package', 'search']))
                    <a href="{{ route('admin.institutes.modules-overview') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                @endif
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Tenant</th>
                    <th>Industry</th>
                    <th>Sub-Category</th>
                    <th>Package</th>
                    <th class="text-center" style="width:110px">Overrides</th>
                    <th class="text-center" style="width:110px">Emergency</th>
                    <th class="text-center" style="width:90px">Risk</th>
                    <th class="text-end" style="width:200px">Quick Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($institutes as $inst)
                    @php
                        $overrideCount = (int) ($overrides[$inst->id] ?? 0);
                        $emergencyCount = isset($emergencyOverrides[$inst->id]) ? $emergencyOverrides[$inst->id]->count() : 0;
                        $risk = $emergencyCount > 0 ? 'critical' : ($overrideCount >= 5 ? 'high' : ($overrideCount >= 1 ? 'medium' : 'low'));
                        $riskBadge = ['low' => 'success', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger'][$risk];
                    @endphp
                    <tr class="{{ $risk === 'critical' ? 'table-danger' : ($risk === 'high' ? 'table-warning' : '') }}">
                        <td class="text-muted">{{ $inst->id }}</td>
                        <td>
                            <span class="fw-semibold">{{ $inst->name }}</span>
                            <br><small class="text-muted">{{ $inst->country_code ?? 'BD' }}</small>
                        </td>
                        <td><span class="badge bg-light text-dark border">{{ $inst->industry ?? '—' }}</span></td>
                        <td>{{ $inst->subcategory_key ? ($inst->subcategory_key) : '—' }}</td>
                        <td>
                            @php $pkg = $packages->firstWhere('id', $inst->package_id); @endphp
                            <span class="badge bg-secondary">{{ $pkg->name ?? 'free' }}</span>
                        </td>
                        <td class="text-center">
                            @if ($overrideCount > 0)
                                <span class="badge bg-info-subtle text-info">{{ $overrideCount }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($emergencyCount > 0)
                                <span class="badge bg-danger">{{ $emergencyCount }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="text-center"><span class="badge bg-{{ $riskBadge }}">{{ $risk }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.institutes.modules', $inst) }}" class="btn btn-outline-primary btn-sm" title="Review module access">
                                <i class="bi bi-grid"></i> Review
                            </a>
                            <a href="{{ route('admin.institutes.access-log', $inst) }}" class="btn btn-outline-secondary btn-sm" title="Audit log">
                                <i class="bi bi-clock-history"></i> Log
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No tenants found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-center">
    {{ $institutes->links() }}
</div>
@endsection
