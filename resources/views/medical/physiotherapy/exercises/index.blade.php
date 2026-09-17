@extends('layouts.institute')

@section('title', 'Exercise Library — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-heart-pulse"></i> Exercise Library</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.physiotherapy.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            @if($user && $user->hasPermission('medical_physiotherapy.create'))
                <a href="{{ route('medical.physiotherapy.exercises.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Exercise
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Exercise name" value="{{ $search ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" @selected(($categoryFilter ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Body Area</label>
                    <select name="body_area" class="form-select">
                        <option value="">All Areas</option>
                        @foreach($bodyAreas as $key => $label)
                            <option value="{{ $key }}" @selected(($bodyAreaFilter ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Difficulty</label>
                    <select name="difficulty" class="form-select">
                        <option value="">All Levels</option>
                        @foreach($difficulties as $key => $label)
                            <option value="{{ $key }}" @selected(($difficultyFilter ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Body Area</th>
                            <th>Difficulty</th>
                            <th>Reps/Sets/Hold</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exercises as $exercise)
                            <tr>
                                <td>
                                    <a href="{{ route('medical.physiotherapy.exercises.show', $exercise) }}">
                                        <strong>{{ $exercise->name }}</strong>
                                    </a>
                                </td>
                                <td>{{ $exercise->category ?? '-' }}</td>
                                <td>{{ $exercise->body_area ?? '-' }}</td>
                                <td>
                                    @php
                                        $diffColor = match($exercise->difficulty) {
                                            'easy' => 'success',
                                            'moderate' => 'warning',
                                            'hard' => 'danger',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $diffColor }}">{{ ucfirst($exercise->difficulty ?? '-') }}</span>
                                </td>
                                <td>
                                    <small>
                                        {{ $exercise->default_reps ?? '-' }} reps
                                        / {{ $exercise->default_sets ?? '-' }} sets
                                        / {{ $exercise->default_hold_seconds ?? '-' }}s hold
                                    </small>
                                </td>
                                <td>
                                    @if($exercise->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('medical.physiotherapy.exercises.show', $exercise) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        @if($user && $user->hasPermission('medical_physiotherapy.edit'))
                                            <a href="{{ route('medical.physiotherapy.exercises.edit', $exercise) }}" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-heart-pulse fs-2 d-block mb-2"></i>
                                    No exercises found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $exercises->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection