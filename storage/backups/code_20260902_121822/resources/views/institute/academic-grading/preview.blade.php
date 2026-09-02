@extends('layouts.standalone')

@section('title', 'Final Result Preview — AccumenAI')
@section('page_title', 'Final Result Preview')

@section('content')

<div class="standalone-heading">
    <h4>Final Result Preview</h4>
    <p>Backend-computed preview: each student's aggregate per subject is converted to a grade, grade point and PASS/FAIL via the effective grade scale, and a GPA is derived. This is a preview only — nothing is published or stored.</p>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('settings.academic.grading.preview') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span flex-shrink-0" style="min-width:260px">
                <label class="form-label mb-1">Aggregation Scheme</label>
                <select class="form-select form-select-sm" name="scheme_id" onchange="this.form.submit()">
                    <option value="">Select a scheme</option>
                    @foreach ($schemes as $item)
                        <option value="{{ $item->id }}" @selected($scheme !== null && $scheme->id === $item->id)>
                            {{ $item->name }} — {{ $item->academicYear?->name }} · {{ $item->classGrade?->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>
</div>

@if ($scheme === null || $preview === null)
    <div class="admin-card">
        <p class="text-center text-muted py-4 mb-0">Choose an aggregation scheme to compute the preview.</p>
    </div>
@else
    {{-- Optional Subject Bonus Configuration — existing persisted GradeScale values only (audit R4) --}}
    @php
        $bonusScale = $effectiveScale ?? null;
    @endphp
    @if ($bonusScale === null)
        <div class="admin-card mb-3 border-warning">
            <div class="table-toolbar bg-warning-subtle">
                <div class="toolbar-info"><i class="bi bi-exclamation-triangle"></i> <span class="fw-semibold">Optional Subject Bonus — No Grade Scale</span></div>
                <span class="badge text-bg-warning">Configuration required</span>
            </div>
            <div class="p-3">
                <p class="small text-muted mb-0">No grade scale is resolved for <strong>{{ $scheme->classGrade?->name ?? 'this class' }}</strong> ({{ $scheme->academicYear?->name ?? '—' }}). Without a persisted <a href="{{ route('settings.academic.grading.index') }}" class="small">Grade Scale</a> no optional-subject bonus can be derived. Configure a scale to enable preview — no values are invented.</p>
            </div>
        </div>
    @else
        <div class="admin-card mb-3">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-star-half"></i> <span class="fw-semibold">Optional Subject Bonus Configuration</span> <span class="text-muted small ms-2">Scale: {{ $bonusScale->name }} · {{ $bonusScale->scopeLabel() }}</span></div>
                <span class="badge text-bg-light border">{{ $bonusScale->isInstituteOverride() ? 'Institute Override' : 'Inherited Default' }}</span>
            </div>
            <div class="p-3">
                <div class="row g-3 small">
                    <div class="col-md-3">
                        <div class="text-muted">Bonus Threshold</div>
                        <div class="fw-semibold fs-6">{{ number_format((float) ($bonusScale->optional_subject_bonus_threshold ?? 2.00), 2) }}</div>
                        <div class="text-muted" style="font-size:0.72rem;">Grade point below this yields 0 bonus</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted">Max GPA (cap)</div>
                        <div class="fw-semibold fs-6">{{ number_format((float) ($bonusScale->max_gpa ?? 5.00), 2) }}</div>
                        <div class="text-muted" style="font-size:0.72rem;">GPA never exceeds this</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted">Multiple Optional Policy</div>
                        <div class="fw-semibold"><span class="badge {{ $bonusScale->multiple_optional_policy === 'best' ? 'text-bg-success' : ($bonusScale->multiple_optional_policy === 'sum' ? 'text-bg-info' : 'text-bg-primary') }}">{{ ucfirst($bonusScale->multiple_optional_policy ?? 'single') }}</span></div>
                        <div class="text-muted" style="font-size:0.72rem;">
                            @if (($bonusScale->multiple_optional_policy ?? 'single') === 'single')
                                Only first optional (lowest subject_id) contributes
                            @elseif (($bonusScale->multiple_optional_policy ?? 'single') === 'best')
                                Maximum bonus among optionals
                            @else
                                Sum of all optional bonuses
                            @endif
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted">Bonus Enabled</div>
                        <div class="fw-semibold">{{ ($bonusScale->optional_subject_bonus_enabled ?? true) ? 'Yes' : 'No' }}</div>
                        <div class="text-muted" style="font-size:0.72rem;">{{ ($bonusScale->optional_subject_bonus_enabled ?? true) ? 'Bonus = max(GP - threshold, 0)' : 'No bonus applied' }}</div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-muted">GPA Mode</div>
                        <div class="fw-semibold">{{ $bonusScale->gpa_mode === 'credit_weighted' ? 'Credit Weighted' : 'Equal Weight' }}</div>
                        <div class="text-muted" style="font-size:0.72rem;">{{ $bonusScale->optional_subject_gpa === 'excluded' ? 'Optionals excluded from GPA' : 'Optionals as configured' }}</div>
                    </div>
                </div>
                <div class="border-top mt-3 pt-2">
                    <div class="text-muted" style="font-size:0.72rem;"><strong>Bonus formula (existing calculation, display only):</strong> <code>bonus = max(grade_point - threshold, 0)</code> per optional subject; GPA = (Σ mandatory GP + Σ bonus) / divisor (credits or count) capped at <code>max_gpa</code>. Values above are from the persisted <code>grade_scales</code> row (<code>optional_subject_bonus_threshold / max_gpa / multiple_optional_policy / optional_subject_bonus_enabled</code>) — no business rule changed here.</div>
                </div>
            </div>
        </div>
    @endif

    <div class="admin-card mb-3">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-clipboard-data"></i> <span class="fw-semibold">{{ $scheme->name }}</span>
                <span class="text-muted small ms-2">
                    Total weight: <span class="badge {{ $preview['weights_valid'] ? 'text-bg-success' : 'text-bg-warning' }}">{{ $preview['total_weight'] }}%</span>
                </span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        @foreach ($preview['subjects'] as $subject)
                            <th>{{ $subject->name }}</th>
                        @endforeach
                        <th>GPA</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preview['rows'] as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['student']->full_name }}</td>
                            @foreach ($row['subjects'] as $entry)
                                @php $result = $entry['result']; @endphp
                                <td>
                                    @if ($result['status'] === 'computed')
                                        <span class="badge {{ $result['subject_status'] === 'PASS' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $result['subject_status'] }}</span>
                                        <span class="small ms-1">{{ $result['grade'] }}</span>
                                        <span class="text-muted small ms-1">({{ $result['aggregate'] }})</span>
                                        @if ($result['gpa']['included'])
                                            <span class="text-muted small ms-1">GP {{ $result['gpa']['grade_point'] }}</span>
                                        @endif
                                    @elseif ($result['status'] === 'incomplete')
                                        <span class="badge text-bg-warning">Pending</span>
                                    @elseif ($result['status'] === 'absent_only')
                                        <span class="badge text-bg-secondary">ABSENT</span>
                                    @elseif ($result['status'] === 'no_grade_scale' || $result['status'] === 'no_band')
                                        <span class="badge text-bg-dark">No grade</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                @if ($row['gpa']['status'] === 'computed')
                                    <strong>{{ $row['gpa']['value'] }}</strong>
                                    <span class="text-muted small ms-1">({{ $row['gpa']['mode'] === 'credit_weighted' ? 'credit' : 'equal' }})</span>
                                @else
                                    <span class="text-muted small">{{ $row['gpa']['reason'] ?? '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($preview['subjects']) + 2 }}" class="text-center text-muted py-4">No eligible students for this scheme.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="mt-3">
    <a href="{{ route('settings.academic.grading.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Grade Scales
    </a>
</div>

@endsection
