@extends('layouts.institute')

@php $isAcademic = \App\Support\InstituteDomain::isAcademic($institute ?? null); @endphp
@section('title', 'Admissions — AccumenAI')

@php
    $statusBadge = [
        'draft'        => 'bg-secondary',
        'submitted'    => 'bg-info',
        'under_review' => 'bg-warning',
        'approved'     => 'bg-success',
        'rejected'     => 'bg-danger',
        'cancelled'    => 'bg-dark',
        'enrolled'     => 'bg-primary',
        'withdrawn'    => 'bg-secondary',
    ];
    $fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '—';
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Admissions</h4>
        <p class="page-header-desc mb-0">{{ $admissions->total() }} {{ $admissions->total() === 1 ? 'application' : 'applications' }} <span class="badge bg-success ms-2"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Active</span></p>
    </div>
    @if ($user->hasPermission('students.manage'))
        <div class="page-header-actions d-flex gap-2">
            @if ($user->hasPermission('admission.approve'))
                <a href="{{ route('admissions.pending') }}" class="btn btn-outline-warning">
                    <i class="bi bi-hourglass-split me-1"></i>Pending
                </a>
            @endif
            <a href="{{ route('admissions.pipeline') }}" class="btn btn-outline-primary">
                <i class="bi bi-diagram-3-fill me-1"></i>Pipeline
            </a>
            <a href="{{ route('admissions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Application
            </a>
        </div>
    @endif
</div>

@include('students._tabs', ['activeTab' => 'admissions'])

{{-- Quick status chips --}}
<div class="d-flex flex-wrap gap-1 mb-3">
    <a href="{{ route('admissions.index') }}" class="btn btn-sm {{ !$admissionStatus ? 'btn-dark' : 'btn-outline-secondary' }}">All</a>
    @foreach ($statuses as $slug)
        <a href="{{ route('admissions.index', array_merge(request()->except('page'), ['admission_status' => $slug])) }}" class="btn btn-sm {{ (string)$admissionStatus === $slug ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $slug }}</a>
    @endforeach
</div>

<div class="admin-card" data-ajax-table>

    <div class="filter-card">
        <form class="filter-layout" method="GET" action="{{ route('admissions.index') }}">

            <div class="filter-search-row align-items-end">

                <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" name="q" value="{{ $q }}"
                           placeholder="Search name, application #, phone, email…">
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                    <label class="form-label mb-1">Status</label>
                    <select class="form-select form-select-sm" name="admission_status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $slug)
                            <option value="{{ $slug }}" @selected((string) $admissionStatus === $slug)>{{ $slug }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                    <label class="form-label mb-1">Branch</label>
                    <select class="form-select form-select-sm" name="branch_id">
                        <option value="">All branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                    <label class="form-label mb-1">Course</label>
                    <select class="form-select form-select-sm" name="course_id">
                        <option value="">All courses</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((string) $courseId === (string) $course->id)>{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                    <label class="form-label mb-1">Academic year</label>
                    <select class="form-select form-select-sm" name="academic_year_id">
                        <option value="">All years</option>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected((string) $academicYearId === (string) $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admissions.index') }}"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                </div>

            </div>

        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Application</th>
                    <th>Applicant</th>
                    <th>Contact</th>
                    <th>Course</th>
                    <th>Academic year</th>
                    <th>Branch</th>
                    <th>Applied on</th>
                    <th>Status</th>
                    <th class="text-end pe-3">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($admissions as $student)
                    <tr>
                        <td class="ps-3">{{ $student->application_number ?? '—' }}</td>
                        <td>{{ $student->full_name }}</td>
                        <td>
                            <div>{{ $student->phone }}</div>
                            @if ($student->email)
                                <small class="text-muted">{{ $student->email }}</small>
                            @endif
                        </td>
                        <td>{{ $student->appliedCourse?->name ?? '—' }}</td>
                        <td>{{ $student->appliedAcademicYear?->name ?? '—' }}</td>
                        <td>{{ $student->branch?->name ?? '—' }}</td>
                        <td>{{ $fmtDate($student->application_date) }}</td>
                        <td>
                            <span class="badge {{ $statusBadge[$student->admission_status] ?? 'bg-secondary' }}">{{ $student->admission_status }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('admissions.show', $student) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No admission applications found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-3 border-top">
        {{ $admissions->links() }}
    </div>

</div>

{{-- Related options --}}
<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-grid-fill"></i> Related options</div>
    </div>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <a href="{{ route('admissions.pipeline') }}" class="text-decoration-none">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold text-dark"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Pipeline Board</div>
                    <small class="text-muted">Leads → Interested → Applicant → Enrolled</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('admissions.pipeline.report') }}" class="text-decoration-none">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold text-dark"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Funnel Report</div>
                    <small class="text-muted">Conversion rates & drop-offs</small>
                </div>
            </a>
        </div>
        @if($isAcademic)
        <div class="col-6 col-md-3">
            <a href="{{ route('academic.analytics.students') }}" class="text-decoration-none">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold text-dark"><i class="bi bi-people-fill text-success me-2"></i>Student Analytics</div>
                    <small class="text-muted">Cohort, placement & results</small>
                </div>
            </a>
        </div>
        @endif
        @if($isAcademic)
        <div class="col-6 col-md-3">
            <a href="{{ route('academic.analytics.index') }}" class="text-decoration-none">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold text-dark"><i class="bi bi-graph-up text-success me-2"></i>Education Analytics</div>
                    <small class="text-muted">Overview across all education topics</small>
                </div>
            </a>
        </div>
        @endif
        <div class="col-6 col-md-3">
            <a href="{{ route('courses.manage.index') }}" class="text-decoration-none">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold text-dark"><i class="bi bi-journal-bookmark-fill text-warning me-2"></i>Courses</div>
                    <small class="text-muted">Manage offerings</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('batches.index') }}" class="text-decoration-none">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold text-dark"><i class="bi bi-collection-fill text-warning me-2"></i>Batches</div>
                    <small class="text-muted">Sections & schedules</small>
                </div>
            </a>
        </div>
        @if($isAcademic)
        <div class="col-6 col-md-3">
            <a href="{{ route('finance.education.dashboard') }}" class="text-decoration-none">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold text-dark"><i class="bi bi-cash-coin text-success me-2"></i>Education Fees</div>
                    <small class="text-muted">Invoices & payments</small>
                </div>
            </a>
        </div>
        @endif
        <div class="col-6 col-md-3">
            <a href="{{ route('students.index') }}" class="text-decoration-none">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold text-dark"><i class="bi bi-mortarboard-fill text-info me-2"></i>All Students</div>
                    <small class="text-muted">Academic records</small>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection