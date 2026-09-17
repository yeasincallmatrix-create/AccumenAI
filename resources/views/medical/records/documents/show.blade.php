@extends('layouts.institute')

@section('title', 'Document Details — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi {{ $document->icon() }}"></i> {{ $document->title }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.records.documents.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            @if($user && $user->hasPermission('medical.records.document.download'))
                <a href="{{ route('medical.records.documents.preview', $document) }}" target="_blank" class="btn btn-outline-info btn-sm">Preview</a>
                <a href="{{ route('medical.records.documents.download', $document) }}" class="btn btn-outline-success btn-sm">Download</a>
            @endif
            <a href="{{ route('medical.records.documents.edit', $document) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Details</h6></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Document #</dt><dd class="col-sm-9">{{ $document->document_number }}</dd>
                        <dt class="col-sm-3">Type</dt><dd class="col-sm-9">{{ $document->documentTypeLabel() }}</dd>
                        <dt class="col-sm-3">Patient</dt><dd class="col-sm-9">{{ $document->patient->full_name ?? 'N/A' }}</dd>
                        <dt class="col-sm-3">Date</dt><dd class="col-sm-9">{{ $document->document_date?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $document->description ?? '—' }}</dd>
                        <dt class="col-sm-3">File</dt><dd class="col-sm-9">{{ $document->original_filename }} ({{ number_format($document->file_size / 1024, 1) }} KB, {{ $document->mime_type }})</dd>
                        <dt class="col-sm-3">Uploaded by</dt><dd class="col-sm-9">{{ $document->uploadedBy->name ?? 'N/A' }} on {{ $document->created_at->format('d M Y H:i') }}</dd>
                        <dt class="col-sm-3">Flags</dt>
                        <dd class="col-sm-9">
                            @if($document->is_confidential)<span class="badge bg-danger">Confidential</span>@endif
                            @if($document->is_patient_visible)<span class="badge bg-success">Patient visible</span>@endif
                            <span class="badge bg-secondary">{{ $document->access_level }}</span>
                        </dd>
                        @if($document->tags)
                            <dt class="col-sm-3">Tags</dt>
                            <dd class="col-sm-9">@foreach($document->tags as $tag)<span class="badge bg-light text-dark border">{{ $tag }}</span> @endforeach</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Danger Zone</h6></div>
                <div class="card-body">
                    @if($user && $user->hasPermission('medical.records.document.delete'))
                        <form method="POST" action="{{ route('medical.records.documents.destroy', $document) }}" onsubmit="return confirm('Delete this document?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Delete Document</button>
                        </form>
                    @else
                        <p class="text-muted small mb-0">You do not have delete permission.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
