@extends('layouts.institute')
@section('title', 'Marks — Training')
@section('page_title', 'Marks')
@section('content')
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
        <p class="text-muted small mb-0">Select exam, enter obtained marks per trainee, auto Pass/Fail.</p>
    </div>
    <a href="{{ route('training.results.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart me-1"></i> View Results</a>
</div>
<div class="admin-card mb-3">
    <form method="GET" action="{{ route('training.marks.index') }}" class="row g-3 align-items-end">
        <div class="col-md-8">
            <label class="form-label small">Exam</label>
            <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">— Select exam —</option>
                @foreach($exams as $ex)
                    <option value="{{ $ex->id }}" @selected($selectedExamId==$ex->id)>{{ $ex->title ?? 'Exam #'.$ex->id }} — {{ $ex->batch?->name ?? '' }} (Pass: {{ $ex->pass_marks }}/{{ $ex->full_marks }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary btn-sm w-100">Load Trainees</button>
        </div>
    </form>
</div>
@if($selectedExam)
<div class="admin-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">Marks Entry — {{ $selectedExam->title }} <span class="text-muted small">Pass: {{ $selectedExam->pass_marks }} / {{ $selectedExam->full_marks }}</span></h6>
        <span class="badge text-bg-light">{{ $trainees->count() }} trainees</span>
    </div>
    <form method="POST" action="{{ route('training.marks.store') }}">
        @csrf
        <input type="hidden" name="exam_id" value="{{ $selectedExam->id }}">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>#</th><th>Trainee</th><th>Obtained Marks</th><th>Pass/Fail</th></tr></thead>
                <tbody>
                @forelse($trainees as $i => $row)
                    @php $t = $row->trainee; @endphp
                    <tr>
                        <td class="text-muted">{{ $i+1 }}</td>
                        <td class="fw-semibold">{{ trim(($t->first_name ?? '').' '.($t->last_name ?? '')) ?: ('Trainee #'.$row->trainee_id) }} <div class="small text-muted">{{ $t->email ?? '' }}</div></td>
                        <td style="width:160px"><input type="number" name="marks[{{ $row->trainee_id }}]" value="{{ $row->obtained }}" class="form-control form-control-sm marks-input" min="0" max="{{ $selectedExam->full_marks }}" step="0.5" data-pass="{{ $selectedExam->pass_marks }}"></td>
                        <td><span class="badge result-badge {{ ($row->obtained!==null && $row->obtained >= $selectedExam->pass_marks) ? 'text-bg-success' : (($row->obtained!==null) ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ $row->obtained===null ? '—' : ($row->obtained >= $selectedExam->pass_marks ? 'Pass' : 'Fail') }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No trainees enrolled for this exam's batch.</td></tr>
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
document.querySelectorAll('.marks-input').forEach(function(inp){
    inp.addEventListener('input', function(){
        var pass = parseFloat(this.getAttribute('data-pass'));
        var val = parseFloat(this.value);
        var badge = this.closest('tr').querySelector('.result-badge');
        if(isNaN(val) || this.value===''){ badge.textContent='—'; badge.className='badge result-badge text-bg-secondary'; }
        else if(val >= pass){ badge.textContent='Pass'; badge.className='badge result-badge text-bg-success'; }
        else { badge.textContent='Fail'; badge.className='badge result-badge text-bg-danger'; }
    });
});
</script>
@endpush
@elseif($exams->isEmpty())
<div class="admin-card text-center text-muted py-4">No exams yet. Create an exam from Batches → Exams.</div>
@endif
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
                    <td class="text-end"><a href="{{ route('training.marks.index', ['exam_id' => $exam->id]) }}" class="btn btn-sm btn-outline-primary">Enter Marks</a> <a href="{{ route('exams.show', $exam->id) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No exams yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($examsPaginated))<div class="p-2">{{ $examsPaginated->links() }}</div>@endif
</div>
@endsection
