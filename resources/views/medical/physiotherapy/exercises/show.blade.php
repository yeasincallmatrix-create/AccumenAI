@extends('layouts.institute')

@section('title', $exercise->name . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0">
            <i class="bi bi-heart-pulse"></i> {{ $exercise->name }}
            @if($exercise->is_active)
                <span class="badge bg-success ms-2">Active</span>
            @else
                <span class="badge bg-secondary ms-2">Inactive</span>
            @endif
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            @if($user && $user->hasPermission('medical_physiotherapy.edit'))
                <a href="{{ route('medical.physiotherapy.exercises.edit', $exercise) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif
            <a href="{{ route('medical.physiotherapy.exercises.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-info-circle"></i> Exercise Details</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="140">Name</th><td>{{ $exercise->name }}</td></tr>
                        <tr><th>Category</th><td>{{ $exercise->category ?? '-' }}</td></tr>
                        <tr><th>Body Area</th><td>{{ $exercise->body_area ?? '-' }}</td></tr>
                        <tr>
                            <th>Difficulty</th>
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
                        </tr>
                        <tr>
                            <th>Reps</th>
                            <td>{{ $exercise->default_reps ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Sets</th>
                            <td>{{ $exercise->default_sets ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Hold (seconds)</th>
                            <td>{{ $exercise->default_hold_seconds ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                @if($exercise->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-list-ol"></i> Default Parameters</div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="fs-2 fw-bold text-primary">{{ $exercise->default_reps ?? '-' }}</div>
                        <small class="text-muted">Reps</small>
                    </div>
                    <div class="mb-3">
                        <div class="fs-2 fw-bold text-info">{{ $exercise->default_sets ?? '-' }}</div>
                        <small class="text-muted">Sets</small>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold text-warning">{{ $exercise->default_hold_seconds ?? '-' }}s</div>
                        <small class="text-muted">Hold</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($exercise->description)
        <div class="row g-3 mt-1">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-journal-text"></i> Description</div>
                    <div class="card-body">
                        <p class="mb-0">{{ $exercise->description }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($exercise->instructions)
        <div class="row g-3 mt-1">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-list-check"></i> Instructions</div>
                    <div class="card-body">
                        <p class="mb-0">{{ $exercise->instructions }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection