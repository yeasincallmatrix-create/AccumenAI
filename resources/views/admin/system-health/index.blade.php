@extends('layouts.admin')

@section('title', 'System Health — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">System Health Dashboard</h4>
        <p class="page-header-desc">RAM, storage and cache visibility with guarded cleanup actions.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('admin.artisan-commands.index') }}">
            <i class="bi bi-terminal-fill"></i> Artisan Runner
        </a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-3">
    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-cpu-fill"></i> RAM Usage</div>
            </div>
            @if ($ram['total'] > 0)
                <h5 class="mt-2">{{ $ram['used'] }} {{ $ram['unit'] }} / {{ $ram['total'] }} {{ $ram['unit'] }}</h5>
                <div class="progress" style="height:20px;">
                    <div class="progress-bar" role="progressbar" style="width: {{ $ram['percent'] }}%">{{ $ram['percent'] }}%</div>
                </div>
            @else
                <p class="text-muted mb-0">RAM info unavailable on this host.</p>
            @endif
            <p class="text-muted small mb-0 mt-2">PHP {{ $php['version'] }} ({{ $php['sapi'] }}) · memory_limit {{ $php['memory_limit'] }}</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-hdd-fill"></i> Storage Usage</div>
            </div>
            @if ($disk['total'] > 0)
                <h5 class="mt-2">{{ $disk['used'] }} {{ $disk['unit'] }} / {{ $disk['total'] }} {{ $disk['unit'] }}</h5>
                <div class="progress" style="height:20px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $disk['percent'] }}%">{{ $disk['percent'] }}%</div>
                </div>
            @else
                <p class="text-muted mb-0">Storage info unavailable on this host.</p>
            @endif
            <p class="text-muted small mb-0 mt-2">{{ $disk['path'] }}</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-lightning-charge-fill"></i> Cache Status</div>
            </div>
            @foreach ($cacheStatus as $name => $data)
                <div class="d-flex justify-content-between border-bottom py-1">
                    <strong>{{ $name }}</strong>
                    <span>{{ $data['count'] }} files · {{ $data['size'] }} KB</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-person-fill"></i> Account RAM <span class="text-muted small">(PHP memory_limit)</span></div>
            </div>
            @if ($accountRam['limit'] !== null)
                <h5 class="mt-2">{{ $accountRam['used'] }} MB / {{ $accountRam['limit'] }} MB</h5>
                <div class="progress" style="height:20px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ $accountRam['percent'] }}%">{{ $accountRam['percent'] }}%</div>
                </div>
            @else
                <h5 class="mt-2">{{ $accountRam['used'] }} MB <span class="text-muted small">(no PHP limit)</span></h5>
            @endif
            <p class="text-muted small mb-0 mt-2">Current process usage vs configured PHP limit.</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-hdd"></i> Account Storage <span class="text-muted small">(cPanel quota)</span></div>
            </div>
            <h5 class="mt-2">{{ $accountDisk['used_mb'] }} MB / {{ $accountDisk['quota_mb'] }} MB
                @if ($accountDisk['over_quota'])
                    <span class="badge bg-danger ms-1">Over quota</span>
                @endif
            </h5>
            <div class="progress" style="height:20px;">
                <div class="progress-bar {{ $accountDisk['over_quota'] ? 'bg-danger' : 'bg-success' }}" role="progressbar" style="width: {{ $accountDisk['percent'] }}%">{{ $accountDisk['percent'] }}%</div>
            </div>
            <p class="text-muted small mb-0 mt-2">
                {{ $accountDisk['home'] }} · measured {{ $accountDisk['measured_at'] }} (cached 10 min)
                @if ($accountDisk['truncated']) · <span class="text-warning">scan capped — figure is partial</span>@endif
            </p>
        </div>
    </div>
</div>

<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-exclamation-triangle-fill"></i> Actions</div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <form method="POST" action="{{ route('admin.system-health.clear-cache') }}" onsubmit="return confirm('Clear all caches (config, routes, views, events)?')">
            @csrf
            <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-repeat"></i> Clear All Caches</button>
        </form>
        <form method="POST" action="{{ route('admin.system-health.clear-temp') }}" onsubmit="return confirm('Delete temporary files? Dotfiles are preserved.')">
            @csrf
            <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Clear Temp Files</button>
        </form>
    </div>
</div>
@endsection
