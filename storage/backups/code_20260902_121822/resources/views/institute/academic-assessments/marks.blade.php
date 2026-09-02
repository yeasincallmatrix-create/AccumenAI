@extends('layouts.standalone')

@section('title', 'Enter Marks — '.$assessment->name.' — AccumenAI')
@section('page_title', 'Enter Marks')

@php
    $resultBadge = [
        'pass' => 'text-success',
        'fail' => 'text-danger',
        'absent' => 'text-muted',
        'not_entered' => 'text-muted',
    ];
@endphp

@section('content')

<div class="standalone-heading">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <h4 class="mb-0">
            <i class="bi bi-pencil-square me-1"></i>{{ $assessment->name }} · {{ $studentSubject->subject?->name ?? 'Subject' }}
            <span class="badge text-bg-light border ms-1">{{ $studentSubject->passRuleLabel() }}</span>
        </h4>
        <div class="ms-auto d-flex gap-2">
            @if ($assessment->subjects->count() > 1)
                <div style="min-width:220px">
                    <select id="subjectSwitch" class="form-select form-select-sm">
                        @foreach ($assessment->subjects as $subject)
                            <option value="{{ $subject->id }}" @selected($subject->id === $studentSubject->id)>{{ $subject->subject?->name ?? ('Subject #'.$subject->subject_id) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <a href="{{ route('settings.academic.assessments.show', $assessment) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Assessment
            </a>
        </div>
    </div>
    <p class="text-muted small mb-0 mt-1">
        {{ $assessment->academicYear?->name ?? '—' }} · {{ $assessment->classGrade?->name ?? '—' }}
        @if ($assessment->academicGroup) · {{ $assessment->academicGroup->name }} @endif
        · {{ $grid['rows'] ? count($grid['rows']).' eligible student(s)' : '0 eligible students' }}
    </p>
</div>

<div class="alert alert-light border small py-2 mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Leave a component blank to skip it (not entered). Use <strong>Absent</strong> when the student did not appear for this subject.
    Obtained mark <code>0</code> is a real zero. Results shown are derived live from the pass rule <code>{{ $studentSubject->pass_rule }}</code>.
</div>

<form id="marksForm" method="POST" action="{{ route('settings.academic.assessments.marks.store', [$assessment, $studentSubject]) }}">
    @csrf

    @if ($errors->count())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Please fix the following:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php $components = $grid['components']; @endphp
    <div class="admin-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:44px">#</th>
                        <th>Student</th>
                        <th class="text-center" style="width:80px">Status</th>
                        @foreach ($components as $component)
                            <th class="text-center" style="width:104px" title="{{ $component->full_mark }} full · {{ $component->pass_mark }} pass@if ($component->mandatory_pass) · mandatory@endif">
                                {{ $component->component?->name ?? '—' }}
                                <div class="small text-muted fw-normal">/{{ $component->full_mark }}</div>
                            </th>
                        @endforeach
                        <th class="text-center" style="width:90px">Total</th>
                        <th class="text-center" style="width:90px">Result</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grid['rows'] as $i => $entry)
                        @php $placement = $entry['placement']; $result = $entry['result']; @endphp
                        <tr>
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $placement->student?->full_name ?? 'Student #'.$placement->student_id }}</div>
                                @if ($placement->student?->student_id)
                                    <div class="small text-muted">{{ $placement->student->student_id }}</div>
                                @endif
                            </td>
                            <td class="text-center">
                                <select class="form-select form-select-sm marks-status" name="rows[{{ $placement->id }}][status]">
                                    <option value="entered" @selected(! ($entry['absent']))>Entered</option>
                                    <option value="absent" @selected($entry['absent'])>Absent</option>
                                </select>
                            </td>
                            @foreach ($components as $component)
                                @php
                                    $mark = $entry['marks']->get($component->id);
                                    $value = $mark !== null && $mark->status === 'entered' ? rtrim(rtrim(number_format((float) $mark->obtained_mark, 2), '0'), '.') : '';
                                @endphp
                                <td class="text-center">
                                    <input type="number" step="0.01" min="0" max="{{ $component->full_mark }}"
                                           name="rows[{{ $placement->id }}][marks][{{ $component->id }}]"
                                           class="form-control form-control-sm text-center marks-c"
                                           data-student="{{ $placement->id }}"
                                           value="{{ $value }}"
                                           placeholder="{{ $component->full_mark }}">
                                </td>
                            @endforeach
                            <td class="text-center">
                                <span class="fw-semibold marks-total text-primary">{{ $result['total_obtained'] }}</span>
                                <div class="small text-muted">/{{ rtrim(rtrim(number_format($result['total_full'], 2), '0'), '.') }}</div>
                            </td>
                            <td class="text-center">
                                <span class="fw-semibold marks-result {{ $resultBadge[$result['status']] ?? 'text-muted' }}">
                                    {{ match ($result['status']) { 'pass' => 'Pass', 'fail' => 'Fail', 'absent' => 'Absent', 'not_entered' => '—', default => $result['status'] } }}
                                </span>
                                @if ($result['mandatory_failed'])
                                    <div class="small text-danger" title="A mandatory component below its pass mark">Mandatory failed</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $components->count() + 5 }}" class="text-center text-muted py-4">
                                No students are currently placed in this class
                                @if ($assessment->academicGroup)/group @endif for the selected academic year.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($grid['rows'])
            <div class="d-flex justify-content-end p-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Marks</button>
            </div>
        @endif
    </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
    var subjectSwitch = document.getElementById('subjectSwitch');
    if (subjectSwitch) {
        subjectSwitch.addEventListener('change', function () {
            if (window.Monetix && Monetix.loadPage) {
                Monetix.loadPage('{{ url('settings/academic/assessments', $assessment->id).'/marks/' }}' + subjectSwitch.value);
            } else {
                window.location = '{{ route('settings.academic.assessments.show', $assessment) }}?subject=' + subjectSwitch.value;
            }
        });
    }

    var passRule = '{{ $studentSubject->pass_rule }}';
    var components = [];
    @foreach ($components as $component)
        components.push({
            id: {{ $component->id }},
            full: {{ $component->full_mark }},
            pass: {{ $component->pass_mark }},
            mandatory: {{ $component->mandatory_pass ? '1' : '0' }}
        });
    @endforeach

    function studentTotals(tr) {
        var total = 0, complete = true;
        tr.querySelectorAll('.marks-c').forEach(function (input) {
            if (input.disabled) { return; }
            var v = parseFloat(input.value);
            if (isNaN(v)) { complete = false; return; }
            total += v;
        });
        var totalBox = tr.querySelector('.marks-total');
        if (totalBox) {
            totalBox.firstChild.textContent = complete ? String(Math.round(total * 100) / 100) : '';
        }
        return { total: total, complete: complete };
    }

    function toggleRow(tr, status) {
        var disabled = status === 'absent';
        tr.querySelectorAll('.marks-c').forEach(function (input) {
            input.disabled = disabled;
            input.classList.toggle('bg-light', disabled);
        });
        if (disabled) { tr.querySelectorAll('.marks-c').forEach(function (input) { input.value = ''; }); }
        updateResult(tr);
    }

    function mandatoryFailed(tr) {
        var map = {};
        components.forEach(function (c) { map[c.id] = c; });
        tr.querySelectorAll('.marks-c').forEach(function (input) {
            var c = map[input.name.match(/marks\[(\d+)\]$/)[1]];
            if (c && c.mandatory && input.value !== '' && parseFloat(input.value) < c.pass) { return false; }
        });
        return false;
    }

    function updateResult(tr) {
        var status = tr.querySelector('.marks-status');
        var isAbsent = status && status.value === 'absent';
        var resultEl = tr.querySelector('.marks-result');
        var totals = studentTotals(tr);

        if (isAbsent) {
            setResult(resultEl, 'Absent', 'text-muted');
            return;
        }
        if (!totals.complete) {
            setResult(resultEl, '—', 'text-muted');
            return;
        }

        var subjectPass = 0, mandatoryOkay = true;
        components.forEach(function (c) { subjectPass += c.pass; });
        tr.querySelectorAll('.marks-c').forEach(function (input) {
            var id = input.name.match(/marks\[(\d+)\]$/)[1];
            var c = components.find(function (x) { return String(x.id) === id; });
            if (!c || !c.mandatory) { return; }
            var v = parseFloat(input.value);
            if (isNaN(v) || v < c.pass) { mandatoryOkay = false; }
        });

        var totalOk = totals.total >= subjectPass;
        var passed = passRule === 'mandatory_components' ? mandatoryOkay
            : passRule === 'both' ? (totalOk && mandatoryOkay)
            : totalOk;

        setResult(resultEl, passed ? 'Pass' : 'Fail', passed ? 'text-success' : 'text-danger');
    }

    function setResult(el, text, cls) {
        if (!el) { return; }
        el.textContent = text;
        el.className = 'fw-semibold marks-result ' + cls;
    }

    document.querySelectorAll('.marks-status').forEach(function (sel, i) {
        sel.addEventListener('change', function () { toggleRow(sel.closest('tr'), sel.value); });
        if (sel.value === 'absent') { toggleRow(sel.closest('tr'), 'absent'); }
    });
    document.querySelectorAll('.marks-c').forEach(function (input) {
        input.addEventListener('input', function () { updateResult(input.closest('tr')); });
        updateResult(input.closest('tr'));
    });
})();
</script>
@endpush