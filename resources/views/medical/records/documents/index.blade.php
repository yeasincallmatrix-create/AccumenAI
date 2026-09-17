@extends('layouts.institute')

@section('title', 'Medical Documents — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-file-earmark-text"></i> Medical Documents</h4>
        @if($user && $user->hasPermission('medical.records.document.upload'))
            <a href="{{ route('medical.records.documents.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-upload"></i> Upload Document
            </a>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title/number/patient..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="document_type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ request('document_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Doc #</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Patient</th>
                            <th>Date</th>
                            <th>Size</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                            <tr>
                                <td><strong>{{ $doc->document_number }}</strong></td>
                                <td>
                                    <i class="bi {{ $doc->icon() }}"></i> {{ $doc->title }}
                                    @if($doc->is_confidential)<span class="badge bg-danger">Confidential</span>@endif
                                </td>
                                <td>{{ $doc->documentTypeLabel() }}</td>
                                <td>{{ $doc->patient->full_name ?? 'N/A' }}</td>
                                <td>{{ $doc->document_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ number_format($doc->file_size / 1024, 1) }} KB</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('medical.records.documents.show', $doc) }}" class="btn btn-outline-primary btn-sm">View</a>
                                    @if($user && $user->hasPermission('medical.records.document.download'))
                                        <a href="{{ route('medical.records.documents.download', $doc) }}" class="btn btn-outline-success btn-sm">Download</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No documents found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $documents->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
