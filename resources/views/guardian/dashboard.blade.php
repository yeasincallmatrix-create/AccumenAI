@extends('guardian.layout')

@section('title', mawa_e('guardian.dashboard_title'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">{{ mawa_e('guardian.dashboard_title') }}</h1>
        <p class="text-body-secondary mb-0 small">{{ mawa_e('guardian.dashboard_welcome', ['name' => $guardian->name]) }}</p>
    </div>
</div>

@if ($student === null)
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div>
            <div class="fw-semibold">{{ mawa_e('guardian.no_students_title') }}</div>
            <div class="small">{{ mawa_e('guardian.no_students_hint') }}</div>
        </div>
    </div>
@else
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center text-primary fw-bold" style="width:52px;height:52px;font-size:20px">
                    {{ mb_substr($student->first_name ?? 'S', 0, 1) }}
                </div>
                <div class="flex-grow-1">
                    <div class="h5 mb-0">{{ $student->full_name }}</div>
                    <div class="small text-body-secondary">
                        {{ $student->student_id }}
                        @if ($enrollment !== null)
                            · {{ $enrollment->batch?->course?->name }} · {{ $enrollment->batch?->name }}
                        @endif
                    </div>
                </div>
                <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('guardian.students.show', $student->id) }}">
                    <i class="bi bi-person-vcard me-1"></i>{{ mawa_e('guardian.view_profile') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-1 text-body-secondary small">
                        <i class="bi bi-calendar-check"></i> {{ mawa_e('guardian.attendance') }}
                    </div>
                    <div class="h4 mb-0">
                        {{ $attendance !== null && $attendance['present_percent'] !== null ? $attendance['present_percent'].'%' : '—' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-1 text-body-secondary small">
                        <i class="bi bi-cash-coin"></i> {{ mawa_e('guardian.outstanding') }}
                    </div>
                    <div class="h4 mb-0">{{ $fees !== null ? number_format((float) $fees['outstanding'], 2) : '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-1 text-body-secondary small">
                        <i class="bi bi-award"></i> {{ mawa_e('guardian.published_results') }}
                    </div>
                    <div class="h4 mb-0">{{ $result_count }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-1 text-body-secondary small">
                        <i class="bi bi-patch-check"></i> {{ mawa_e('guardian.certificates') }}
                    </div>
                    <div class="h4 mb-0">{{ $certificate_count }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-calendar-check me-1"></i>{{ mawa_e('guardian.attendance') }}
                </div>
                <div class="card-body">
                    @if ($attendance !== null && $attendance['total'] > 0)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary">{{ mawa_e('guardian.present') }}</span>
                            <span class="fw-semibold text-success">{{ $attendance['present'] }} <small class="text-body-secondary">({{ $attendance['present_percent'] }}%)</small></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary">{{ mawa_e('guardian.absent') }}</span>
                            <span class="fw-semibold text-danger">{{ $attendance['absent'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary">{{ mawa_e('guardian.late') }}</span>
                            <span class="fw-semibold text-warning">{{ $attendance['late'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-body-secondary">{{ mawa_e('guardian.leave') }}</span>
                            <span class="fw-semibold">{{ $attendance['leave'] }}</span>
                        </div>
                        <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('guardian.students.attendance', $student->id) }}">
                            {{ mawa_e('guardian.view_attendance') }} <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @else
                        <p class="text-body-secondary small mb-0">{{ mawa_e('guardian.no_attendance') }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-cash-coin me-1"></i>{{ mawa_e('guardian.fees') }}
                </div>
                <div class="card-body">
                    @if ($fees !== null)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary">{{ mawa_e('guardian.billed') }}</span>
                            <span class="fw-semibold">{{ number_format((float) $fees['billed'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary">{{ mawa_e('guardian.paid') }}</span>
                            <span class="fw-semibold text-success">{{ number_format((float) $fees['collected'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-body-secondary">{{ mawa_e('guardian.outstanding') }}</span>
                            <span class="fw-semibold text-danger">{{ number_format((float) $fees['outstanding'], 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-body-secondary">{{ mawa_e('guardian.overdue') }}</span>
                            <span class="fw-semibold text-warning">{{ number_format((float) $fees['overdue'], 2) }}</span>
                        </div>
                        <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('guardian.students.fees', $student->id) }}">
                            {{ mawa_e('guardian.view_fees') }} <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @else
                        <p class="text-body-secondary small mb-0">{{ mawa_e('guardian.no_fees') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
@endsection