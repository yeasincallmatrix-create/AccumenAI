@extends('layouts.admin')

@section('title', 'Academic Structure — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Academic Structure</h4>
        <p class="page-header-desc">Configure the global education hierarchy: Country → Education System → Level → Class/Grade → Group/Stream.</p>
    </div>
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.academic.index') }}">
        <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:220px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q"
                       placeholder="Search by country name..." value="{{ $q ?? '' }}">
            </div>
            <div class="filter-span flex-shrink-0">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Search</button>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mt-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Country</th>
                    <th>Academic Unit Label</th>
                    <th>Education Systems</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($countries as $country)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $country->name }}</span>
                            <div class="text-muted small">{{ $country->iso2 }}</div>
                        </td>
                        <td>
                            @if ($country->academicUnitLabel())
                                <span class="badge text-bg-light border">{{ $country->academicUnitLabel() }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge text-bg-primary">{{ $country->education_systems_count }}</span> systems
                        </td>
                        <td>
                            @if ($country->status)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.academic.country', $country) }}">
                                <i class="bi bi-pencil"></i> Configure
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No countries found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection