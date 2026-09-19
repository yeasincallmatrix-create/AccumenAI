@extends('layouts.admin')

@section('title', 'Feature Management — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Feature Management</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Feature Management</h4>
        <p class="page-header-desc">Manage feature availability across subscription packages.</p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-grid-3x3-gap-fill"></i> Feature × Package Matrix
        </div>
        <div class="toolbar-info">
            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Enabled</span>
            <span class="badge bg-secondary ms-1"><i class="bi bi-x-circle"></i> Disabled</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width:180px">Feature</th>
                    <th style="min-width:200px">Description</th>
                    @foreach ($packages as $pkg)
                        <th class="text-center" style="min-width:110px">
                            {{ $pkg->name }}
                            <br><small class="text-muted">{{ $pkg->slug }}</small>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($features as $feature)
                    <tr>
                        <td>
                            <a href="{{ route('admin.features.show', $feature->feature_key) }}" class="text-decoration-none fw-semibold">
                                {{ $feature->name }}
                            </a>
                            <br><small class="text-muted"><code>{{ $feature->feature_key }}</code></small>
                        </td>
                        <td class="text-muted">{{ $feature->description ?? '—' }}</td>
                        @foreach ($packages as $pkg)
                            <td class="text-center">
                                @php
                                    $isEnabled = in_array($feature->feature_key, $packageFeatures[$pkg->id] ?? [], true);
                                @endphp
                                <form method="POST" action="{{ route('admin.features.toggle-package', ['feature_key' => $feature->feature_key, 'package_id' => $pkg->id]) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="enabled" value="{{ $isEnabled ? '0' : '1' }}">
                                    <button type="submit" class="btn btn-sm {{ $isEnabled ? 'btn-success' : 'btn-outline-secondary' }}" title="{{ $isEnabled ? 'Click to disable' : 'Click to enable' }}">
                                        @if ($isEnabled)
                                            <i class="bi bi-check-circle-fill"></i>
                                        @else
                                            <i class="bi bi-x-circle"></i>
                                        @endif
                                    </button>
                                </form>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $packages->count() + 2 }}" class="text-center text-muted py-4">No features registered.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
