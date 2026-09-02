@extends('layouts.institute')

@section('title', 'Admission Pipeline — AccumenAI')

@php
    $stageBadge = [
        'leads'       => 'bg-secondary',
        'interested'  => 'bg-info',
        'applicants'  => 'bg-warning',
        'admitted'    => 'bg-success',
        'enrolled'    => 'bg-primary',
        'won'         => 'bg-success',
        'lost'        => 'bg-dark',
    ];
    $admissionBadge = [
        'draft'        => 'bg-secondary',
        'submitted'    => 'bg-info',
        'under_review' => 'bg-warning',
        'approved'     => 'bg-success',
        'rejected'     => 'bg-danger',
        'cancelled'    => 'bg-dark',
        'enrolled'     => 'bg-primary',
        'withdrawn'    => 'bg-secondary',
    ];
    $stageLabels = [
        'leads'       => 'Leads',
        'interested'  => 'Interested',
        'applicants'  => 'Applicants',
        'admitted'    => 'Admitted',
        'enrolled'    => 'Enrolled',
        'won'         => 'Won',
        'lost'        => 'Lost',
    ];
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Admission Pipeline</h4>
        <p class="page-header-desc mb-0">CRM leads through to enrollment — Lead → Interested → Applicant → Admitted → Enrolled</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="{{ route('admissions.pipeline.report', request()->query()) }}" class="btn btn-outline-primary">
            <i class="bi bi-bar-chart-fill me-1"></i>Funnel Report
        </a>
        @if ($canManage)
            <a href="{{ route('admissions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Application
            </a>
        @endif
    </div>
</div>

@include('students._tabs', ['activeTab' => 'pipeline'])

<div class="admin-card">

    <div class="filter-card">
        <form class="filter-layout" method="GET" action="{{ route('admissions.pipeline') }}">

            <div class="filter-search-row align-items-end">

                <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" name="q" value="{{ $filters['q'] }}"
                           placeholder="Search name, phone, email, application…">
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                    <label class="form-label mb-1">Branch</label>
                    <select class="form-select form-select-sm" name="branch_id">
                        <option value="">All branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                    <label class="form-label mb-1">Course</label>
                    <select class="form-select form-select-sm" name="course_id">
                        <option value="">All courses</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected($filters['course_id'] === $course->id)>{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                    <label class="form-label mb-1">Academic year</label>
                    <select class="form-select form-select-sm" name="academic_year_id">
                        <option value="">All years</option>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected($filters['academic_year_id'] === $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                    <label class="form-label mb-1">Source</label>
                    <select class="form-select form-select-sm" name="source_id">
                        <option value="">All sources</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected($filters['source_id'] === $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:160px;">
                    <label class="form-label mb-1">Assigned staff</label>
                    <select class="form-select form-select-sm" name="assigned_user_id">
                        <option value="">All staff</option>
                        @foreach ($staff as $user)
                            <option value="{{ $user->id }}" @selected($filters['assigned_user_id'] === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admissions.pipeline') }}"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                </div>

            </div>

        </form>
    </div>

    <div class="pipeline-board d-flex gap-3 p-3" style="overflow-x:auto;">
        @foreach (array_keys($stageLabels) as $stage)
            @php $items = $board[$stage] ?? []; @endphp
            <div class="pipeline-column flex-shrink-0" style="width:290px;">
                <div class="d-flex align-items-center justify-content-between px-2 py-2">
                    <span class="fw-semibold text-uppercase small">
                        <span class="badge {{ $stageBadge[$stage] }} me-1"></span>
                        {{ $stageLabels[$stage] }}
                    </span>
                    <span class="badge bg-light text-dark">{{ count($items) }}</span>
                </div>

                <div class="d-flex flex-column gap-2" style="min-height:80px;">
                    @forelse ($items as $item)
                        @if (in_array($stage, ['leads', 'interested', 'won', 'lost'], true))
                            <div class="border rounded p-2 bg-white">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold">{{ $item->displayName() }}</div>
                                        <small class="text-muted d-block">{{ $item->phone ?? ($item->email ?? '—') }}</small>
                                    </div>
                                    <span class="badge bg-light text-dark">{{ $item->status?->name ?? $item->status?->slug ?? '—' }}</span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-building me-1"></i>{{ $item->branch?->name ?? 'No branch' }}
                                    @if ($item->assignedUser)
                                        <span class="mx-1">·</span>
                                        <i class="bi bi-person me-1"></i>{{ $item->assignedUser->name }}
                                    @endif
                                </small>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="{{ route('crm.leads.show', $item) }}" class="btn btn-sm btn-outline-secondary flex-fill">View lead</a>
                                    @if ($canManage)
                                        <a href="{{ route('admissions.pipeline.convert', $item) }}" class="btn btn-sm btn-primary flex-fill">
                                            @if (in_array($stage, ['won', 'lost'], true)) Relink @else Convert @endif
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="border rounded p-2 bg-white">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold">{{ $item->full_name }}</div>
                                        <small class="text-muted d-block">{{ $item->application_number ?? '—' }}</small>
                                    </div>
                                    <span class="badge {{ $admissionBadge[$item->admission_status] ?? 'bg-secondary' }}">{{ $item->admission_status }}</span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-book me-1"></i>{{ $item->appliedCourse?->name ?? '—' }}
                                    @if ($item->appliedAcademicYear)
                                        <span class="mx-1">·</span>{{ $item->appliedAcademicYear->name }}
                                    @endif
                                </small>
                                <small class="text-muted d-block">
                                    <i class="bi bi-building me-1"></i>{{ $item->branch?->name ?? '—' }}
                                    @if ($item->preferredBatch)
                                        <span class="mx-1">·</span>Pref: {{ $item->preferredBatch->name }}
                                    @endif
                                </small>
                                @if ($item->admissionAssignedUser)
                                    <small class="text-muted d-block"><i class="bi bi-person me-1"></i>{{ $item->admissionAssignedUser->name }}</small>
                                @endif
                                <a href="{{ route('admissions.show', $item) }}" class="btn btn-sm btn-outline-primary mt-2 w-100">View application</a>
                            </div>
                        @endif
                    @empty
                        <div class="text-center text-muted small border rounded p-3 bg-white bg-opacity-50">No {{ strtolower($stageLabels[$stage]) }}</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

</div>
@endsection