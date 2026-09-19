@extends('layouts.admin')

@section('title', $feature->name . ' — Feature Detail — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.features.index') }}" class="text-decoration-none">Feature Management</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $feature->name }}</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $feature->name }}</h4>
        <p class="page-header-desc">{{ $feature->description ?? 'No description' }}</p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-card mb-4">
            <div class="table-toolbar">
                <div class="toolbar-info">
                    <i class="bi bi-info-circle"></i> Feature Details
                </div>
            </div>
            <div class="p-3">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:140px">Feature Key</td>
                        <td><code>{{ $feature->feature_key }}</code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Module</td>
                        <td><code>{{ $feature->module_key }}</code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td>
                            <span class="badge {{ $feature->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($feature->status) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Sort Order</td>
                        <td>{{ $feature->sort_order ?? '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info">
                    <i class="bi bi-box-seam"></i> Package Availability
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th class="text-center" style="width:100px">Status</th>
                            <th class="text-center" style="width:100px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($packages as $pkg)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $pkg->name }}</span>
                                    <br><small class="text-muted">{{ $pkg->slug }}</small>
                                </td>
                                <td class="text-center">
                                    @if ($packageFeatures[$pkg->id] ?? false)
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Enabled</span>
                                    @else
                                        <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Disabled</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.features.toggle-package', ['feature_key' => $feature->feature_key, 'package_id' => $pkg->id]) }}">
                                        @csrf
                                        <input type="hidden" name="enabled" value="{{ ($packageFeatures[$pkg->id] ?? false) ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-sm {{ ($packageFeatures[$pkg->id] ?? false) ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                            {{ ($packageFeatures[$pkg->id] ?? false) ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">No active packages.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if ($recentLogs->isNotEmpty())
<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-clock-history"></i> Recent Access Logs
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Action</th>
                    <th>Package</th>
                    <th>Actor</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentLogs as $log)
                    <tr>
                        <td class="text-muted">{{ $log->created_at->diffForHumans() }}</td>
                        <td>
                            <span class="badge {{ $log->action === 'feature_enabled' ? 'bg-success' : 'bg-danger' }}">
                                {{ str_replace('_', ' ', ucfirst($log->action)) }}
                            </span>
                        </td>
                        <td>{{ $log->package?->name ?? '—' }}</td>
                        <td>{{ $log->actor_type ?? 'system' }}</td>
                        <td class="text-muted">{{ $log->notes ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
