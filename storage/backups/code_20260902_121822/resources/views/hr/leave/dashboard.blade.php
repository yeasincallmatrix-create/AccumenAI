@extends('layouts.institute')

@section('title', 'Leave — HR')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Leave Management <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Annual/sick/casual/unpaid etc. Configurable types, balances, applications, approvals.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('hr.leave.applications.create') }}" class="btn btn-primary btn-sm">Apply Leave</a>
        <a href="{{ route('hr.leave.types') }}" class="btn btn-outline-secondary btn-sm">Types & Policies</a>
        <a href="{{ route('hr.leave.balances') }}" class="btn btn-outline-secondary btn-sm">Balances</a>
        <a href="{{ route('hr.leave.applications') }}" class="btn btn-outline-secondary btn-sm">Applications</a>
    </div>
</div>

@include('hr._tabs')

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $stats['pending'] }}</div><div class="text-muted small">Pending</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-success">{{ $stats['approved'] }}</div><div class="text-muted small">Approved</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $stats['types'] }}</div><div class="text-muted small">Leave Types</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $stats['balances'] }}</div><div class="text-muted small">Balance Records</div></div></div>
</div>

@if($recent->isNotEmpty())
<div class="admin-card">
    <div class="p-3 border-bottom"><h6 class="mb-0">Recent Applications</h6></div>
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($recent as $a)
                    <tr><td>{{ $a->employee->display_name }}</td><td>{{ $a->leaveType->name }}</td><td>{{ $a->start_date->format('Y-m-d') }} → {{ $a->end_date->format('Y-m-d') }} ({{ $a->days_count }}d)</td><td><span class="badge {{ $a->status==='approved'?'text-bg-success':($a->status==='pending'?'text-bg-warning':'text-bg-secondary') }}">{{ $a->status }}</span></td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
