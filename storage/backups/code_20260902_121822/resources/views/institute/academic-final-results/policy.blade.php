@extends('layouts.standalone')

@section('title', 'Final Result Policy — AccumenAI')
@section('page_title', 'Final Result Policy')

@php
    $statusBadge = [
        'review'    => ['Review', 'text-bg-secondary'],
        'approved'  => ['Approved', 'text-bg-info'],
        'locked'    => ['Locked', 'text-bg-warning'],
        'published' => ['Published', 'text-bg-success'],
    ];
@endphp

@section('content')

<div class="standalone-heading">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <h4 class="mb-0">{{ $scheme->name }}</h4>
        <span class="badge text-bg-light border">{{ ucfirst($scheme->status) }}</span>
        <span class="badge {{ $scheme->weightIsValid() ? 'text-bg-success' : 'text-bg-warning' }}">Total weight: {{ $scheme->totalWeight() }}%</span>
    </div>
    <p class="mb-2">
        {{ $scheme->academicYear?->name ?? '—' }} ·
        {{ $scheme->classGrade?->name ?? '—' }}
        @if ($scheme->academicGroup) · {{ $scheme->academicGroup->name }} @endif
        @if ($scheme->branch) · Branch: {{ $scheme->branch->name }} @endif
    </p>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.academic.final-results.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All Final Results
        </a>
        <a href="{{ route('settings.academic.final-results.readiness', $scheme) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-signpost-2 me-1"></i>Check Readiness
        </a>
        <a href="{{ route('settings.academic.final-results.preflight', $scheme) }}" class="btn btn-sm btn-outline-info">
            <i class="bi bi-rocket-takeoff me-1"></i>Pre-flight Check
        </a>
        <a href="{{ route('settings.academic.grading.preview', ['scheme_id' => $scheme->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-calculator me-1"></i>Calculated Preview
        </a>
    </div>
</div>

@if (! $scheme->weightIsValid())
    <div class="alert alert-warning rounded-0 border-0 mb-3 small">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        Weight must total 100% before the final result can be locked.
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-sliders"></i> <span class="fw-semibold">Policy Configuration</span></div>
            </div>
            <form method="POST" action="{{ route('settings.academic.final-results.policy.update', $policy) }}" class="p-3">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Policy name</label>
                    <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $policy->name) }}" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label">Grade-scale override</label>
                    <select name="grade_scale_id" class="form-select form-select-sm">
                        <option value="">Use the effective scale (ladder)</option>
                        @foreach ($scaleOverrides as $scale)
                            <option value="{{ $scale->id }}" @selected($scale->id === $policy->grade_scale_id)>
                                {{ $scale->name }}
                                @if (method_exists($scale, 'scopeLabel'))({{ $scale->scopeLabel() }})@endif
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Leave empty to use the normal resolution ladder (institute → level → system → country → global).</div>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="absentRenorm" name="absent_renormalization" value="1" @checked($policy->absent_renormalization)>
                    <label class="form-check-label small" for="absentRenorm">
                        Re-normalize weights when a student is absent
                    </label>
                    <div class="form-text">ON (default): remaining entered assessments are re-scaled to 100%. OFF: configured weights stay as-is, so an absence lowers the achievable total.</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="requireApproval" name="require_approval" value="1" @checked($policy->require_approval)>
                    <label class="form-check-label small" for="requireApproval">
                        Require an explicit approval before locking
                    </label>
                </div>
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save policy</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-broadcast"></i> <span class="fw-semibold">Start a Review Cycle</span></div>
            </div>
            <div class="p-3">
                @if ($activeResult)
                    <p class="text-muted small mb-2">A cycle is already in flight for this policy.</p>
                    <a href="{{ route('settings.academic.final-results.show', $activeResult) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-right-square me-1"></i>Continue “{{ $activeResult->name }}”
                    </a>
                @elseif ($preflight !== null && ! $preflight['verdict']['allowed'])
                    <div class="alert alert-danger rounded-0 border-0 small mb-2">
                        <i class="bi bi-x-octagon-fill me-1"></i>
                        <strong>Generation is blocked by the pre-flight gate.</strong> Resolve the issues below (or use the linked pre-flight report) before starting a cycle.
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach ($preflight['verdict']['blocking'] as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <a href="{{ route('settings.academic.final-results.preflight', $scheme) }}" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-rocket-takeoff me-1"></i>View Pre-flight Report
                    </a>
                @else
                    <p class="text-muted small mb-2">Begin a new publish cycle. The derived preview is computed live from the entered marks and never stored until you lock.</p>
                    <form method="POST" action="{{ route('settings.academic.final-results.store', $policy) }}" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. First Term Final 2026" required maxlength="120">
                        <button class="btn btn-sm btn-primary flex-shrink-0"><i class="bi bi-plus-lg me-1"></i>Start cycle</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($history->isNotEmpty())
            <div class="admin-card mt-3">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-clock-history"></i> <span class="fw-semibold">Cycle History</span></div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Cycle</th>
                                <th>Status</th>
                                <th>Students</th>
                                <th>Rows</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $cycle)
                                <tr>
                                    <td class="fw-semibold">{{ $cycle->name }}</td>
                                    <td><span class="badge {{ $statusBadge[$cycle->status][1] ?? 'text-bg-secondary' }}">{{ $statusBadge[$cycle->status][0] ?? ucfirst($cycle->status) }}</span></td>
                                    <td>{{ $cycle->students_count }}</td>
                                    <td>{{ $cycle->rows_count }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('settings.academic.final-results.show', $cycle) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection