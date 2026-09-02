@extends('guardian.layout')

@section('title', mawa_e('guardian.certificates_title'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">{{ mawa_e('guardian.certificates_title') }}</h1>
        <div class="small text-body-secondary">{{ $student->full_name }} · {{ $student->student_id }}</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.show', $student->id) }}"><i class="bi bi-arrow-left me-1"></i>{{ mawa_e('guardian.back_to_profile') }}</a>
</div>

@if ($certificates->isEmpty())
    <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>{{ mawa_e('guardian.no_certificates') }}</div>
@else
    <div class="row g-3">
        @foreach ($certificates as $certificate)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-patch-check fs-4 text-primary"></i>
                            <div class="fw-semibold flex-grow-1">{{ $certificate->course?->name ?? mawa_e('guardian.na') }}</div>
                            @if ($certificate->status === 'active')
                                <span class="badge text-bg-success">{{ mawa_e('guardian.issued') }}</span>
                            @elseif ($certificate->status === 'pending')
                                <span class="badge text-bg-warning">{{ mawa_e('guardian.pending') }}</span>
                            @elseif ($certificate->status === 'revoked')
                                <span class="badge text-bg-danger">{{ mawa_e('guardian.revoked') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ $certificate->status }}</span>
                            @endif
                        </div>
                        <div class="small text-body-secondary mb-1">
                            <i class="bi bi-bounding-box me-1"></i>{{ $certificate->batch?->name }}
                        </div>
                        @if ($certificate->issue_date)
                            <div class="small text-body-secondary mb-1">
                                <i class="bi bi-calendar3 me-1"></i>{{ \Illuminate\Support\Carbon::parse($certificate->issue_date)->format('d M Y') }}
                            </div>
                        @endif
                        @if ($certificate->status === 'active')
                            <div class="small text-body-secondary mb-3">
                                <i class="bi bi-upc-scan me-1"></i>{{ $certificate->certificate_number }}
                            </div>
                            <a class="btn btn-sm btn-outline-primary rounded-pill w-100" href="{{ route('verify.certificate', $certificate->certificate_number) }}" target="_blank" rel="noopener">
                                <i class="bi bi-shield-check me-1"></i>{{ mawa_e('guardian.verify_certificate') }}
                            </a>
                        @elseif ($certificate->status === 'pending')
                            <div class="small text-body-secondary mt-3">{{ mawa_e('guardian.certificate_pending_note') }}</div>
                        @elseif ($certificate->status === 'revoked' && $certificate->revoked_reason)
                            <div class="small text-danger mt-3"><i class="bi bi-exclamation-triangle me-1"></i>{{ $certificate->revoked_reason }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection