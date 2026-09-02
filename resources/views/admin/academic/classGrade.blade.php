@extends('layouts.admin')

@section('title', 'Academic — ' . $classGrade->name . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $classGrade->name }}
            @if ($classGrade->status)
                <span class="badge text-bg-success ms-1">Active</span>
            @else
                <span class="badge text-bg-secondary ms-1">Inactive</span>
            @endif
        </h4>
        <p class="page-header-desc">
            {{ $level->name }} &middot; {{ $system->name }} &middot; {{ $country->name }}
            &middot; {{ $groups->count() }} group(s)
        </p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.academic.level', $level) }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

@if (! $classGrade->status)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        This class is inactive. Institutes will not see it when configuring groups.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card">
            <div class="card-title mb-3">Class details</div>
            <form method="POST" action="{{ route('admin.academic.classes.update', $classGrade) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $classGrade->name) }}" maxlength="120" required>
                    @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="code">Code</label>
                    <input type="text" id="code" name="code" class="form-control" value="{{ old('code', $classGrade->code) }}" maxlength="60" required>
                    @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="display_order">Display order</label>
                    <input type="number" id="display_order" name="display_order" class="form-control" value="{{ old('display_order', $classGrade->display_order) }}" min="0">
                    @error('display_order')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save details</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-people"></i> Groups / Streams</div>
            </div>
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $group)
                        <tr>
                            <td class="fw-semibold">{{ $group->name }}</td>
                            <td><span class="badge text-bg-light border">{{ $group->code }}</span></td>
                            <td>
                                @if ($group->status)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.academic.groups.toggle', $group) }}" data-ajax-action="1"
                                      data-confirm="{{ $group->status ? 'Disable this group?' : 'Enable this group?' }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No groups yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <hr>
            <div class="card-title mb-3">Add group</div>
            <form method="POST" action="{{ route('admin.academic.groups.store', $classGrade) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-5">
                        <input type="text" name="name" class="form-control" placeholder="e.g. Science" required maxlength="120"
                               value="{{ old('name') }}">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="code" class="form-control" placeholder="Code e.g. SCI" required maxlength="60"
                               value="{{ old('code') }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add group</button>
                    </div>
                </div>
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </form>
        </div>
    </div>
</div>
@endsection