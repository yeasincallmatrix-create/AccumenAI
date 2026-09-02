@extends('layouts.admin')

@section('title', 'Academic — ' . $level->name . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $level->name }}
            @if ($level->status)
                <span class="badge text-bg-success ms-1">Active</span>
            @else
                <span class="badge text-bg-secondary ms-1">Inactive</span>
            @endif
        </h4>
        <p class="page-header-desc">{{ $system->name }} &middot; {{ $country->name }} &middot; order {{ $level->display_order }} &middot; {{ $classes->count() }} class(es)</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.academic.system', $system) }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

@if (! $level->status)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        This level is inactive. Institutes will not see it when configuring classes.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card">
            <div class="card-title mb-3">Level details</div>
            <form method="POST" action="{{ route('admin.academic.levels.update', $level) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $level->name) }}" maxlength="120" required>
                    @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="code">Code</label>
                    <input type="text" id="code" name="code" class="form-control" value="{{ old('code', $level->code) }}" maxlength="60" required>
                    @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="display_order">Display order</label>
                    <input type="number" id="display_order" name="display_order" class="form-control" value="{{ old('display_order', $level->display_order) }}" min="0" required>
                    @error('display_order')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save details</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-book"></i> Classes / Grades</div>
            </div>
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="text-center">Groups</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($classes as $classGrade)
                        <tr>
                            <td class="fw-semibold">{{ $classGrade->name }}</td>
                            <td><span class="badge text-bg-light border">{{ $classGrade->code }}</span></td>
                            <td class="text-center">
                                <a href="{{ route('admin.academic.classGrade', $classGrade) }}" class="badge text-bg-primary text-decoration-none">{{ $groupCounts[$classGrade->id] ?? 0 }}</a>
                            </td>
                            <td>
                                @if ($classGrade->status)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.academic.classes.toggle', $classGrade) }}" data-ajax-action="1"
                                      data-confirm="{{ $classGrade->status ? 'Disable this class?' : 'Enable this class?' }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                                </form>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.academic.classGrade', $classGrade) }}">
                                    <i class="bi bi-pencil"></i> Manage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No classes yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <hr>
            <div class="card-title mb-3">Add class</div>
            <form method="POST" action="{{ route('admin.academic.classes.store', $level) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="name" class="form-control" placeholder="e.g. Grade 1" required maxlength="120"
                               value="{{ old('name') }}">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="code" class="form-control" placeholder="Code e.g. G1" required maxlength="60"
                               value="{{ old('code') }}">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="display_order" class="form-control" placeholder="Order" min="0"
                               value="{{ old('display_order', ($classes->max('display_order') ?? 0) + 1) }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add class</button>
                    </div>
                </div>
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('display_order')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </form>
        </div>
    </div>
</div>
@endsection