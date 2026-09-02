@extends('layouts.admin')

@section('title', 'Academic — ' . $system->name . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $system->name }}
            @if ($system->status)
                <span class="badge text-bg-success ms-1">Active</span>
            @else
                <span class="badge text-bg-secondary ms-1">Inactive</span>
            @endif
        </h4>
        <p class="page-header-desc">{{ $country->name }} &middot; {{ $system->code }} &middot; {{ $levels->count() }} level(s)</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.academic.country', $country) }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

@if (! $system->status)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        This system is inactive and will not appear when institutes configure academic structure.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card">
            <div class="card-title mb-3">System details</div>
            <form method="POST" action="{{ route('admin.academic.systems.update', $system) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $system->name) }}" maxlength="120" required>
                    @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="code">Code</label>
                    <input type="text" id="code" name="code" class="form-control" value="{{ old('code', $system->code) }}" maxlength="60" required>
                    @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="display_order">Display order</label>
                    <input type="number" id="display_order" name="display_order" class="form-control" value="{{ old('display_order', $system->display_order) }}" min="0">
                    @error('display_order')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save details</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-layers"></i> Levels (ordered)</div>
            </div>
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th class="text-center">Classes</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($levels as $level)
                        <tr>
                            <td class="text-muted">{{ $level->display_order }}</td>
                            <td class="fw-semibold">{{ $level->name }}</td>
                            <td><span class="badge text-bg-light border">{{ $level->code }}</span></td>
                            <td class="text-center">
                                <a href="{{ route('admin.academic.level', $level) }}" class="badge text-bg-primary text-decoration-none">{{ $classCounts[$level->id] ?? 0 }}</a>
                            </td>
                            <td>
                                @if ($level->status)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.academic.levels.toggle', $level) }}" data-ajax-action="1"
                                      data-confirm="{{ $level->status ? 'Disable this level?' : 'Enable this level?' }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                                </form>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.academic.level', $level) }}">
                                    <i class="bi bi-pencil"></i> Manage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No levels yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <hr>
            <div class="card-title mb-3">Add level</div>
            <form method="POST" action="{{ route('admin.academic.levels.store', $system) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="number" name="display_order" class="form-control" placeholder="Order" required min="0"
                               value="{{ old('display_order', ($levels->max('display_order') ?? 0) + 1) }}">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="name" class="form-control" placeholder="e.g. Secondary" required maxlength="120"
                               value="{{ old('name') }}">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="code" class="form-control" placeholder="Code e.g. SEC" required maxlength="60"
                               value="{{ old('code') }}">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-plus-lg"></i> Add level</button>
                @error('display_order')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </form>
        </div>
    </div>
</div>
@endsection