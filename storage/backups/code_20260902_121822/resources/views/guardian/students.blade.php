@extends('guardian.layout')

@section('title', mawa_e('guardian.students_title'))

@section('content')
<h1 class="h4 mb-3">{{ mawa_e('guardian.students_title') }}</h1>

@if ($rows->isEmpty())
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div>
            <div class="fw-semibold">{{ mawa_e('guardian.no_students_title') }}</div>
            <div class="small">{{ mawa_e('guardian.no_students_hint') }}</div>
        </div>
    </div>
@else
    <div class="row g-3">
        @foreach ($rows as $row)
            @php $student = $row['student']; $enrollment = $row['enrollment']; @endphp
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center text-primary fw-bold" style="width:44px;height:44px;font-size:18px">
                                {{ mb_substr($student->first_name ?? 'S', 0, 1) }}
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $student->full_name }}</div>
                                <div class="small text-body-secondary">{{ $student->student_id }}</div>
                            </div>
                            <span class="badge text-bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">{{ $student->status }}</span>
                        </div>
                        @if ($enrollment !== null)
                            <div class="small text-body-secondary mb-1">
                                <i class="bi bi-mortarboard me-1"></i>{{ $enrollment->batch?->course?->name }}
                            </div>
                            <div class="small text-body-secondary mb-1">
                                <i class="bi bi-calendar3 me-1"></i>{{ $enrollment->batch?->name }} ({{ $enrollment->batch?->academicYear?->name ?? mawa_e('guardian.na') }})
                            </div>
                            <div class="small text-body-secondary">
                                <i class="bi bi-geo-alt me-1"></i>{{ $enrollment->batch?->branch?->name ?? $student->branch?->name ?? mawa_e('guardian.na') }}
                            </div>
                        @else
                            <div class="small text-body-secondary mb-1">{{ mawa_e('guardian.no_enrollment') }}</div>
                        @endif
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('guardian.students.show', $student->id) }}">
                                <i class="bi bi-person-vcard me-1"></i>{{ mawa_e('guardian.view_profile') }}
                            </a>
                            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.attendance', $student->id) }}">
                                <i class="bi bi-calendar-check me-1"></i>{{ mawa_e('guardian.attendance') }}
                            </a>
                            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.results', $student->id) }}">
                                <i class="bi bi-award me-1"></i>{{ mawa_e('guardian.results') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection