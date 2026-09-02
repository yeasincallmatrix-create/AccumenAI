@extends('layouts.institute')

@section('title', 'Attendance — HR')
@section('page_title', 'HR Attendance')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">HR Attendance <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Employee attendance separate from student academic attendance. Daily summaries, corrections, shifts and holidays.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('hr.attendance.daily', ['date' => $date]) }}" class="btn btn-primary btn-sm">Daily View</a>
        <a href="{{ route('hr.attendance.shifts') }}" class="btn btn-outline-secondary btn-sm">Shifts</a>
        <a href="{{ route('hr.attendance.holidays') }}" class="btn btn-outline-secondary btn-sm">Holidays</a>
        <a href="{{ route('hr.attendance.corrections') }}" class="btn btn-outline-secondary btn-sm">Corrections ({{ $summary['pendingCorrections'] }} pending)</a>
    </div>
</div>

@include('hr._tabs')

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('hr.attendance.dashboard') }}" class="filter-layout">
        <div class="filter-search-row flex-wrap">
            <div class="filter-span"><label class="form-label mb-1">Date</label><input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" onchange="this.form.submit()"></div>
            <div class="filter-span"><label class="form-label mb-1">Branch</label><select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All</option>@foreach($branches as $b)<option value="{{ $b->id }}" @selected((string)($filters['branch_id']??'') === (string)$b->id)>{{ $b->name }}</option>@endforeach</select></div>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $summary['total'] }}</div><div class="text-muted small">Total</div></div></div>
    <div class="col-md-2"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-success">{{ $summary['present'] }}</div><div class="text-muted small">Present</div></div></div>
    <div class="col-md-2"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-warning">{{ $summary['late'] }}</div><div class="text-muted small">Late</div></div></div>
    <div class="col-md-2"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-danger">{{ $summary['absent'] }}</div><div class="text-muted small">Absent</div></div></div>
    <div class="col-md-2"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-info">{{ $summary['leave'] }}</div><div class="text-muted small">Leave</div></div></div>
    <div class="col-md-2"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $summary['pendingCorrections'] }}</div><div class="text-muted small">Pending Corrections</div></div></div>
</div>

<div class="admin-card p-3">
    <h6>Note</h6><p class="small text-muted mb-0">Unrecorded days are NOT automatically marked absent. Reports count only recorded rows. Holidays/weekends are derived from shift working days and hr_holidays.</p>
</div>
@endsection
