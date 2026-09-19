@extends('layouts.admin')

@section('title', $feature->name . ' — Feature Detail — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.features.index') }}" class="text-decoration-none">Features</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $feature->name }}</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $feature->name }}</h4>
        <p class="page-header-desc">
            <code>{{ $feature->feature_key }}</code>
            <span class="badge {{ $feature->status === 'active' ? 'bg-success' : 'bg-secondary' }} ms-2">{{ ucfirst($feature->status) }}</span>
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('admin.features.index', ['module' => $feature->module_key]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> {{ ucfirst($feature->module_key) }} features
        </a>
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
                        <td>
                            <a href="{{ route('admin.features.index', ['module' => $feature->module_key]) }}" class="text-decoration-none">
                                <code>{{ $feature->module_key }}</code>
                            </a>
                        </td>
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
                    <tr>
                        <td class="text-muted">Description</td>
                        <td>{{ $feature->description ?? '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info">
                    <i class="bi bi-box-seam"></i> Package Coverage
                    <span class="badge bg-primary ms-2">{{ $packages->filter(fn ($p) => $packageFeatures[$p->id] ?? false)->count() }} / {{ $packages->count() }}</span>
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
                            @php
                                $isEnabled = $packageFeatures[$pkg->id] ?? false;
                            @endphp
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $pkg->name }}</span>
                                    <br><small class="text-muted">{{ $pkg->slug }}</small>
                                </td>
                                <td class="text-center">
                                    @if ($isEnabled)
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Enabled</span>
                                    @else
                                        <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Disabled</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.features.toggle-package', ['feature_key' => $feature->feature_key, 'package_id' => $pkg->id]) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="enabled" value="{{ $isEnabled ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-sm {{ $isEnabled ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                            {{ $isEnabled ? 'Disable' : 'Enable' }}
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

<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-building"></i> Institute Overrides
            <span class="badge bg-primary ms-2">{{ $instituteOverrides->count() }} active</span>
        </div>
    </div>

    <div class="p-3">
        <form method="POST" action="{{ route('admin.features.institute-override.add', $feature->feature_key) }}" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label mb-1">Institute</label>
                <select name="institute_id" class="form-select form-select-sm" required>
                    <option value="">-- Select institute --</option>
                    @foreach($institutes as $inst)
                        <option value="{{ $inst->id }}">{{ $inst->name }} ({{ $inst->industry }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">State</label>
                <select name="enabled" class="form-select form-select-sm" required>
                    <option value="1">Grant (enable)</option>
                    <option value="0">Deny (disable)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Reason</label>
                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason (optional)" maxlength="255">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Add Override</button>
            </div>
        </form>
    </div>

    @if($instituteOverrides->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Institute</th>
                        <th class="text-center" style="width:100px">State</th>
                        <th>Granted By</th>
                        <th>Reason</th>
                        <th class="text-center" style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($instituteOverrides as $override)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $override->institute?->name ?? 'Unknown' }}</span>
                                <br><small class="text-muted">{{ $override->institute?->industry ?? '' }}</small>
                            </td>
                            <td class="text-center">
                                @if($override->enabled)
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Enabled</span>
                                @else
                                    <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Disabled</span>
                                @endif
                            </td>
                            <td>
                                @if($override->overriddenBy)
                                    {{ $override->overriddenBy->first_name }} {{ $override->overriddenBy->last_name }}
                                @else
                                    <span class="text-muted">System</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $override->reason ?? '—' }}</td>
                            <td class="text-center">
                                <form method="POST"
                                      action="{{ route('admin.features.institute-override.remove', ['feature_key' => $feature->feature_key, 'institute_id' => $override->institute_id]) }}"
                                      onsubmit="return confirm('Remove override?')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="p-4 text-center text-muted">
            <i class="bi bi-building fs-3 d-block mb-2"></i>
            No institute overrides. This feature follows the package default for all institutes.
        </div>
    @endif
</div>

<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-clock-history"></i> Recent Activity
        </div>
    </div>
    @if ($recentLogs->isNotEmpty())
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
    @else
        <div class="p-4 text-center text-muted">
            <i class="bi bi-clock fs-3 d-block mb-2"></i>
            No recent activity for this feature.
        </div>
    @endif
</div>
@endsection
