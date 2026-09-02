@extends('layouts.institute')
@section('title','Manager Dashboard — HR')
@section('content')
<div class="standalone-heading">
    <h4>Manager Dashboard — {{ $manager->display_name }}</h4>
    <p class="text-muted small">Team: {{ $team->count() }} employees</p>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-warning">{{ $pendingLeaves->count() }}</div><div class="text-muted small">Pending Leaves</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-warning">{{ $pendingCorrections->count() }}</div><div class="text-muted small">Pending Corrections</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-danger">{{ $exceptions->count() }}</div><div class="text-muted small">Today Exceptions</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $pendingReviews->count() }}</div><div class="text-muted small">Performance Reviews</div></div></div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h6>Pending Leaves</h6>
            @forelse($pendingLeaves as $l)<div class="small border-bottom py-1">{{ $l->employee->display_name }} — {{ $l->leaveType?->name }} {{ $l->start_date->format('Y-m-d') }} <span class="badge text-bg-warning">pending</span></div>@empty<div class="text-muted small">No pending leaves</div>@endforelse
        </div>
        <div class="admin-card p-3 mt-3">
            <h6>Attendance Today</h6>
            @forelse($teamAttendance as $att)<div class="small">{{ $att->employee->display_name }} — {{ $att->status }}</div>@empty<div class="text-muted small">No records</div>@endforelse
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h6>Pending Corrections</h6>
            @forelse($pendingCorrections as $c)<div class="small border-bottom py-1">{{ $c->employee->display_name }} — {{ $c->correction_date->format('Y-m-d') }} <span class="badge text-bg-warning">{{ $c->status }}</span></div>@empty<div class="text-muted small">None</div>@endforelse
        </div>
        <div class="admin-card p-3 mt-3">
            <h6>Training Enrollments</h6>
            @forelse($trainingTasks as $t)<div class="small">{{ $t->employee->display_name }} — {{ $t->training->title ?? 'Training' }}</div>@empty<div class="text-muted small">None</div>@endforelse
        </div>
    </div>
</div>
@endsection
