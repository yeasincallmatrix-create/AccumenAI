@extends('layouts.institute')

@section('title', 'Employees — HR')
@section('page_title', 'Employees')

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Employees <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Master employee profiles — industry-neutral, tenant and branch isolated.</p>
    </div>
    @if ($canCreate)
        <a href="{{ route('hr.employees.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Employee</a>
    @endif
</div>

@include('hr._tabs')

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('hr.employees.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Code, name, phone or email">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Branch</label>
                <select class="form-select form-select-sm" name="branch_id" onchange="this.form.submit()">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string)($filters['branch_id'] ?? '') === (string)$branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Department</label>
                <select class="form-select form-select-sm" name="department_id" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected((string)($filters['department_id'] ?? '') === (string)$dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Designation</label>
                <select class="form-select form-select-sm" name="designation_id" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($designations as $des)
                        <option value="{{ $des->id }}" @selected((string)($filters['designation_id'] ?? '') === (string)$des->id)>{{ $des->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="employment_status" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['employment_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" name="employment_type" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(($filters['employment_type'] ?? '') === $type)>{{ ucwords(str_replace('_',' ', $type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('hr.employees.index') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Employee</th>
                    <th>Code</th>
                    <th>Branch</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Status</th>
                    <th>Type</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td class="text-muted">{{ $employees->firstItem() + $loop->index }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if ($employee->profile_photo)
                                    <img src="{{ Storage::url($employee->profile_photo) }}" class="avatar-sm rounded" alt="">
                                @else
                                    <span class="avatar-circle avatar-initials">{{ strtoupper(substr($employee->display_name, 0, 1)) }}</span>
                                @endif
                                <div>
                                    <a href="{{ route('hr.employees.show', $employee) }}" class="fw-semibold text-decoration-none">{{ $employee->display_name }}</a>
                                    <div class="text-muted small">{{ $employee->email ?? $employee->phone ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td><code>{{ $employee->employee_code }}</code></td>
                        <td>{{ $employee->branch?->name ?? '—' }}</td>
                        <td>{{ $employee->department?->name ?? '—' }}</td>
                        <td>{{ $employee->designation?->name ?? '—' }}</td>
                        <td><span class="badge {{ $employee->employment_status === 'active' ? 'text-bg-success' : ($employee->employment_status === 'suspended' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ ucfirst($employee->employment_status) }}</span></td>
                        <td>{{ $employee->employment_type ? ucwords(str_replace('_',' ', $employee->employment_type)) : '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            @if ($canUpdate)
                                <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No employees found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($employees->hasPages())
        <div class="p-2 border-top">{{ $employees->links() }}</div>
    @endif
</div>

@endsection
