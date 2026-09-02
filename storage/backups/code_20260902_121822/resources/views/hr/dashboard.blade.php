@extends('layouts.institute')

@section('title', 'HR — AccumenAI')
@section('page_title', 'HR Dashboard')

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Human Resources <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Industry-neutral employee master, departments and designations. Foundation for attendance, leave, payroll, recruitment, performance and training.</p>
    </div>
    <div class="page-header-actions d-flex flex-wrap gap-2">
        <a href="{{ route('hr.employees.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-people-fill me-1"></i>Employees</a>
        <a href="{{ route('hr.departments.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-diagram-3 me-1"></i>Departments</a>
        <a href="{{ route('hr.designations.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-award me-1"></i>Designations</a>
    </div>
</div>

@include('hr._tabs')

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $summary['total'] }}</div><div class="text-muted small">Total Employees</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-success">{{ $summary['active'] }}</div><div class="text-muted small">Active</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-secondary">{{ $summary['inactive'] }}</div><div class="text-muted small">Inactive</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-warning">{{ $summary['suspended'] }}</div><div class="text-muted small">Suspended</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $summary['departments'] }}</div><div class="text-muted small">Departments</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $summary['designations'] }}</div><div class="text-muted small">Designations</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $summary['active'] + $summary['inactive'] + $summary['suspended'] }}</div><div class="text-muted small">Lifecycle Master</div></div></div>
</div>

@if ($summary['by_branch'] !== [])
    <div class="admin-card p-3 mb-4">
        <h6 class="mb-2">Employees by Branch</h6>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($summary['by_branch'] as $branchId => $count)
                <span class="badge text-bg-light border">Branch #{{ $branchId }}: {{ $count }}</span>
            @endforeach
        </div>
    </div>
@endif

@if ($recent->isNotEmpty())
    <div class="admin-card mb-4">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <h6 class="mb-0">Recent Employees</h6>
            <a href="{{ route('hr.employees.index') }}" class="btn btn-sm btn-outline-secondary">View all</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent as $emp)
                        <tr>
                            <td><code>{{ $emp->employee_code }}</code></td>
                            <td><a href="{{ route('hr.employees.show', $emp) }}" class="fw-semibold text-decoration-none">{{ $emp->display_name }}</a></td>
                            <td>{{ $emp->department?->name ?? '—' }}</td>
                            <td>{{ $emp->designation?->name ?? '—' }}</td>
                            <td><span class="badge {{ $emp->employment_status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($emp->employment_status) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-danger">{{ $docStats['expired_count'] }}</div><div class="text-muted small">Expired Documents</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-warning">{{ $docStats['expiring_soon_count'] }}</div><div class="text-muted small">Expiring Soon (30d)</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3 text-center"><div class="h5 mb-0 text-info">{{ $docStats['missing_required_count'] }}</div><div class="text-muted small">Employees Missing Required Docs</div></div></div>
</div>

@if($docStats['expired']->isNotEmpty())
<div class="admin-card mb-4">
    <div class="p-3 border-bottom"><h6 class="mb-0 text-danger">Expired Documents</h6></div>
    <div class="table-responsive"><table class="table table-sm mb-0 small">
        <thead><tr><th>Employee</th><th>Document</th><th>Type</th><th>Expiry</th></tr></thead>
        <tbody>
        @foreach($docStats['expired'] as $doc)
            <tr><td><a href="{{ route('hr.employees.show', $doc->documentable_id) }}">{{ $doc->documentable?->display_name ?? 'Employee #'.$doc->documentable_id }}</a> <code>{{ $doc->documentable?->employee_code }}</code></td><td>{{ $doc->title ?? $doc->original_filename }}</td><td>{{ $doc->category?->name }}</td><td class="text-danger">{{ $doc->expiry_date?->format('Y-m-d') }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif

@if($docStats['expiring_soon']->isNotEmpty())
<div class="admin-card mb-4">
    <div class="p-3 border-bottom"><h6 class="mb-0 text-warning">Expiring Soon (30 days)</h6></div>
    <div class="table-responsive"><table class="table table-sm mb-0 small">
        <thead><tr><th>Employee</th><th>Document</th><th>Type</th><th>Expiry</th></tr></thead>
        <tbody>
        @foreach($docStats['expiring_soon'] as $doc)
            <tr><td><a href="{{ route('hr.employees.show', $doc->documentable_id) }}">{{ $doc->documentable?->display_name ?? 'Employee #'.$doc->documentable_id }}</a> <code>{{ $doc->documentable?->employee_code }}</code></td><td>{{ $doc->title ?? $doc->original_filename }}</td><td>{{ $doc->category?->name }}</td><td class="text-warning">{{ $doc->expiry_date?->format('Y-m-d') }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif

@if($docStats['missing']->isNotEmpty())
<div class="admin-card mb-4">
    <div class="p-3 border-bottom"><h6 class="mb-0 text-info">Missing Required Documents</h6></div>
    <div class="table-responsive"><table class="table table-sm mb-0 small">
        <thead><tr><th>Employee</th><th>Missing Types</th></tr></thead>
        <tbody>
        @foreach($docStats['missing'] as $row)
            <tr><td><a href="{{ route('hr.employees.show', $row['employee']->id) }}">{{ $row['employee']->display_name }}</a> <code>{{ $row['employee']->employee_code }}</code></td><td>@foreach($row['missing'] as $cat)<span class="badge bg-light text-dark border me-1">{{ $cat->name }}</span>@endforeach</td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif

@if(isset($hrStats))
<div class="admin-card p-3 mb-4">
    <h6>HR Operations — Pending Actions</h6>
    <div class="row g-2 text-center">
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="h5 mb-0 text-warning">{{ $hrStats['pending_leaves'] }}</div><div class="text-muted small">Pending Leaves</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="h5 mb-0 text-warning">{{ $hrStats['pending_corrections'] }}</div><div class="text-muted small">Attendance Corrections</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="h5 mb-0 text-danger">{{ $hrStats['attendance_exceptions'] }}</div><div class="text-muted small">Today Exceptions</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="h5 mb-0 text-info">{{ $hrStats['pending_documents'] }}</div><div class="text-muted small">Pending Docs</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="h5 mb-0">{{ $hrStats['pending_payrolls'] }}</div><div class="text-muted small">Payroll Periods</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="h5 mb-0">{{ $hrStats['pending_requisitions'] }}</div><div class="text-muted small">Requisitions</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="h5 mb-0">{{ $hrStats['pending_performance'] }}</div><div class="text-muted small">Performance Reviews</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="h5 mb-0">{{ $hrStats['pending_training'] }}</div><div class="text-muted small">Training Enrollments</div></div></div>
    </div>
</div>
@endif

<div class="d-flex gap-2 mb-4">
    <a href="{{ route('hr.self.dashboard') }}" class="btn btn-outline-primary btn-sm">My Self-Service</a>
    <a href="{{ route('hr.manager.dashboard') }}" class="btn btn-outline-primary btn-sm">Manager Dashboard</a>
</div>

<div class="admin-card p-3">
    <h6>Education compatibility</h6>
    <p class="text-muted small mb-0">Teachers remain managed under <code>institute_users</code> + <code>teacher_profiles</code>. An HR employee may optionally link to an <code>institute_user</code> via <code>institute_user_id</code> — this links the HR master to the existing education teacher identity without duplicating records. Teacher workflows are unchanged; HR-2 may reconcile the link.</p>
</div>

@endsection
