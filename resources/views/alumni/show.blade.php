@extends('layouts.institute')

@section('title', mawa_e('alumni.profile') . ' — AccumenAI')

@php
    $student = $alumni->student;
    $name = $student->full_name ?: trim($student->first_name.' '.$student->last_name);
    $statusBadge = [
        'active'   => 'text-bg-success',
        'inactive' => 'text-bg-secondary',
    ];
    $visibilityNames = [
        'private' => mawa_e('alumni.visibility_private'),
        'public'  => mawa_e('alumni.visibility_public'),
    ];
    $contactNames = [
        'private' => mawa_e('alumni.visibility_private'),
        'email'   => mawa_e('alumni.contact_email'),
        'phone'   => mawa_e('alumni.contact_phone'),
        'both'    => mawa_e('alumni.contact_email_phone'),
    ];
    $certStatusBadge = [
        'pending'  => 'text-bg-warning',
        'active'   => 'text-bg-success',
        'rejected' => 'text-bg-danger',
        'revoked'  => 'text-bg-secondary',
    ];
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <a href="{{ route('alumni.directory') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>{{ mawa_e('alumni.nav_directory') }}
        </a>
        <h4 class="page-header-title">
            {{ $name }}
            <span class="badge {{ $statusBadge[$alumni->status] ?? 'text-bg-secondary' }} ms-1">{{ ucfirst($alumni->status) }}</span>
        </h4>
        <p class="page-header-desc">
            @if ($alumni->alumni_reference_number)
                Ref: {{ $alumni->alumni_reference_number }}
            @elseif ($student->reg_no)
                Reg: {{ $student->reg_no }}
            @elseif ($student->student_id)
                ID: {{ $student->student_id }}
            @else
                {{ mawa_e('alumni.alumni_profile') }}
            @endif
        </p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        @if ($user->hasPermission('alumni.update'))
            <a class="btn btn-outline-primary" href="{{ route('alumni.edit', $alumni) }}">
                <i class="bi bi-pencil me-1"></i>{{ mawa_e('alumni.edit_btn') }}
            </a>
        @endif
        @if ($user->hasPermission('alumni.delete'))
            <form class="d-inline" method="POST" action="{{ route('alumni.destroy', $alumni) }}"
                  data-ajax-delete="1" data-confirm="{{ mawa_e('alumni.confirm_remove') }}">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i>{{ mawa_e('alumni.remove') }}</button>
            </form>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.career_information') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">{{ mawa_e('alumni.field_current_occupation') }}</div>
                        <div class="fw-semibold">{{ $alumni->current_occupation ?: '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">{{ mawa_e('alumni.field_job_title') }}</div>
                        <div class="fw-semibold">{{ $alumni->job_title ?: '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">{{ mawa_e('alumni.field_employer') }}</div>
                        <div class="fw-semibold">{{ $alumni->employer ?: '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">{{ mawa_e('alumni.field_employment_sector') }}</div>
                        <div class="fw-semibold">{{ $alumni->employment_sector ?: '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">{{ mawa_e('alumni.field_current_city') }}</div>
                        <div class="fw-semibold">{{ $alumni->current_city ?: '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">{{ mawa_e('alumni.field_current_country') }}</div>
                        <div class="fw-semibold">{{ $alumni->current_country ?: '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">{{ mawa_e('alumni.field_higher_education') }}</div>
                        <div>{{ $alumni->higher_education ?: '—' }}</div>
                    </div>
                    @if ($alumni->career_notes)
                        <div class="col-12">
                            <div class="text-muted small">{{ mawa_e('alumni.field_career_notes') }}</div>
                            <div>{{ $alumni->career_notes }}</div>
                        </div>
                    @endif
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">{{ mawa_e('alumni.field_profile_visibility') }}</div>
                        <span class="badge text-bg-info">{{ $visibilityNames[$alumni->profile_visibility] ?? $alumni->profile_visibility }}</span>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">{{ mawa_e('alumni.field_public_contact') }}</div>
                        <span class="badge text-bg-light border">{{ $contactNames[$alumni->public_contact_preference] ?? $alumni->public_contact_preference }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.academic_history') }} <span class="text-muted small">{{ mawa_e('alumni.read_only') }}</span></h5></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">{{ mawa_e('alumni.field_graduation_date') }}</dt>
                    <dd class="col-7">{{ $alumni->graduation_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted">{{ mawa_e('alumni.field_completion_year') }}</dt>
                    <dd class="col-7">{{ $alumni->completionAcademicYear?->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted">{{ mawa_e('alumni.field_completed_course') }}</dt>
                    <dd class="col-7">{{ $alumni->completedCourse?->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted">{{ mawa_e('alumni.field_completed_batch') }}</dt>
                    <dd class="col-7">{{ $alumni->completedBatch?->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted">{{ mawa_e('alumni.field_student_id') }}</dt>
                    <dd class="col-7">{{ $student->student_id ?: '—' }}</dd>

                    <dt class="col-5 text-muted">{{ mawa_e('alumni.field_registration') }}</dt>
                    <dd class="col-7">{{ $student->reg_no ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="admin-card">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.certificates') }}</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ mawa_e('alumni.th_certificate_no') }}</th>
                            <th>{{ mawa_e('alumni.th_course') }}</th>
                            <th>{{ mawa_e('alumni.th_batch') }}</th>
                            <th>{{ mawa_e('alumni.th_issue_date') }}</th>
                            <th>{{ mawa_e('alumni.th_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($certificates as $certificate)
                            <tr>
                                <td class="text-muted">{{ $certificate->certificate_number ?? '—' }}</td>
                                <td>{{ $certificate->course?->name ?? '—' }}</td>
                                <td>{{ $certificate->batch?->name ?? '—' }}</td>
                                <td>{{ $certificate->issue_date?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $certStatusBadge[$certificate->status] ?? 'text-bg-secondary' }}">{{ ucfirst($certificate->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">{{ mawa_e('alumni.no_certificates') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
