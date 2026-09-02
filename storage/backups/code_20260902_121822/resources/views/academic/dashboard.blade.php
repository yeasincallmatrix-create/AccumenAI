@extends('layouts.institute')

@section('title', mawa_e('academic_dashboard.title'))

@section('content')

@include('dashboard._tabs', ['activeTab' => 'academic'])

@php
    $year = $summary['year'];
    $attendance = $summary['attendance'];
    $institute = $summary['institute'] ?? $institute ?? null;
    $industryLabel = $summary['industryLabel'] ?? 'Education';
    $subIndustryLabel = $summary['subIndustryLabel'] ?? '';
    $overview = $summary['overview'] ?? [];
    $academics = $summary['academics'] ?? [];
    $teachers = $summary['teachers'] ?? ['total'=>0,'active'=>0];
    $batches = $summary['batches'] ?? ['by_status'=>[],'recent'=>collect()];
    $courses = $summary['courses'] ?? ['total'=>0,'assigned'=>0];
    $usesClassTerm = in_array($institute?->sub_industry ?? '', ['school','college','madrasha','primary_school','secondary_high_school','school_college'], true);
    $academicLabel = $usesClassTerm ? mawa_e('sidebar.classes') : mawa_e('sidebar.courses');
@endphp

{{-- Header — industry aware for ALL education sub-industries --}}
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>{{ mawa_e('academic_dashboard.title') }}</h4>
        <p class="page-header-desc">
            {{ $institute->name ?? '' }}
            @if($subIndustryLabel)
                — {{ $subIndustryLabel }} <span class="text-muted">({{ $industryLabel }})</span>
            @else
                — {{ $industryLabel }} — {{ mawa_e('academic_dashboard.subtitle') }}
            @endif
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        @if($institute)
            <span class="badge text-bg-primary p-2"><i class="bi bi-building me-1"></i>{{ $subIndustryLabel ?: $industryLabel }}</span>
        @endif
        <span class="badge text-bg-light border p-2">
            <i class="bi bi-calendar-check me-1"></i>
            {{ mawa_e('academic_dashboard.current_year') }}:
            @if ($year)
                {{ $year->name }} ({{ $year->start_date?->format('d M Y') }} – {{ $year->end_date?->format('d M Y') }})
            @else
                {{ mawa_e('academic_dashboard.not_set') }}
            @endif
        </span>
    </div>
</div>

@if ($year === null)
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ mawa_e('academic_dashboard.no_current_year') }}
        <a href="{{ route('settings.academic.index') }}" class="alert-link ms-2">Configure academic year →</a>
    </div>
@endif

