@extends('layouts.admin')

@section('title', 'Academic — ' . $country->name . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $country->name }}
            @if ($country->status)
                <span class="badge text-bg-success ms-1">Active</span>
            @else
                <span class="badge text-bg-secondary ms-1">Inactive</span>
            @endif
        </h4>
        <p class="page-header-desc">{{ $country->iso2 }} &middot; {{ $systems->count() }} education system(s)</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.academic.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

@if (! $country->status)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        This country is marked inactive in Geo settings. Its systems will not appear when institutes configure academic structure.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card">
            <div class="card-title mb-3">Academic unit label</div>
            <p class="text-muted small">Used by institutes to name the top-level grouping of learners (e.g. "School", "Institute", "College"). A blank value falls back to the global "Class/ Grade / Year" behavior.</p>
            <form method="POST" action="{{ route('admin.academic.country.update', $country) }}">
                @csrf
                @method('PUT')
                <label class="form-label" for="unit_label">Unit label</label>
                <input type="text" id="unit_label" name="academic_unit_label" class="form-control"
                       value="{{ old('academic_unit_label', $country->academic_unit_label) }}"
                       maxlength="40">
                @error('academic_unit_label')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-primary btn-sm mt-3"><i class="bi bi-check-lg"></i> Save</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-diagram-3"></i> Education systems</div>
            </div>
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="text-center">Levels</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($systems as $system)
                        <tr>
                            <td class="fw-semibold">{{ $system->name }}</td>
                            <td><span class="badge text-bg-light border">{{ $system->code }}</span></td>
                            <td class="text-center">
                                <a href="{{ route('admin.academic.system', $system) }}" class="badge text-bg-primary text-decoration-none">{{ $levelCounts[$system->id] ?? 0 }}</a>
                            </td>
                            <td>
                                @if ($system->status)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.academic.systems.toggle', $system) }}" data-ajax-action="1"
                                      data-confirm="{{ $system->status ? 'Disable this system?' : 'Enable this system?' }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-power"></i>
                                    </button>
                                </form>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.academic.system', $system) }}">
                                    <i class="bi bi-pencil"></i> Manage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No education systems yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <hr>
            <div class="card-title mb-3">Add education system</div>
            <form method="POST" action="{{ route('admin.academic.systems.store', $country) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="name" class="form-control" placeholder="e.g. Secondary Education" required maxlength="120"
                               value="{{ old('name') }}">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="code" class="form-control" placeholder="Code e.g. SE" required maxlength="60"
                               value="{{ old('code') }}">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button>
                    </div>
                </div>
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </form>
        </div>
    </div>
</div>
@endsection