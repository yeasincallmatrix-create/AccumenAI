@extends('layouts.institute')
@section('title', 'Marks — Training')
@section('page_title', 'Marks')
@section('content')
@php
    $components = $components ?? ['active' => false];
    $subjects = $subjects ?? collect();
    $selectedSubjectId = $selectedSubjectId ?? null;
    $isOverall = $isOverall ?? false;
    $useComponents = $components['active'] ?? false;
    $showWritten = $useComponents && ($components['written']['active'] ?? false);
    $showPractical = $useComponents && ($components['practical']['active'] ?? false);
    $showViva = $useComponents && ($components['viva']['active'] ?? false);
    $fullMarks = $selectedExam->full_marks ?? 0;
    $passMarks = $selectedExam->pass_marks ?? 0;
    $colCount = 3 + ($showWritten ? 1 : 0) + ($showPractical ? 1 : 0) + ($showViva ? 1 : 0) + ($useComponents ? 1 : 2);
    $selectedSubject = (!$isOverall && $selectedSubjectId) ? $subjects->firstWhere('id', $selectedSubjectId) : null;
@endphp
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Marks</li>
    </ol>
</nav>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4 class="mb-1">Training Marks</h4>
        <p class="text-muted small mb-0">Select exam & subject, enter obtained marks per trainee, auto Pass/Fail.</p>
    </div>
    <a href="{{ route('training.results.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart me-1"></i> View Results</a>
</div>
<div class="admin-card mb-3">
    <form method="GET" action="{{ route('training.marks.index') }}" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label small">Exam</label>
            <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">— Select exam —</option>
                @foreach($exams as $ex)
                    <option value="{{ $ex->id }}" @selected($selectedExamId==$ex->id)>{{ $ex->title ?? 'Exam #'.$ex->id }} — {{ $ex->batch?->name ?? '' }} (Pass: {{ $ex->pass_marks }}/{{ $ex->full_marks }})</option>
                @endforeach
            </select>
        </div>
        @if($selectedExam && $subjects->isNotEmpty())
        <div class="col-md-4">
            <label class="form-label small">Subject</label>
            <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0" @selected($isOverall ?? false)>— Overall —</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(!($isOverall ?? false) && $selectedSubjectId==$subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>
        @else
        <input type="hidden" name="subject_id" value="{{ $selectedSubjectId ?? '' }}">
        @endif
        <div class="col-md-{{ $selectedExam && $subjects->isNotEmpty() ? 3 : 7 }}">
            <button type="submit" class="btn btn-primary btn-sm w-100">Load Trainees</button>
        </div>
    </form>
