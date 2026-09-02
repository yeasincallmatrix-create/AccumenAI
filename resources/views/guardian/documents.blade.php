@extends('guardian.layout')

@section('title', mawa_e('guardian.documents_title'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">{{ mawa_e('guardian.documents_title') }}</h1>
        <div class="small text-body-secondary">{{ $student->full_name }} · {{ $student->student_id }}</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('guardian.students.show', $student->id) }}"><i class="bi bi-arrow-left me-1"></i>{{ mawa_e('guardian.back_to_profile') }}</a>
</div>

@if ($documents->isEmpty())
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div>
            <div class="fw-semibold">{{ mawa_e('guardian.no_documents_title') }}</div>
            <div class="small">{{ mawa_e('guardian.no_documents_hint') }}</div>
        </div>
    </div>
@else
    <div class="row g-3">
        @foreach ($documents as $document)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <i class="bi bi-file-earmark-text fs-4 text-primary"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold text-truncate">{{ $document->title ?? $document->original_filename }}</div>
                                <div class="small text-body-secondary">
                                    {{ $document->category?->name ?? mawa_e('guardian.na') }}
                                    @if ($document->file_size)
                                        · {{ number_format((float) $document->file_size / 1024, 1) }} KB
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if ($document->description)
                            <p class="small text-body-secondary mb-2">{{ $document->description }}</p>
                        @endif
                        <div class="small text-body-secondary mb-3">
                            <i class="bi bi-clock me-1"></i>{{ $document->created_at?->format('d M Y') }}
                        </div>
                        <a class="btn btn-sm btn-outline-primary rounded-pill w-100" href="{{ route('guardian.students.documents.download', [$student->id, $document->id]) }}">
                            <i class="bi bi-download me-1"></i>{{ mawa_e('guardian.download') }}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection