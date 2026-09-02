@extends('guardian.layout')

@section('title', mawa_e('guardian.results_title'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">{{ mawa_e('guardian.results_title') }}</h1>
        <div class="small text-body-secondary">{{ $student->full_name }} · {{ $student->student_id }}</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.show', $student->id) }}"><i class="bi bi-arrow-left me-1"></i>{{ mawa_e('guardian.back_to_profile') }}</a>
</div>

@if ($published->isEmpty() && $assessments->isEmpty())
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div>
            <div class="fw-semibold">{{ mawa_e('guardian.no_results_title') }}</div>
            <div class="small">{{ mawa_e('guardian.no_results_hint') }}</div>
        </div>
    </div>
@endif

@foreach ($published as $result)
    @php
        $snapshot = $result->students->first();
        $rows = $result->rows;
    @endphp
    <div class="card mb-3">
        <div class="card-header bg-transparent d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="fw-semibold">
                <i class="bi bi-patch-check me-1 text-primary"></i>
                {{ $result->name }}
                <span class="text-body-secondary fw-normal">· {{ $result->scheme?->academicYear?->name ?? mawa_e('guardian.na') }} {{ $result->scheme?->classGrade?->name ?? '' }}</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>{{ mawa_e('guardian.published') }}</span>
                @if ($result->published_at)
                    <span class="small text-body-secondary">{{ \Illuminate\Support\Carbon::parse($result->published_at)->format('d M Y') }}</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if ($snapshot !== null)
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center">
                            <div class="small text-body-secondary">{{ mawa_e('guardian.gpa') }}</div>
                            <div class="h5 mb-0">{{ $snapshot->gpa_status === 'computed' ? number_format((float) $snapshot->gpa, 2) : '—' }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center">
                            <div class="small text-body-secondary">{{ mawa_e('guardian.passed') }}</div>
                            <div class="h5 mb-0 text-success">{{ $snapshot->passed_count }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center">
                            <div class="small text-body-secondary">{{ mawa_e('guardian.failed') }}</div>
                            <div class="h5 mb-0 text-danger">{{ $snapshot->failed_count }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center">
                            <div class="small text-body-secondary">{{ mawa_e('guardian.result_status') }}</div>
                            <div class="h5 mb-0">
                                @if ((int) $snapshot->failed_count === 0)
                                    <span class="text-success">{{ mawa_e('guardian.passed') }}</span>
                                @else
                                    <span class="text-danger">{{ mawa_e('guardian.failed') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($rows->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="text-body-secondary">
                                <th>{{ mawa_e('guardian.subject') }}</th>
                                <th class="text-end">{{ mawa_e('guardian.marks') }}</th>
                                <th class="text-end">{{ mawa_e('guardian.grade_point') }}</th>
                                <th class="text-center">{{ mawa_e('guardian.passed') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row->subject?->name ?? mawa_e('guardian.na') }}
                                        @if ($row->optional)<span class="badge text-bg-light ms-1">{{ mawa_e('guardian.optional') }}</span>@endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) $row->aggregate, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $row->grade_point, 2) }}</td>
                                    <td class="text-center">
                                        @if ($row->grade_point > 0 && $row->aggregate >= 0)
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        @else
                                            <i class="bi bi-x-circle-fill text-danger"></i>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-body-secondary small mb-0">{{ mawa_e('guardian.no_subject_rows') }}</p>
            @endif
        </div>
    </div>
@endforeach

@if ($assessments->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-clipboard-check me-1"></i>{{ mawa_e('guardian.assessment_marks') }}
        </div>
        <div class="card-body p-0">
            @foreach ($assessments as $row)
                <div class="border-bottom p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div class="fw-semibold">
                            {{ $row['assessment']->name }}
                            <span class="text-body-secondary fw-normal">· {{ $row['assessment']->academicYear?->name }} {{ $row['assessment']->classGrade?->name }}</span>
                        </div>
                        <div class="small text-body-secondary">
                            {{ mawa_e('guardian.total') }}: {{ number_format((float) $row['total_obtained'], 2) }} / {{ number_format((float) $row['total_full'], 2) }}
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="text-body-secondary">
                                    <th>{{ mawa_e('guardian.subject') }}</th>
                                    <th class="text-end">{{ mawa_e('guardian.marks') }}</th>
                                    <th class="text-center">{{ mawa_e('guardian.passed') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($row['subjects'] as $subject)
                                    <tr>
                                        <td>{{ $subject['subject'] }}</td>
                                        <td class="text-end">{{ number_format((float) $subject['obtained'], 2) }} / {{ number_format((float) $subject['full'], 2) }}</td>
                                        <td class="text-center">
                                            @if ($subject['passed'])
                                                <i class="bi bi-check-circle-fill text-success"></i>
                                            @else
                                                <i class="bi bi-x-circle-fill text-danger"></i>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection