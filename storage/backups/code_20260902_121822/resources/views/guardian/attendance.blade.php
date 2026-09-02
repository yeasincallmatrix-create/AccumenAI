@extends('guardian.layout')

@section('title', mawa_e('guardian.attendance_title'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">{{ mawa_e('guardian.attendance_title') }}</h1>
        <div class="small text-body-secondary">{{ $student->full_name }} · {{ $student->student_id }}</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.show', $student->id) }}"><i class="bi bi-arrow-left me-1"></i>{{ mawa_e('guardian.back_to_profile') }}</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('guardian.students.attendance', $student->id) }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1">{{ mawa_e('guardian.academic_year') }}</label>
                <select class="form-select form-select-sm" name="academic_year_id">
                    <option value="">— {{ mawa_e('guardian.all_years') }} —</option>
                    @foreach ($years as $y)
                        <option value="{{ $y->id }}" @selected($selectedYear !== null && (int) $selectedYear->id === (int) $y->id)>{{ $y->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">{{ mawa_e('guardian.from') }}</label>
                <input type="date" class="form-control form-control-sm" name="start_date" value="{{ $start->toDateString() }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">{{ mawa_e('guardian.to') }}</label>
                <input type="date" class="form-control form-control-sm" name="end_date" value="{{ $end->toDateString() }}">
            </div>
            <div class="col-12 col-md-2">
                <button class="btn btn-sm btn-primary rounded-pill w-100" type="submit"><i class="bi bi-funnel me-1"></i>{{ mawa_e('guardian.apply') }}</button>
            </div>
        </form>
    </div>
</div>

@if (! $report['valid'])
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>{{ $report['message'] }}</div>
@else
    <div class="row g-3 mb-3">
        @php $t = $report['totals']; @endphp
        <div class="col-6 col-lg-2">
            <div class="card"><div class="card-body py-2 px-3">
                <div class="small text-body-secondary">{{ mawa_e('guardian.total') }}</div>
                <div class="h5 mb-0">{{ $t['total'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card"><div class="card-body py-2 px-3">
                <div class="small text-body-secondary">{{ mawa_e('guardian.present') }}</div>
                <div class="h5 mb-0 text-success">{{ $t['present'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card"><div class="card-body py-2 px-3">
                <div class="small text-body-secondary">{{ mawa_e('guardian.absent') }}</div>
                <div class="h5 mb-0 text-danger">{{ $t['absent'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card"><div class="card-body py-2 px-3">
                <div class="small text-body-secondary">{{ mawa_e('guardian.late') }}</div>
                <div class="h5 mb-0 text-warning">{{ $t['late'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card"><div class="card-body py-2 px-3">
                <div class="small text-body-secondary">{{ mawa_e('guardian.leave') }}</div>
                <div class="h5 mb-0">{{ $t['leave'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card"><div class="card-body py-2 px-3">
                <div class="small text-body-secondary">{{ mawa_e('guardian.present_percent') }}</div>
                <div class="h5 mb-0 text-primary">{{ $t['present_percent'] !== null ? $t['present_percent'].'%' : '—' }}</div>
            </div></div>
        </div>
    </div>

    <div class="small text-body-secondary mb-3"><i class="bi bi-info-circle me-1"></i>{{ mawa_e('guardian.attendance_note') }}</div>

    @if ($records->isNotEmpty())
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr class="text-body-secondary">
                                <th>{{ mawa_e('guardian.date') }}</th>
                                <th>{{ mawa_e('guardian.status') }}</th>
                                <th>{{ mawa_e('guardian.batch') }}</th>
                                <th>{{ mawa_e('guardian.period') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $record)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($record->class_date)->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $record->status === 'present' ? 'success' : ($record->status === 'absent' ? 'danger' : ($record->status === 'late' ? 'warning' : 'secondary')) }}">{{ $record->status }}</span>
                                    </td>
                                    <td>{{ $record->batch?->name ?? mawa_e('guardian.na') }}</td>
                                    <td>
                                        @if ($record->academic_placement !== null)
                                            {{ $record->academic_placement->academicYear?->name }}
                                        @else
                                            <span class="text-body-secondary">{{ mawa_e('guardian.unclassified') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($records->hasPages())
                    <div class="p-2">{{ $records->links() }}</div>
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>{{ mawa_e('guardian.no_attendance') }}</div>
    @endif
@endif
@endsection