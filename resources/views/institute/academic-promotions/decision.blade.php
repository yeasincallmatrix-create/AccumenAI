@extends('layouts.standalone')

@section('title', 'Promotion Decision — AccumenAI')
@section('page_title', 'Promotion Decision')

@php
    $decisionBadge = [
        'pending'  => ['Pending', 'text-bg-secondary'],
        'review'   => ['In Review', 'text-bg-info'],
        'approved' => ['Approved', 'text-bg-success'],
    ];
    $verdictBadge = [
        'promoted'     => ['Promoted', 'text-bg-success'],
        'conditional'  => ['Conditional', 'text-bg-warning'],
        'repeat'       => ['Repeat', 'text-bg-danger'],
        'not_promoted' => ['Not Promoted', 'text-bg-danger'],
        'completed'    => ['Completed', 'text-bg-info'],
        'graduated'    => ['Graduated', 'text-bg-info'],
        'pending'      => ['Pending', 'text-bg-secondary'],
    ];
@endphp

@section('content')

<div class="standalone-heading">
    <h4>Promotion Decision</h4>
    <p>
        <span class="badge {{ $decisionBadge[$decision->status][1] ?? 'text-bg-secondary' }}">{{ $decisionBadge[$decision->status][0] ?? ucfirst($decision->status) }}</span>
        &nbsp;{{ $decision->result?->name ?? ('Result #'.$decision->result_id) }} ·
        {{ $decision->result?->scheme?->academicYear?->name ?? '—' }} · {{ $decision->result?->scheme?->classGrade?->name ?? '—' }}
        @if ($decision->result?->scheme?->academicGroup) · {{ $decision->result->scheme->academicGroup->name }}@endif
    </p>
</div>

@if ($decision->items->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('settings.academic.promotions.decisions.sheet', $decision) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-printer me-1"></i>Print / Preview Sheet
        </a>
        <a href="{{ route('settings.academic.promotions.decisions.export', $decision) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
@endif

@if ($decision->status !== 'approved')
<div class="d-flex flex-wrap gap-2 mb-3">
    @if ($decision->canStartReview())
        <form method="POST" action="{{ route('settings.academic.promotions.decisions.review', $decision) }}">
            @csrf
            <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-eye me-1"></i>Start Review</button>
        </form>
    @endif
    @if ($decision->canSendBackToReview())
        <form method="POST" action="{{ route('settings.academic.promotions.decisions.send-to-review', $decision) }}">
            @csrf
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Send back to pending</button>
        </form>
    @endif
</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Promotion approval:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('settings.academic.promotions.decisions.approve', $decision) }}" id="approveForm">
    @csrf

    <div class="admin-card mb-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <label class="form-label mb-0 fw-semibold" for="target_year">Target Academic Year *</label>
            <select id="target_year" name="target_year_id" class="form-select form-select-sm" style="width:auto" @if ($decision->status === 'approved') disabled @endif>
                <option value="">Select year</option>
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}">{{ $year->name }}@if ($year->is_current) (Current)@endif</option>
                @endforeach
            </select>
            <span class="text-muted small">Promoted / conditional / completed / graduated students get a NEW placement in this year. Source placements are never modified.</span>
        </div>
    </div>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-muted">#</th>
                        <th>Student</th>
                        <th>Source Class</th>
                        <th>GPA</th>
                        <th>Failed</th>
                        <th>Incomplete</th>
                        <th>Verdict</th>
                        <th>Reasons</th>
                        @if ($decision->status !== 'approved')
                            <th style="min-width:260px">Target Class / Group</th>
                        @else
                            <th>Next Placement</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($decision->items as $item)
                        @php
                            $metric = $metrics[$item->placement_id] ?? [];
                            $needs = $item->needsPlacement();
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-semibold">{{ $item->student?->full_name ?? ('Student #'.$item->student_id) }}</span>
                                <div class="text-muted small">{{ $item->placement?->academicYear?->name ?? '' }}</div>
                            </td>
                            <td>
                                {{ $item->placement?->classGrade?->name ?? '—' }}
                                @if ($item->placement?->academicGroup)
                                    <div class="text-muted small">{{ $item->placement->academicGroup->name }}</div>
                                @endif
                            </td>
                            <td>
                                @if (($metric['gpa'] ?? null) !== null)
                                    {{ $metric['gpa'] }}
                                    <div class="text-muted small">{{ $metric['gpa_mode'] ?? '' }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $metric['failed_count'] ?? 0 }}</td>
                            <td>{{ $metric['incomplete_count'] ?? 0 }}</td>
                            <td>
                                <span class="badge {{ $verdictBadge[$item->decision][1] ?? 'text-bg-secondary' }}">{{ $verdictBadge[$item->decision][0] ?? ucfirst($item->decision) }}</span>
                            </td>
                            <td class="small text-muted">
                                @if ($item->reasons)
                                    <ul class="mb-0 ps-3">
                                        @foreach ($item->reasons as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    —
                                @endif
                            </td>
                            @if ($decision->status !== 'approved')
                                <td>
                                    @if ($needs)
                                        <div class="d-flex gap-1">
                                            <select name="targets[{{ $item->placement_id }}][class_grade_id]" class="form-select form-select-sm target-class" data-group-map="groups[{{ $item->placement_id }}]">
                                                <option value="">Select class</option>
                                                @foreach ($classes as $entry)
                                                    <option value="{{ $entry['class_grade']->id }}" @selected($item->target_class_grade_id === $entry['class_grade']->id)>{{ $entry['name'] }}</option>
                                                @endforeach
                                            </select>
                                            <select name="targets[{{ $item->placement_id }}][academic_group_id]" id="groups[{{ $item->placement_id }}]" class="form-select form-select-sm">
                                                <option value="">Whole class</option>
                                            </select>
                                        </div>
                                    @else
                                        <span class="text-muted small">No next placement</span>
                                    @endif
                                </td>
                            @else
                                <td>
                                    @if ($item->nextPlacement)
                                        <a href="{{ route('settings.academic.placements.show', $item->nextPlacement) }}" class="small">
                                            {{ $item->nextPlacement->academicYear?->name ?? '' }} · {{ $item->targetClassGrade?->name ?? '' }}
                                        </a>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($decision->status !== 'approved')
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" type="submit" @if ($decision->items->isEmpty()) disabled @endif>
                <i class="bi bi-check-lg me-1"></i>Approve &amp; Create Next-Year Placements
            </button>
            <a href="{{ route('settings.academic.promotions.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    @endif
</form>

@endsection

@push('scripts')
<script>
(function () {
    var map = @json($classGroupsMap);
    var groupTargets = {{ $decision->items->filter(fn ($i) => $i->needsPlacement())->mapWithKeys(fn ($i) => [$i->placement_id => $i->target_academic_group_id])->toJson() }};
    var form = document.getElementById('approveForm');
    if (!form) { return; }

    form.querySelectorAll('.target-class').forEach(function (select) {
        var targetId = select.getAttribute('data-group-map');
        var groupSel = document.getElementById(targetId);
        var selectedGroup = groupTargets[targetId.replace('groups[', '').replace(']', '')];

        function renderGroups() {
            if (!groupSel) { return; }
            var options = '<option value="">Whole class</option>';
            (map[select.value] || []).forEach(function (g) {
                options += '<option value="' + g.id + '"' + (String(selectedGroup) === String(g.id) ? ' selected' : '') + '>' + g.name + '</option>';
            });
            groupSel.innerHTML = options;
        }

        renderGroups();
        select.addEventListener('change', renderGroups);
    });
})();
</script>
@endpush
