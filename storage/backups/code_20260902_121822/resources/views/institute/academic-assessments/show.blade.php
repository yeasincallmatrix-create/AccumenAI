@extends('layouts.standalone')

@section('title', 'Academic Assessment — AccumenAI')
@section('page_title', 'Academic Assessment')

@php
    $statusBadge = [
        'draft'     => 'text-bg-secondary',
        'scheduled' => 'text-bg-info',
        'open'      => 'text-bg-success',
        'completed' => 'text-bg-primary',
        'cancelled' => 'text-bg-danger',
    ];
@endphp

@section('content')

<div class="standalone-heading">
    <h4>
        {{ $assessment->name }}
        <span class="badge {{ $statusBadge[$assessment->status] ?? 'text-bg-secondary' }} ms-1">{{ ucfirst($assessment->status) }}</span>
        @if ($assessment->isLocked())
            <span class="badge text-bg-dark ms-1"><i class="bi bi-lock-fill me-1"></i>Locked</span>
        @endif
        @if ($assessment->assessmentType)
            <span class="badge text-bg-light border ms-1">{{ $assessment->assessmentType->name }}</span>
        @endif
    </h4>
    <p class="mb-2">
        {{ $assessment->academicYear?->name ?? '—' }} ·
        {{ $assessment->classGrade?->name ?? '—' }}
        @if ($assessment->academicGroup)
            · {{ $assessment->academicGroup->name }}
        @endif
        @if ($assessment->branch)
            · Branch: {{ $assessment->branch->name }}
        @endif
    </p>
    <p class="text-muted small mb-2">
        @if ($assessment->exam_date)Exam date: {{ $assessment->exam_date->format('d M Y') }} · @endif
        Position: {{ $assessment->display_order }} ·
        {{ $assessment->subjects->count() }} subject(s)
        @if ($assessment->creator) · Created by {{ $assessment->creator->first_name }} {{ $assessment->creator->last_name }}@endif
    </p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('settings.academic.assessments.readiness', $assessment) }}" class="btn btn-sm btn-outline-info">
            <i class="bi bi-clipboard-check me-1"></i>Result Readiness
        </a>
        <a href="{{ route('settings.academic.assessments.marks-sheet', $assessment) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-table me-1"></i>Marks Sheet
        </a>
        @if ($assessment->isLocked())
            <form method="POST" action="{{ route('settings.academic.assessments.unlock', $assessment) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-warning" title="Unlock to allow marks and configuration changes again">
                    <i class="bi bi-unlock me-1"></i>Unlock Assessment
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('settings.academic.assessments.lock', $assessment) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-dark" title="Freeze marks and configuration until explicitly unlocked">
                    <i class="bi bi-lock me-1"></i>Lock Assessment
                </button>
            </form>
            <a href="{{ route('settings.academic.assessments.edit', $assessment) }}" class="btn btn-sm btn-primary">
                <i class="bi bi-pencil-square me-1"></i>Edit Assessment
            </a>
        @endif
        <a href="{{ route('settings.academic.assessments.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All Assessments
        </a>
    </div>
</div>

@if ($assessment->isLocked())
    <div class="alert alert-dark mb-3 py-2">
        <i class="bi bi-lock-fill me-1"></i>
        This assessment is locked. Marks entry and configuration changes are disabled until an authorised user unlocks it.
        @if ($assessment->lockedBy)
            Locked by {{ $assessment->lockedBy->first_name }} {{ $assessment->lockedBy->last_name }}
        @endif
        @if ($assessment->locked_at)
            on {{ $assessment->locked_at->format('d M Y H:i') }}.
        @endif
    </div>
@endif

@if ($assessment->notes)
    <div class="admin-card mb-3">
        <div class="p-3">
            <span class="text-muted small">Notes:</span>
            <span>{{ $assessment->notes }}</span>
        </div>
    </div>
@endif

@forelse ($assessment->subjects as $subjectConfig)
    @php
        $total = $subjectConfig->totalFullMark();
    @endphp
    <div class="admin-card mb-3">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-journal-text"></i>
                <span class="fw-semibold">{{ $subjectConfig->subject?->name ?? ('Subject #'.$subjectConfig->subject_id) }}</span>
                @if ($subjectConfig->subject?->subject_code)
                    <span class="text-muted small ms-2">{{ $subjectConfig->subject->subject_code }}</span>
                @endif
                <span class="badge text-bg-primary badge-soft ms-2">{{ $total }} total marks</span>
            </div>
            <div class="toolbar-actions">
                @if ($assessment->isLocked())
                    <span class="text-muted small me-2"><i class="bi bi-lock me-1"></i>Locked</span>
                @else
                    <a href="{{ route('settings.academic.assessments.marks.store', [$assessment, $subjectConfig]) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil-square me-1"></i>Enter Marks
                    </a>
                @endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Component</th>
                        <th class="text-center" style="width:120px">Full Mark</th>
                        <th class="text-center" style="width:120px">Pass Mark</th>
                        <th class="text-center" style="width:120px">Must Pass</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjectConfig->components as $component)
                        <tr>
                            <td>{{ $component->component?->name ?? 'Component removed' }}</td>
                            <td class="text-center">{{ rtrim(rtrim(number_format((float) $component->full_mark, 2), '0'), '.') }}</td>
                            <td class="text-center">{{ rtrim(rtrim(number_format((float) $component->pass_mark, 2), '0'), '.') }}</td>
                            <td class="text-center">
                                @if ($component->mandatory_pass)
                                    <span class="badge text-bg-success">Yes</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No components configured for this subject.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="admin-card">
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle me-1"></i>No subjects have been added to this assessment yet.
        </p>
    </div>
@endforelse

@endsection