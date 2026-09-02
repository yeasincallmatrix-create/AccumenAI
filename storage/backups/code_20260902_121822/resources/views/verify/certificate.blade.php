@extends('layouts.standalone')

@section('title', 'Certificate Verification — AccumenAI')
@section('page_title', 'Certificate Verification')

@section('content')
@php
    $verified = $certificate->status === 'active';
    $statusBadge = [
        'pending'  => ['text-bg-warning', 'PENDING REVIEW'],
        'active'   => ['text-bg-success', 'VALID CERTIFICATE'],
        'rejected' => ['text-bg-danger', 'REJECTED'],
        'revoked'  => ['text-bg-danger', 'REVOKED'],
    ];
    [$badgeClass, $badgeText] = $statusBadge[$certificate->status] ?? ['text-bg-secondary', strtoupper((string) $certificate->status)];
@endphp

<div class="verify-certificate">
    <div class="verify-card">
        <div class="verify-header">
            <div class="verify-icon">
                <i class="bi {{ $verified ? 'bi-patch-check-fill text-gold shine' : 'bi-x-octagon-fill text-danger' }}"></i>
            </div>
            <h4 class="verify-heading">
                {{ $verified ? 'Certificate Verified' : 'Verification Failed' }}
            </h4>
            <span class="badge {{ $badgeClass }} verify-badge">{{ $badgeText }}</span>
            <p class="text-muted verify-sub">
                This certificate is {{ $verified ? 'authentic and issued by' : 'linked to' }}
                <strong>{{ $certificate->institute->name ?? 'AccumenAI' }}</strong> on {{ $certificate->created_at->format('d M Y') }}.
            </p>
        </div>

        <div class="verify-details">
            <div class="text-center mb-4">
                @if($certificate->student?->photo)
                    <img src="{{ $certificate->student->photo_url }}" alt="{{ $certificate->student->full_name ?? 'Student' }}" class="verify-photo">
                @else
                    <div class="verify-photo verify-photo-placeholder"><i class="bi bi-person-fill"></i></div>
                @endif
                <div class="verify-student-name">{{ $certificate->student->full_name ?? trim(($certificate->student->first_name ?? '').' '.($certificate->student->last_name ?? '')) ?: '—' }}</div>
                <div class="verify-course-name">{{ $certificate->course->name ?? '—' }}</div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="verify-label">Father Name</label>
                    <div class="verify-value">{{ $certificate->student->father_name ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <label class="verify-label">Certificate No</label>
                    <div class="verify-value font-monospace">{{ $certificate->certificate_number ?? '—' }}</div>
                </div>
                <div class="col-12">
                    <label class="verify-label">Subjects Covered</label>
                    @php $verifySubjects = $certificate->course?->subjects ? $certificate->course->subjects->sortBy(function($s){ $map=['ARC Welding'=>1,'TIG'=>2,'MIG'=>3]; return $map[$s->name] ?? 99; }) : collect(); @endphp
                    @if($verifySubjects->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            @foreach($verifySubjects as $subject)
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">{{ $subject->name }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="verify-value text-muted small">No subjects listed</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="verify-label">NID No</label>
                    <div class="verify-value">{{ $certificate->student->nid_number ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <label class="verify-label">Passport No</label>
                    <div class="verify-value">{{ $certificate->student->passport_number ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <label class="verify-label">Date of Issue</label>
                    <div class="verify-value">{{ $certificate->issue_date?->format('d M Y') ?? $certificate->created_at?->format('d M Y') ?? '—' }}</div>
                </div>
            </div>

            @if ($certificate->revoked_reason)
                <div class="alert alert-danger mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    This certificate has been revoked. Reason: {{ $certificate->revoked_reason }}
                </div>
            @elseif ($certificate->status === 'rejected')
                <div class="alert alert-danger mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    This certificate request was rejected and is not valid.
                </div>
            @elseif ($certificate->status === 'pending')
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="bi bi-clock-fill me-2"></i>
                    This certificate is still pending review and is not yet valid.
                </div>
            @endif
        </div>

        <div class="verify-footer text-muted small">
            <i class="bi bi-shield-check me-1"></i>
            Authenticity confirmed via AccumenAI digital records.
            <span class="float-end">
                <a class="text-decoration-none" href="{{ route('verify.certificate.index') }}"><i class="bi bi-arrow-repeat me-1"></i>Check another</a>
            </span>
        </div>
    </div>
</div>

<style>
    .verify-certificate { max-width: 640px; margin: 24px auto; }
    .verify-card {
        background: #fff;
        border: 1px solid rgba(13, 110, 253, .15);
        border-radius: 18px;
        box-shadow: 0 20px 50px rgba(13, 110, 253, .10);
        overflow: hidden;
    }
    .verify-header {
        text-align: center;
        padding: 32px 24px 24px;
        border-bottom: 1px solid rgba(13, 110, 253, .12);
        background: linear-gradient(180deg, rgba(13, 110, 253, .06), transparent);
    }
    .verify-icon { font-size: 42px; line-height: 1; margin-bottom: 10px; }
    .text-gold {
        background: linear-gradient(45deg, #b8860b, #ffd700 45%, #fff7c2 60%, #ffd700 75%, #b8860b);
        background-size: 200% auto;
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        color: #ffd700;
        animation: goldShine 2.5s linear infinite;
    }
    @keyframes goldShine {
        0% { background-position: 200% center; }
        100% { background-position: -200% center; }
    }
    .verify-heading { font-weight: 700; margin-bottom: 8px; }
    .verify-badge { font-size: 12px; letter-spacing: .6px; padding: 6px 12px; border-radius: 30px; }
    .verify-sub { margin: 12px 0 0; font-size: 14px; }
    .verify-details { padding: 24px; }
    .verify-photo { width: 110px; height: 110px; border-radius: 12px; object-fit: cover; border: 3px solid var(--bs-primary); box-shadow: 0 4px 12px rgba(13,110,253,.15); margin: 0 auto 12px; display: block; }
    .verify-photo-placeholder { width: 110px; height: 110px; border-radius: 12px; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-size: 48px; color: #adb5bd; margin: 0 auto 12px; }
    .verify-student-name { font-weight: 800; font-size: 20px; color: #212529; margin-bottom: 2px; }
    .verify-course-name { font-size: 14px; color: #495057; font-weight: 600; }
    .verify-label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #6c757d; margin-bottom: 2px; }
    .verify-value { font-weight: 600; font-size: 15px; }
    .verify-footer {
        padding: 12px 24px;
        border-top: 1px dashed rgba(13, 110, 253, .18);
        background: rgba(13, 110, 253, .03);
    }
    html.monetix-dark .verify-card { background: #1e1f22; border-color: rgba(255,255,255,.1); }
    html.monetix-dark .verify-label { color: #9aa0a6; }
</style>
@endsection
