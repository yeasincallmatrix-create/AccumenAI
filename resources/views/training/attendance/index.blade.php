@extends('layouts.institute')
@section('title', 'Attendance — Training')
@section('page_title', 'Attendance')
@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Attendance</li>
    </ol>
</nav>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4 class="mb-1">Training Attendance</h4>
        <p class="text-muted small mb-0">Monthly calendar — each trainee per day. Checked = Present.</p>
    </div>
    <a href="{{ route('exams.index') }}?batch_id={{ $selectedBatchId ?? '' }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-clipboard-check me-1"></i> Add Exam</a>
</div>

@php
    use Carbon\Carbon;

    $batchId = request('batch_id', $selectedBatchId ?? null);
    $viewMode = request('view_mode', $viewMode ?? 'month');

    if ($viewMode == 'week') {
        $weekDate = request('week_date', $weekDate ?? now()->toDateString());
        $carbonDate = Carbon::parse($weekDate);
        $prevWeek = $carbonDate->copy()->subWeek()->format('Y-m-d');
        $nextWeek = $carbonDate->copy()->addWeek()->format('Y-m-d');
        $displayLabel = 'Week of ' . $carbonDate->copy()->startOfWeek(Carbon::MONDAY)->format('M d') . ' – ' . $carbonDate->copy()->endOfWeek(Carbon::SUNDAY)->format('M d, Y');
    } else {
        $month = request('month', $month ?? now()->format('Y-m'));
        $carbonMonth = Carbon::parse($month . '-01');
        $prevMonth = $carbonMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $carbonMonth->copy()->addMonth()->format('Y-m');
        $displayLabel = $carbonMonth->format('F Y');
    }
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="btn-group" role="group">
        <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'month', 'week_date' => null]) }}"
           class="btn btn-sm {{ ($viewMode ?? 'month') == 'month' ? 'btn-primary' : 'btn-outline-secondary' }}">
            Month
        </a>
        <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'week', 'week_date' => $weekDate ?? now()->toDateString()]) }}"
           class="btn btn-sm {{ ($viewMode ?? 'month') == 'week' ? 'btn-primary' : 'btn-outline-secondary' }}">
            Week
        </a>
    </div>
    <div class="d-flex align-items-center gap-2">
        @if($viewMode == 'week')
            <a href="{{ route('training.attendance.index', ['batch_id' => $batchId, 'view_mode' => 'week', 'week_date' => $prevWeek]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chevron-left"></i>
            </a>
            <span class="fw-semibold">{{ $displayLabel }}</span>
            <a href="{{ route('training.attendance.index', ['batch_id' => $batchId, 'view_mode' => 'week', 'week_date' => $nextWeek]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chevron-right"></i>
            </a>
        @else
            <a href="{{ route('training.attendance.index', ['batch_id' => $batchId, 'view_mode' => 'month', 'month' => $prevMonth]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chevron-left"></i>
            </a>
            <span class="fw-semibold">{{ $displayLabel }}</span>
            <a href="{{ route('training.attendance.index', ['batch_id' => $batchId, 'view_mode' => 'month', 'month' => $nextMonth]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chevron-right"></i>
            </a>
        @endif
    </div>
    @if(!empty($monthYear))
        <span class="badge bg-light text-dark border">{{ $monthYear }}</span>
    @endif
</div>

