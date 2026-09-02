@extends('layouts.admin')

@section('title', $institute->name . ' — Module Access — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $institute->name }} — Module Access</h4>
        <p class="page-header-desc">
            Package: <strong>{{ $institute->package->name ?? '—' }}</strong>
            @if ($institute->package)
                <span class="badge bg-info ms-1">{{ $institute->package->slug }}</span>
            @endif
            — Toggle modules on/off for this institute. Package default applies; override wins.
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.institutes.show', $institute) }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form method="POST" action="{{ route('admin.institutes.modules.update', $institute) }}">
    @csrf
    @method('PUT')

    <div class="admin-card">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-puzzle-fill"></i> All Modules — {{ $allModules->count() }} total
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Module</th>
                        <th class="text-center" style="width:130px">Package</th>
                        <th class="text-center" style="width:130px">Current</th>
                        <th class="text-center" style="width:100px">Enable</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allModules as $module)
                        @php
                            $inPackage = $resolved[$module->key] ?? false;
                            $override = $overrides->get($module->key);
                            $hasOverride = $override !== null;
                            $isEnabled = $hasOverride ? (bool) $override->enabled : $inPackage;
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-semibold">{{ $module->name }}</span>
                                <br><small class="text-muted"><code>{{ $module->key }}</code></small>
                            </td>
                            <td class="text-center">
                                @if($inPackage)
                                    <span class="badge bg-success-subtle text-success">Yes</span>
                                @else
                                    <span class="badge bg-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($isEnabled)
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Enabled</span>
                                @else
                                    <span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> Disabled</span>
                                @endif
                                @if($hasOverride)
                                    <br><small class="text-info">Overridden</small>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $module->key }}" id="mod_{{ $module->key }}" {{ $isEnabled ? 'checked' : '' }}>
                                    <label class="form-check-label" for="mod_{{ $module->key }}">{{ $isEnabled ? 'On' : 'Off' }}</label>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <label class="form-label">Reason (optional)</label>
        <input type="text" name="reason" class="form-control" placeholder="e.g. Enable HR for trial institute" style="max-width:400px">
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Module Access</button>
        <a href="{{ route('admin.institutes.show', $institute) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<div class="row g-3 mt-3">
    <div class="col-md-4">
        <div class="admin-card p-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-box-seam text-success fs-4"></i>
                <div>
                    <div class="fw-semibold">Package Default</div>
                    <small class="text-muted">Included by institute's subscription package.</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card p-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-fill-check text-primary fs-4"></i>
                <div>
                    <div class="fw-semibold">Overridden On</div>
                    <small class="text-muted">Manually enabled despite package not including it.</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card p-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-fill-x text-danger fs-4"></i>
                <div>
                    <div class="fw-semibold">Overridden Off</div>
                    <small class="text-muted">Manually disabled despite package including it.</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
