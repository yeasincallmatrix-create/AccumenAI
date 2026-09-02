@extends('layouts.institute')

@section('title', mawa_lang('subjects.dependencies') . ' — ' . $subject->name . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('subjects.dependencies') }}</h4>
        <p class="page-header-desc">{{ mawa_lang('subjects.dependencies_desc') }} — <strong>{{ $subject->name }}</strong> ({{ $subject->subject_code }})</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('courses.manage.subjects.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Subjects
        </a>
    </div>
</div>

<div class="admin-card">
    <div class="card-body">
        <div class="alert alert-info mb-4">
            <h6 class="alert-heading"><i class="bi bi-info-circle me-1"></i>{{ mawa_lang('subjects.classification') }}</h6>
            <p class="mb-2">
                <span class="badge {{ $classification['state'] === 'UNREFERENCED' ? 'text-bg-success' : ($classification['state'] === 'ACTIVE_DEPENDENCY' ? 'text-bg-warning' : ($classification['state'] === 'HISTORICAL_DEPENDENCY' ? 'text-bg-danger' : 'text-bg-dark')) }} fs-6">
                    {{ $classification['state'] }}
                </span>
            </p>
            @if ($classification['blockReason'])
                <p class="mb-0">{{ $classification['blockReason'] }}</p>
            @endif
            <hr class="my-2">
            <div class="row text-center small">
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['course_subjects'] ?? 0 }}</div>
                    <div class="text-muted">Course Subjects</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['institute_subjects'] ?? 0 }}</div>
                    <div class="text-muted">Institute Subjects</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['subject_academic_assignments'] ?? 0 }}</div>
                    <div class="text-muted">Academic Assignments</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['student_subject_selections'] ?? 0 }}</div>
                    <div class="text-muted">Student Selections</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['assessment_subjects'] ?? 0 }}</div>
                    <div class="text-muted">Assessments</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['exam_subjects'] ?? 0 }}</div>
                    <div class="text-muted">Exam Subjects</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['exam_results'] ?? 0 }}</div>
                    <div class="text-muted">Exam Results</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['academic_final_result_rows'] ?? 0 }}</div>
                    <div class="text-muted">Final Results</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['teacher_academic_assignments'] ?? 0 }}</div>
                    <div class="text-muted">Teacher Assignments</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-bold">{{ $classification['counts']['calendar_events'] ?? 0 }}</div>
                    <div class="text-muted">Calendar Events</div>
                </div>
            </div>
        </div>

        <h5>{{ mawa_lang('subjects.detailed_breakdown') }}</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Relation</th>
                        <th class="text-end">Count</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($details as $key => $count)
                        <tr>
                            <td>{{ mawa_lang("subjects.dependency.{$key}") ?? ucwords(str_replace('_', ' ', $key)) }}</td>
                            <td class="text-end fw-bold">{{ $count }}</td>
                            <td>
                                @if ($count > 0)
                                    @if (in_array($key, ['exam_results', 'academic_final_result_rows', 'student_subject_selections']))
                                        <span class="badge text-bg-danger">Historical</span>
                                    @else
                                        <span class="badge text-bg-warning">Active</span>
                                    @endif
                                @else
                                    <span class="badge text-bg-success">Clean</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <h6>{{ mawa_lang('subjects.deletion_rules') }}</h6>
            <ul class="small text-muted">
                <li><strong>UNREFERENCED:</strong> Soft-delete allowed.</li>
                <li><strong>ACTIVE_DEPENDENCY:</strong> Blocked — remove active assignments first.</li>
                <li><strong>HISTORICAL_DEPENDENCY:</strong> Soft-delete allowed, hard delete blocked — historical records preserved.</li>
                <li><strong>SYSTEM_REFERENCE:</strong> Blocked — global/system subject.</li>
            </ul>
        </div>
    </div>
</div>
@endsection