<div class="admin-card mb-3">
    <form method="GET" action="{{ route('training.attendance.index') }}" class="row g-3 align-items-end">
        <input type="hidden" name="view_mode" value="{{ $viewMode ?? 'month' }}">
        <div class="col-md-4">
            <label class="form-label small">Batch</label>
            <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">— Select batch —</option>
                @foreach($batches as $b)
                    <option value="{{ $b->id }}" @selected($selectedBatchId==$b->id)>{{ $b->name }} ({{ $b->course?->name ?? '' }}) — {{ $b->enrollments_count }} enrolled</option>
                @endforeach
            </select>
        </div>
        @if(($viewMode ?? 'month') == 'week')
            <div class="col-md-3">
                <label class="form-label small">Week</label>
                <input type="date" name="week_date" class="form-control form-control-sm" value="{{ $weekDate ?? now()->toDateString() }}" onchange="this.form.submit()">
            </div>
            <input type="hidden" name="month" value="{{ $month ?? now()->format('Y-m') }}">
        @else
            <div class="col-md-3">
                <label class="form-label small">Month</label>
                <input type="month" name="month" class="form-control form-control-sm" value="{{ $month ?? now()->format('Y-m') }}" onchange="this.form.submit()">
            </div>
            <input type="hidden" name="week_date" value="{{ $weekDate ?? now()->toDateString() }}">
        @endif
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">Load</button>
        </div>
        <div class="col-md-3 text-end">
            <a href="{{ route('batches.show', $selectedBatchId ?? 1) }}" class="btn btn-outline-secondary btn-sm">View Batch</a>
        </div>
    </form>
</div>

<div class="admin-card">
    @if($selectedBatch)
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 small text-muted">
                Batch: <strong>{{ $selectedBatch->name ?? '' }}</strong>
                • {{ ($viewMode ?? 'month') == 'week' ? ($days[0]->toDateString() ?? '') . ' to ' . ($days[count($days)-1]->toDateString() ?? '') : ($month ?? '') }}
                • {{ count($days ?? []) }} days • {{ $trainees->count() }} trainees
            </h6>
        </div>
        <form method="POST" action="{{ route('training.attendance.bulk.store') }}">
            @csrf
            <input type="hidden" name="batch_id" value="{{ $selectedBatchId }}">
            <input type="hidden" name="view_mode" value="{{ $viewMode }}">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="week_date" value="{{ $weekDate }}">
            <div class="table-responsive" style="max-height:70vh;">
                <table class="table table-bordered table-sm align-middle mb-0" style="font-size:0.85rem;">
                    <thead class="table-light" style="position:sticky; top:0; z-index:1;">
                        <tr>
                            <th style="min-width:40px;">#</th>
                            <th style="min-width:200px; position:sticky; left:0; background:#f8f9fa; z-index:2;" class="text-nowrap">Trainee</th>
                            @foreach($days as $dateObj)
                                <th class="text-center" style="min-width:52px;">
                                    {{ $dateObj->format('d') }}<br><small>{{ $dateObj->format('D') }}</small>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trainees as $trainee)
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td style="position:sticky; left:0; background:white; z-index:1;" class="fw-semibold">
                                    {{ $trainee->name }}
                                    <div class="small text-muted" style="font-size:0.75rem;">{{ $trainee->student_id ?? $trainee->reg_no ?? 'N/A' }}</div>
                                </td>
                                @foreach($days as $dateObj)
                                    @php
                                        $dateStr = $dateObj->toDateString();
                                        $isPresent = isset($attendanceMap[$trainee->id][$dateStr]) && $attendanceMap[$trainee->id][$dateStr] === 'present';
                                        $isDisabled = false;
                                        if ($batchStart && $dateStr < $batchStart) { $isDisabled = true; }
                                        if ($batchEnd && $dateStr > $batchEnd) { $isDisabled = true; }
                                    @endphp
                                    <td class="text-center px-0 {{ $isDisabled ? 'bg-light' : '' }}">
                                        <input type="checkbox"
                                               name="attendance[{{ $trainee->id }}][{{ $dateObj->day }}]"
                                               value="present"
                                               {{ $isPresent ? 'checked' : '' }}
                                               {{ $isDisabled ? 'disabled' : '' }}
                                               title="{{ $isDisabled ? 'Outside batch duration' : $dateStr }}"
                                               class="form-check-input"
                                               style="cursor:{{ $isDisabled ? 'not-allowed' : 'pointer' }}; opacity:{{ $isDisabled ? '0.4' : '1' }};">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 d-flex justify-content-between align-items-center">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save All Attendance
                </button>
                <span class="text-muted small">Checked = Present. Unchecked = Absent. Click Save All to persist.</span>
            </div>
        </form>
    @else
        <div class="alert alert-info small">Please select a batch to load attendance.</div>
    @endif
</div>
@endsection