{{-- Overview — works for every education sub-industry --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="icon" style="background:rgba(13,110,253,.1); color:#0d6efd;"><i class="bi bi-people-fill"></i></div>
            <div class="num">{{ $overview['students_total'] ?? $summary['students']['cohort'] }}</div>
            <div class="label">{{ $usesClassTerm ? 'Students' : mawa_e('academic_dashboard.active_students') }}</div>
            <div class="small text-muted">{{ $summary['students']['active'] }} active placements</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="icon" style="background:rgba(111,66,193,.12); color:#6f42c1;"><i class="bi bi-person-workspace"></i></div>
            <div class="num">{{ $teachers['total'] ?? 0 }}</div>
            <div class="label">Teachers</div>
            <div class="small text-muted">{{ $teachers['active'] ?? 0 }} active</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="icon" style="background:rgba(25,135,84,.12); color:#198754;"><i class="bi bi-collection-fill"></i></div>
            <div class="num">{{ $overview['batches_total'] ?? 0 }}</div>
            <div class="label">Batches</div>
            <div class="small text-muted">{{ $overview['batches_running'] ?? 0 }} running</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="icon" style="background:rgba(13,202,240,.15); color:#0aa2c0;"><i class="bi bi-journal-bookmark-fill"></i></div>
            <div class="num">{{ $courses['assigned'] ?? $courses['total'] ?? 0 }}</div>
            <div class="label">{{ $academicLabel }}</div>
            <div class="small text-muted">{{ $courses['total'] ?? 0 }} total</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="icon" style="background:rgba(255,193,7,.15); color:#b8860b;"><i class="bi bi-clipboard-check-fill"></i></div>
            <div class="num">{{ $overview['exams_total'] ?? 0 }}</div>
            <div class="label">Exams</div>
            <div class="small text-muted">{{ $overview['assessments_total'] ?? 0 }} assessments</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="icon" style="background:rgba(220,53,69,.1); color:#dc3545;"><i class="bi bi-patch-check-fill"></i></div>
            <div class="num">{{ $summary['certificates']['issued'] }}</div>
            <div class="label">Certificates</div>
            <div class="small text-muted">{{ $summary['certificates']['eligible'] }} eligible</div>
        </div>
    </div>
</div>

{{-- Academic Structure — relevant for ALL education types --}}
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-diagram-3-fill"></i> Academic Structure — {{ $subIndustryLabel ?: $industryLabel }}</div>
        <a href="{{ route('settings.academic.index') }}" class="btn btn-sm btn-outline-primary">Manage structure</a>
    </div>
    <div class="row row-cols-2 row-cols-md-4 g-3">
        <div class="col">
            <div class="border rounded p-3 text-center h-100">
                <div class="fs-4 fw-bold text-primary">{{ $academics['levels'] ?? 0 }}</div>
                <div class="small text-muted">Levels</div>
                <div class="small text-muted">{{ $academics['institute_levels'] ?? 0 }} institute levels</div>
            </div>
        </div>
        <div class="col">
            <div class="border rounded p-3 text-center h-100">
                <div class="fs-4 fw-bold" style="color:#6f42c1;">{{ $academics['classes'] ?? 0 }}</div>
                <div class="small text-muted">Classes / Grades</div>
                <div class="small text-muted">{{ $academics['institute_classes'] ?? 0 }} active</div>
            </div>
        </div>
        <div class="col">
            <div class="border rounded p-3 text-center h-100">
                <div class="fs-4 fw-bold text-success">{{ $academics['groups'] ?? 0 }}</div>
                <div class="small text-muted">Groups</div>
                <div class="small text-muted">{{ $academics['subjects'] ?? 0 }} subjects</div>
            </div>
        </div>
        <div class="col">
            <div class="border rounded p-3 text-center h-100">
                <div class="fs-4 fw-bold text-warning">{{ $academics['grading_scales'] ?? 0 }}</div>
                <div class="small text-muted">Grading Scales</div>
                <div class="small text-muted">{{ $academics['systems'] ?? 0 }} systems</div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3 flex-wrap">
        <a href="{{ route('settings.academic.index') }}" class="btn btn-sm btn-light border">Academic Years</a>
        <a href="{{ route('settings.academic.placements.index') }}" class="btn btn-sm btn-light border">Placements</a>
        <a href="{{ route('settings.academic.assessments.index') }}" class="btn btn-sm btn-light border">Assessments</a>
        <a href="{{ route('settings.academic.aggregations.index') }}" class="btn btn-sm btn-light border">Aggregations</a>
        <a href="{{ route('settings.academic.grading.index') }}" class="btn btn-sm btn-light border">Grading</a>
        <a href="{{ route('settings.academic.final-results.index') }}" class="btn btn-sm btn-light border">Final Results</a>
        <a href="{{ route('academic.analytics.index') }}" class="btn btn-sm btn-primary">Education Analytics →</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-people-fill"></i> {{ mawa_e('academic_dashboard.students') }}</div>
                <a href="{{ route('students.index') }}" class="btn btn-sm btn-outline-primary">All students</a>
            </div>
            <div class="row row-cols-2 g-3">
                @php
                    $metrics = [
                        ['key' => 'cohort', 'label' => 'active_students'],
                        ['key' => 'active', 'label' => 'active_placements'],
                        ['key' => 'completed', 'label' => 'completed'],
                        ['key' => 'graduated', 'label' => 'graduated'],
                        ['key' => 'withdrawn', 'label' => 'withdrawn'],
                        ['key' => 'transferred', 'label' => 'transferred'],
                    ];
                @endphp
                @foreach ($metrics as $metric)
                    <div class="col">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ mawa_e('academic_dashboard.' . $metric['label']) }}</div>
                            <div class="fs-4 fw-semibold">{{ $summary['students'][$metric['key']] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-journal-bookmark-fill"></i> {{ mawa_e('academic_dashboard.final_results') }}</div>
                <a href="{{ route('settings.academic.final-results.index') }}" class="btn btn-sm btn-outline-primary">View results</a>
            </div>
            <div class="row row-cols-3 g-3">
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.published_results') }}</div>
                        <div class="fs-4 fw-semibold">{{ $summary['results']['published_results'] }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.passed') }}</div>
                        <div class="fs-4 fw-semibold text-success">{{ $summary['results']['passed_students'] }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.failed') }}</div>
                        <div class="fs-4 fw-semibold text-danger">{{ $summary['results']['failed_students'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-diagram-3-fill"></i> {{ mawa_e('academic_dashboard.promotion') }}</div>
                <a href="{{ route('settings.academic.promotions.index') }}" class="btn btn-sm btn-outline-primary">Promotions</a>
            </div>
            <div class="row row-cols-3 g-3">
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.pending') }}</div>
                        <div class="fs-4 fw-semibold">{{ $summary['promotion']['pending'] }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.in_review') }}</div>
                        <div class="fs-4 fw-semibold">{{ $summary['promotion']['review'] }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.approved') }}</div>
                        <div class="fs-4 fw-semibold">{{ $summary['promotion']['approved'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-calendar-check-fill"></i> {{ mawa_e('academic_dashboard.attendance') }}</div>
                <a href="{{ route('academic-attendance.mark.index') }}" class="btn btn-sm btn-outline-primary">Mark</a>
            </div>
            @if ($attendance['available'])
                <div class="row row-cols-2 g-3">
                    <div class="col">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ mawa_e('academic_dashboard.records') }}</div>
                            <div class="fs-4 fw-semibold">{{ $attendance['total'] }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ mawa_e('academic_dashboard.present') }}</div>
                            <div class="fs-4 fw-semibold text-success">
                                {{ $attendance['present_percent'] !== null ? number_format($attendance['present_percent'], 1) . '%' : '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ mawa_e('academic_dashboard.absent') }}</div>
                            <div class="fs-4 fw-semibold text-danger">{{ $attendance['absent'] }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ mawa_e('academic_dashboard.late') }} / {{ mawa_e('academic_dashboard.leave') }}</div>
                            <div class="fs-4 fw-semibold">{{ $attendance['late'] }} / {{ $attendance['leave'] }}</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-light border mb-0">
                    <i class="bi bi-info-circle me-1"></i> {{ mawa_e('academic_dashboard.attendance_unavailable') }}
                    @if ($attendance['message'])
                        <div class="small text-muted mt-1">{{ $attendance['message'] }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="col-md-4">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-patch-check-fill"></i> {{ mawa_e('academic_dashboard.certificates') }}</div>
                <a href="{{ route('certificates.index') }}" class="btn btn-sm btn-outline-primary">Certificates</a>
            </div>
            <div class="row row-cols-2 g-3">
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.eligible') }}</div>
                        <div class="fs-4 fw-semibold">{{ $summary['certificates']['eligible'] }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.issued') }}</div>
                        <div class="fs-4 fw-semibold text-success">{{ $summary['certificates']['issued'] }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.revoked') }}</div>
                        <div class="fs-4 fw-semibold text-danger">{{ $summary['certificates']['revoked'] }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ mawa_e('academic_dashboard.requests') }}</div>
                        <div class="fs-4 fw-semibold">{{ $summary['certificates']['pending'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Batches & Recent — relevant for every education sub-industry --}}
@if(!empty($batches['recent']) && $batches['recent']->count())
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-collection-fill"></i> Recent Batches</div>
        <a href="{{ route('batches.index') }}" class="btn btn-sm btn-outline-primary">All batches</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Batch</th><th>Course/Class</th><th>Status</th><th>Students</th></tr></thead>
            <tbody>
                @foreach($batches['recent'] as $batch)
                <tr>
                    <td><a href="{{ route('batches.show', $batch) }}">{{ $batch->name ?? $batch->batch_name ?? 'Batch #'.$batch->id }}</a></td>
                    <td>{{ $batch->course?->name ?? '—' }}</td>
                    <td><span class="badge text-bg-light border">{{ $batch->status }}</span></td>
                    <td>{{ $batch->students_count ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Quick links for all education --}}
<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-grid-fill"></i> Quick Access — {{ $subIndustryLabel ?: 'Education' }}</div>
    </div>
    <div class="row row-cols-2 row-cols-md-4 g-2">
        <div class="col"><a href="{{ route('students.index') }}" class="btn btn-light border w-100"><i class="bi bi-people me-1"></i> Students</a></div>
        <div class="col"><a href="{{ route('teachers.index') }}" class="btn btn-light border w-100"><i class="bi bi-person-workspace me-1"></i> Teachers</a></div>
        <div class="col"><a href="{{ route('batches.index') }}" class="btn btn-light border w-100"><i class="bi bi-collection me-1"></i> Batches</a></div>
        <div class="col"><a href="{{ $usesClassTerm ? route('classes.index') : route('courses.manage.index') }}" class="btn btn-light border w-100"><i class="bi bi-journal-bookmark me-1"></i> {{ $academicLabel }}</a></div>
        <div class="col"><a href="{{ route('admissions.index') }}" class="btn btn-light border w-100"><i class="bi bi-file-earmark-person me-1"></i> Admissions</a></div>
        <div class="col"><a href="{{ route('admissions.pipeline') }}" class="btn btn-light border w-100"><i class="bi bi-diagram-3 me-1"></i> Pipeline</a></div>
        <div class="col"><a href="{{ route('exams.index') }}" class="btn btn-light border w-100"><i class="bi bi-clipboard-check me-1"></i> Exams</a></div>
        <div class="col"><a href="{{ route('academic.analytics.index') }}" class="btn btn-primary w-100"><i class="bi bi-bar-chart-line me-1"></i> Education Analytics</a></div>
    </div>
</div>

@endsection
