@extends('layouts.standalone')

@section('title', 'Academic Placement — AccumenAI')
@section('page_title', 'Academic Placement')

@php
    $statusBadge = [
        'active'      => 'text-bg-success',
        'completed'   => 'text-bg-primary',
        'transferred' => 'text-bg-info',
        'dropped'     => 'text-bg-secondary',
    ];
    $mandatory = $placement->selections->where('is_mandatory', true);
    $grouped = $placement->selections->where('is_mandatory', false)->groupBy(fn ($s) => $s->selection_group_id ?: 'ungrouped');
@endphp

@section('content')

<div class="standalone-heading">
    <h4>
        {{ $placement->student?->full_name ?? 'Student' }}
        <span class="badge {{ $statusBadge[$placement->status] ?? 'text-bg-secondary' }} ms-1">{{ ucfirst($placement->status) }}</span>
    </h4>
    <p class="mb-2">
        {{ $placement->academicYear?->name ?? '—' }} ·
        {{ $placement->classGrade?->name ?? '—' }}
        @if ($placement->academicGroup)
            · {{ $placement->academicGroup->name }}
        @endif
    </p>
    <p class="text-muted small mb-2">
        @if ($placement->student?->student_id)Student ID: {{ $placement->student->student_id }} · @endif
        @if ($placement->student?->branch)Branch: {{ $placement->student->branch->name }} · @endif
        {{ $placement->selections->count() }} selected subject(s)
    </p>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.academic.placements.edit', $placement) }}" class="btn btn-sm btn-primary">
            <i class="bi bi-pencil-square me-1"></i>Edit Selection
        </a>
        <a href="{{ route('settings.academic.placements.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All Placements
        </a>
    </div>
</div>

@if ($placement->notes)
    <div class="admin-card mb-3">
        <div class="p-3">
            <span class="text-muted small">Notes:</span>
            <span>{{ $placement->notes }}</span>
        </div>
    </div>
@endif

{{-- Mandatory --}}
<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-star-fill"></i>
            <span class="fw-semibold">Mandatory Subjects</span>
            <span class="badge text-bg-success badge-soft ms-2">{{ $mandatory->count() }}</span>
        </div>
    </div>
    <div class="p-3 d-flex flex-wrap gap-2">
        @forelse ($mandatory as $selection)
            <span class="badge text-bg-light border px-3 py-2">
                <i class="bi bi-check2-circle text-success me-1"></i>{{ $selection->subject?->name ?? 'Subject removed' }}
            </span>
        @empty
            <span class="text-muted small">No mandatory subjects selected.</span>
        @endforelse
    </div>
</div>

{{-- Optional / elective --}}
@foreach ($grouped as $key => $selections)
    @php
        $isGroup = $key !== 'ungrouped';
        $first = $selections->first();
        $group = $first?->selectionGroup;
    @endphp
    <div class="admin-card mb-3">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-collection"></i>
                <span class="fw-semibold">{{ $isGroup && $group ? $group->name : 'Optional / Elective Subjects' }}</span>
                @if ($isGroup && $group)
                    <span class="badge text-bg-light border ms-2">{{ $group->selection_type }}</span>
                    <span class="text-muted small ms-2">Choose {{ $group->minimum_selection ?? 0 }} – {{ $group->maximum_selection ?? $selections->count() }} subject(s)</span>
                @endif
                <span class="badge text-bg-secondary badge-soft ms-2">{{ $selections->count() }} selected</span>
            </div>
        </div>
        <div class="p-3 d-flex flex-wrap gap-2">
            @foreach ($selections as $selection)
                <span class="badge text-bg-light border px-3 py-2">
                    <i class="bi bi-check2-circle text-primary me-1"></i>{{ $selection->subject?->name ?? 'Subject removed' }}
                </span>
            @endforeach
        </div>
    </div>
@endforeach

@endsection