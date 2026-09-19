@extends('layouts.admin')

@section('title', 'Tenant Access — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.institutes.index') }}" class="text-decoration-none">Institutes</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $institute->name }} / Access</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $institute->name }}</h4>
        <p class="page-header-desc">
            Package: <strong>{{ $institute->package->name ?? '—' }}</strong>
            <span class="badge text-bg-secondary badge-soft ms-1">Institute #{{ $institute->id }}</span>
        </p>
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

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-grid-3x3-gap-fill"></i> Effective Features</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th class="text-center">State</th>
                    <th>Source</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($features as $feature)
                    @php $entry = $effectiveMap[$feature->feature_key] ?? ['state' => false, 'source' => 'package', 'reason' => null]; @endphp
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $feature->name }}</span>
                            <br><small class="text-muted"><code>{{ $feature->feature_key }}</code></small>
                        </td>
                        <td class="text-center">
                            @if ($entry['state'])
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Enabled</span>
                            @else
                                <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Disabled</span>
                            @endif
                        </td>
                        <td><code>{{ $entry['source'] }}</code></td>
                        <td class="text-muted small">{{ $entry['reason'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No features in the registry.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-card mb-4">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-plus-circle"></i> Active Grants <span class="badge bg-primary ms-2">{{ $grants->where('status', 'active')->count() }}</span></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Type</th><th>Key</th><th>Expires</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($grants as $grant)
                            <tr>
                                <td><code>{{ $grant->grant_type }}</code></td>
                                <td><code>{{ $grant->grant_key }}</code></td>
                                <td class="small text-muted">{{ $grant->expires_at?->format('Y-m-d') ?? '—' }}</td>
                                <td><span class="badge {{ $grant->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $grant->status }}</span></td>
                                <td class="text-end">
                                    @if ($grant->status === 'active')
                                        <form method="POST" action="{{ route('admin.institutes.grants.revoke', [$institute, $grant]) }}" class="d-inline" onsubmit="return confirm('Revoke this grant?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No grants recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top">
                <h6 class="mb-2">Add Grant</h6>
                <form method="POST" action="{{ route('admin.institutes.grants.store', $institute) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" name="grant_type" required>
                                <option value="feature">feature</option>
                                <option value="module">module</option>
                                <option value="tier">tier</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-sm" name="grant_key" maxlength="100" placeholder="e.g. medical.pharmacy" required>
                        </div>
                        <div class="col-md-6">
                            <input type="date" class="form-control form-control-sm" name="expires_at" title="Expires at (optional)">
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control form-control-sm" name="reason" maxlength="255" placeholder="Reason (optional)">
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Add Grant</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-card mb-4">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-dash-circle"></i> Active Denials <span class="badge bg-danger ms-2">{{ $denials->where('status', 'active')->count() }}</span></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Type</th><th>Key</th><th>Expires</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($denials as $denial)
                            <tr>
                                <td><code>{{ $denial->deny_type }}</code></td>
                                <td><code>{{ $denial->deny_key }}</code></td>
                                <td class="small text-muted">{{ $denial->expires_at?->format('Y-m-d') ?? '—' }}</td>
                                <td><span class="badge {{ $denial->status === 'active' ? 'bg-danger' : 'bg-secondary' }}">{{ $denial->status }}</span></td>
                                <td class="text-end">
                                    @if ($denial->status === 'active')
                                        <form method="POST" action="{{ route('admin.institutes.denials.lift', [$institute, $denial]) }}" class="d-inline" onsubmit="return confirm('Lift this denial?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Lift"><i class="bi bi-check-circle"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No denials recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top">
                <h6 class="mb-2">Add Denial</h6>
                <form method="POST" action="{{ route('admin.institutes.denials.store', $institute) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" name="deny_type" required>
                                <option value="feature">feature</option>
                                <option value="module">module</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-sm" name="deny_key" maxlength="100" placeholder="e.g. medical.pharmacy" required>
                        </div>
                        <div class="col-md-6">
                            <input type="date" class="form-control form-control-sm" name="expires_at" title="Expires at (optional)">
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control form-control-sm" name="reason" maxlength="255" placeholder="Reason (required)" required>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-dash"></i> Add Denial</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
