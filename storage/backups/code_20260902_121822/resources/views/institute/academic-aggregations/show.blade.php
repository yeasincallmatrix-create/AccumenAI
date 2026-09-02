@extends('layouts.standalone')

@section('title', $scheme->name.' — Aggregation Scheme — AccumenAI')
@section('page_title', 'Aggregation Scheme')

@php
    $statusBadge = [
        'draft'    => 'text-bg-secondary',
        'active'   => 'text-bg-success',
        'archived' => 'text-bg-light',
    ];
    $aggStatus = [
        'computed'     => ['Computed', 'text-success'],
        'incomplete'   => ['Incomplete', 'text-warning'],
        'absent_only'  => ['Absent', 'text-muted'],
        'not_eligible' => ['—', 'text-muted'],
    ];
    $entryStatus = [
        'entered'     => ['Entered', 'text-success'],
        'absent'      => ['Absent', 'text-muted'],
        'not_entered' => ['Not entered', 'text-warning'],
    ];
@endphp

@section('content')

<div class="standalone-heading">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <h4 class="mb-0">{{ $scheme->name }}</h4>
        <span class="badge {{ $statusBadge[$scheme->status] ?? 'text-bg-secondary' }}">{{ ucfirst($scheme->status) }}</span>
        <span class="badge {{ $scheme->weightIsValid() ? 'text-bg-success' : 'text-bg-warning' }}">Total weight: {{ $scheme->totalWeight() }}%</span>
    </div>
    <p class="mb-2">
        {{ $scheme->academicYear?->name ?? '—' }} ·
        {{ $scheme->classGrade?->name ?? '—' }}
        @if ($scheme->academicGroup)
            · {{ $scheme->academicGroup->name }}
        @endif
        @if ($scheme->branch)
            · Branch: {{ $scheme->branch->name }}
        @endif
    </p>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.academic.aggregations.edit', $scheme) }}" class="btn btn-sm btn-primary">
            <i class="bi bi-pencil-square me-1"></i>Edit Scheme
        </a>
        <a href="{{ route('settings.academic.aggregations.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All Schemes
        </a>
    </div>
</div>

{{-- Weightage config --}}
<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-pie-chart"></i>
            <span class="fw-semibold">Configured Weightage</span>
            <span class="text-muted small ms-2">Manually entered — preserved exactly.</span>
        </div>
    </div>
    @if (! $scheme->weightIsValid())
        <div class="alert alert-warning rounded-0 mb-0 border-0 small">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Current total weight: <strong>{{ $scheme->totalWeight() }}%</strong>. Weight must total 100% before the aggregate can be calculated.
        </div>
    @endif
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Assessment</th>
                    <th class="text-center" style="width:140px">Weight</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($scheme->items as $item)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td>
                            <span class="fw-semibold">{{ $item->assessment?->name ?? ('Assessment #'.$item->academic_assessment_id) }}</span>
                            @if ($item->assessment?->assessmentType)
                                <span class="badge text-bg-light border ms-1">{{ $item->assessment->assessmentType->name }}</span>
                            @endif
                        </td>
                        <td class="text-center fw-semibold">{{ rtrim(rtrim(number_format($item->weight, 2), '0'), '.') }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">No assessments configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Derived preview --}}
<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-calculator"></i>
            <span class="fw-semibold">Aggregation Preview</span>
            <span class="text-muted small ms-2">Derived from stored marks — never stored, never alters marks.</span>
        </div>
    </div>
    <div class="p-3">
        @if ($coveredSubjects->isEmpty())
            <p class="text-muted mb-0 small">No subjects are covered by this scheme's assessments yet.</p>
        @else
            <div class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label class="form-label small mb-1">Subject</label>
                    <select id="previewSubject" class="form-select form-select-sm" style="min-width:220px" onchange="window.location=this.value">
                        <option value="">Select a subject…</option>
                        @foreach ($coveredSubjects as $subject)
                            <option value="{{ route('settings.academic.aggregations.show', ['scheme' => $scheme, 'subject_id' => $subject->id]) }}"
                                    @selected($selectedSubjectId === $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($preview === null)
                <p class="text-muted mb-0 small">Select a subject above to preview the weighted aggregate for every eligible student.</p>
            @elseif (! $preview['weights_valid'])
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Total configured weight is {{ $preview['total_weight'] }}%; the aggregate cannot be calculated until it equals 100%.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                @php
                                    $columns = $preview['rows'][0]['aggregate']['entries'] ?? [];
                                @endphp
                                @foreach ($columns as $entry)
                                    <th class="text-center" title="{{ $entry['assessment']->name }}">
                                        {{ $entry['assessment']->name }}
                                        <div class="small text-muted fw-normal">W {{ rtrim(rtrim(number_format($entry['original_weight'], 2), '0'), '.') }}%</div>
                                    </th>
                                @endforeach
                                <th class="text-center">Aggregate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($preview['rows'] as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row['student']?->full_name ?? 'Student #'.$row['placement']->student_id }}</div>
                                        @if ($row['student']?->student_id)
                                            <div class="small text-muted">{{ $row['student']->student_id }}</div>
                                        @endif
                                    </td>
                                    @if ($row['aggregate'] === null)
                                        <td colspan="{{ max(count($columns), 1) }}" class="text-center text-muted small">
                                            {{ $row['selected'] ? 'Subject covered only in assessments outside this scheme.' : 'Not selected this subject.' }}
                                        </td>
                                        <td class="text-center text-muted">—</td>
                                    @else
                                        @foreach ($row['aggregate']['entries'] as $entry)
                                            @php
                                                $es = $entryStatus[$entry['status']] ?? ['—', 'text-muted'];
                                            @endphp
                                            <td class="text-center">
                                                @if ($entry['status'] === 'entered')
                                                    <span class="fw-semibold">{{ rtrim(rtrim(number_format($entry['percentage'], 2), '0'), '.') }}%</span>
                                                @else
                                                    <span class="text-muted small">{{ $es[0] }}</span>
                                                @endif
                                                <div class="small text-muted">
                                                    @if ($entry['effective_weight'] !== null && abs($entry['effective_weight'] - $entry['original_weight']) > 0.005)
                                                        eff. {{ rtrim(rtrim(number_format($entry['effective_weight'], 2), '0'), '.') }}%
                                                    @endif
                                                </div>
                                            </td>
                                        @endforeach
                                        <td class="text-center">
                                            @php $as = $aggStatus[$row['aggregate']['status']] ?? ['—', 'text-muted']; @endphp
                                            <span class="fw-semibold {{ $as[1] }}">
                                                @if ($row['aggregate']['aggregate'] !== null)
                                                    {{ rtrim(rtrim(number_format($row['aggregate']['aggregate'], 2), '0'), '.') }}%
                                                @else
                                                    {{ $as[0] }}
                                                @endif
                                            </span>
                                            @if ($row['aggregate']['incomplete_reason'])
                                                <div class="small text-warning" title="{{ $row['aggregate']['incomplete_reason'] }}">
                                                    <i class="bi bi-info-circle me-1"></i>{{ $row['aggregate']['incomplete_reason'] }}
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columns) + 2 }}" class="text-center text-muted py-4">
                                        No students are placed in this class
                                        @if ($scheme->academicGroup)/group @endif for the selected year.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>

@endsection