</div>
@if($selectedExam)
<div class="admin-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="mb-0">
            Marks Entry — {{ $selectedExam->title }}
            @if($isOverall ?? false)
                <span class="badge text-bg-secondary ms-1">Overall (avg)</span>
            @elseif($selectedSubject)
                <span class="badge text-bg-primary ms-1">{{ $selectedSubject->name }}</span>
            @endif
            <span class="text-muted small">Pass: {{ $passMarks }} / {{ $fullMarks }}</span>
        </h6>
        <div class="d-flex flex-wrap gap-1">
            @if($showWritten)
                <span class="badge text-bg-info">Written {{ rtrim(rtrim(number_format($components['written']['percent'], 2), '0'), '.') }}% weight</span>
            @endif
            @if($showPractical)
                <span class="badge text-bg-success">Practical {{ rtrim(rtrim(number_format($components['practical']['percent'], 2), '0'), '.') }}% weight</span>
            @endif
            @if($showViva)
                <span class="badge text-bg-warning">Viva {{ rtrim(rtrim(number_format($components['viva']['percent'], 2), '0'), '.') }}% weight</span>
            @endif
            <span class="badge text-bg-light">{{ $trainees->count() }} trainees</span>
        </div>
    </div>
    <form method="POST" action="{{ route('training.marks.store') }}" id="marks-entry-form">
        @csrf
        <input type="hidden" name="exam_id" value="{{ $selectedExam->id }}">
        <input type="hidden" name="subject_id" value="{{ $selectedSubjectId ?? '' }}">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Trainee</th>
                        @if($showWritten)<th>Written<br><small class="text-muted">/ {{ rtrim(rtrim(number_format($fullMarks, 2), '0'), '.') }}</small></th>@endif
                        @if($showPractical)<th>Practical<br><small class="text-muted">/ {{ rtrim(rtrim(number_format($fullMarks, 2), '0'), '.') }}</small></th>@endif
                        @if($showViva)<th>Viva<br><small class="text-muted">/ {{ rtrim(rtrim(number_format($fullMarks, 2), '0'), '.') }}</small></th>@endif
                        <th>{{ $useComponents ? ($selectedSubjectId ? 'Total' : 'Average') : 'Obtained Marks' }}<br><small class="text-muted">{{ $selectedSubjectId ? 'subject sum' : 'avg of subjects' }}</small></th>
                        <th>Pass/Fail</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($trainees as $i => $row)
                    @php $t = $row->trainee; @endphp
                    <tr @if($row->is_overall ?? false) data-overall="1" @endif>
                        <td class="text-muted">{{ $i+1 }}</td>
                        <td class="fw-semibold">{{ trim(($t->first_name ?? '').' '.($t->last_name ?? '')) ?: ('Trainee #'.$row->trainee_id) }} <div class="small text-muted">{{ $t->email ?? '' }}</div></td>
                        @if($showWritten)
                            <td style="width:130px">
                                <input type="number" name="written[{{ $row->trainee_id }}]" value="{{ $row->written }}" class="form-control form-control-sm marks-input" min="0" max="{{ $fullMarks }}" step="0.5" data-pass="{{ $passMarks }}" data-full="{{ $fullMarks }}" title="Max {{ rtrim(rtrim(number_format($fullMarks, 2), '0'), '.') }}" @if($row->is_overall ?? false) readonly @endif>
                            </td>
                        @endif
                        @if($showPractical)
                            <td style="width:130px">
                                <input type="number" name="practical[{{ $row->trainee_id }}]" value="{{ $row->practical }}" class="form-control form-control-sm marks-input" min="0" max="{{ $fullMarks }}" step="0.5" data-pass="{{ $passMarks }}" data-full="{{ $fullMarks }}" title="Max {{ rtrim(rtrim(number_format($fullMarks, 2), '0'), '.') }}" @if($row->is_overall ?? false) readonly @endif>
                            </td>
                        @endif
                        @if($showViva)
                            <td style="width:130px">
                                <input type="number" name="viva[{{ $row->trainee_id }}]" value="{{ $row->viva }}" class="form-control form-control-sm marks-input" min="0" max="{{ $fullMarks }}" step="0.5" data-pass="{{ $passMarks }}" data-full="{{ $fullMarks }}" title="Max {{ rtrim(rtrim(number_format($fullMarks, 2), '0'), '.') }}" @if($row->is_overall ?? false) readonly @endif>
                            </td>
                        @endif
                        <td style="width:180px">
                            @if($useComponents)
                                <input type="text" class="form-control form-control-sm total-display bg-light" readonly
                                       value="{{ $row->obtained !== null ? rtrim(rtrim(number_format($row->obtained, 2), '0'), '.') : '' }}"
                                       data-pass="{{ $passMarks }}" data-full="{{ $fullMarks }}">
                            @else
                                <input type="number" name="marks[{{ $row->trainee_id }}]" value="{{ $row->obtained }}" class="form-control form-control-sm marks-input" min="0" max="{{ $fullMarks }}" step="0.5" data-pass="{{ $passMarks }}" data-full="{{ $fullMarks }}" title="Max {{ rtrim(rtrim(number_format($fullMarks, 2), '0'), '.') }}">
                            @endif
                        </td>
                        <td>
                            <span class="badge result-badge {{
                                $row->result_status === null ? 'text-bg-secondary' :
                                ($row->result_status === 'pass' ? 'text-bg-success' : 'text-bg-danger')
                            }}">{{
                                $row->result_status === null ? '—' : ($row->result_status === 'pass' ? 'Pass' : 'Fail')
                            }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $colCount }}" class="text-center text-muted py-4">No trainees enrolled for this exam's batch.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($trainees->isNotEmpty())
        <div class="mt-3 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i> Save Marks</button>
        </div>
        @endif
    </form>
</div>
@push('scripts')
<script>
(function(){
    var pass = parseFloat({{ $passMarks }});
    function fmtNum(n){
        return n.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
    }
    function rowTotal(row){
        var sum = 0, any = false;
        row.querySelectorAll('input.marks-input').forEach(function(inp){
            if (inp.readOnly) return;
            var v = parseFloat(inp.value);
            if (!isNaN(v) && inp.value !== '') { sum += v; any = true; }
        });
        return any ? Math.round(sum * 100) / 100 : null;
    }
    function validateOverMax(inp){
        var max = parseFloat(inp.getAttribute('max'));
        var v = parseFloat(inp.value);
        var over = inp.value !== '' && !isNaN(v) && !isNaN(max) && v > max;
        inp.classList.toggle('is-invalid', over);
        inp.title = over
            ? 'Max ' + fmtNum(max) + ' — entered ' + fmtNum(v)
            : (inp.dataset.maxHint || ('Max ' + (isNaN(max) ? '' : fmtNum(max))));
        return over;
    }
    function refreshRow(row){
        var total = rowTotal(row);
        var badge = row.querySelector('.result-badge');
        var totalField = row.querySelector('.total-display');
        if (totalField) {
            totalField.value = total === null ? '' : fmtNum(total);
        }
        if (!badge) return;
        if (total === null) {
            badge.textContent = '—';
            badge.className = 'badge result-badge text-bg-secondary';
        } else if (total >= pass) {
            badge.textContent = 'Pass';
            badge.className = 'badge result-badge text-bg-success';
        } else {
            badge.textContent = 'Fail';
            badge.className = 'badge result-badge text-bg-danger';
        }
    }
    document.querySelectorAll('input.marks-input').forEach(function(inp){
        inp.dataset.maxHint = inp.title || '';
        inp.addEventListener('input', function(){
            validateOverMax(this);
            var row = this.closest('tr');
            if (row) refreshRow(row);
        });
        validateOverMax(inp);
    });
    document.querySelectorAll('tr').forEach(function(row){
        if (row.dataset.overall === '1') return;
        if (row.querySelector('input.marks-input')) refreshRow(row);
    });
    var entryForm = document.getElementById('marks-entry-form');
    if (entryForm) {
        entryForm.addEventListener('submit', function(e){
            var anyOver = false;
            entryForm.querySelectorAll('input.marks-input').forEach(function(inp){
                if (validateOverMax(inp)) anyOver = true;
            });
            if (anyOver) {
                e.preventDefault();
            }
        });
    }
})();
</script>
@endpush
@elseif($exams->isEmpty())
<div class="admin-card text-center text-muted py-4">No exams yet. Create an exam from Batches → Exams.</div>
@endif
@if(! $selectedExam)
<div class="admin-card mt-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Exam</th><th>Batch</th><th>Course</th><th>Results</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($examsPaginated ?? $exams as $exam)
                <tr>
                    <td class="fw-semibold">{{ $exam->title ?? 'Exam #'.$exam->id }}</td>
                    <td class="small">{{ $exam->batch?->name ?? '—' }}</td>
                    <td class="small text-muted">{{ $exam->course?->name ?? '—' }}</td>
                    <td>{{ $exam->results_count ?? 0 }}</td>
                    <td class="text-end"><a href="{{ route('training.marks.index', ['exam_id' => $exam->id]) }}" class="btn btn-sm btn-outline-primary">Enter Marks</a> <a href="{{ route('training.exams.show', $exam->id) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No exams yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($examsPaginated))<div class="p-2">{{ $examsPaginated->links() }}</div>@endif
</div>
@endif
@endsection
