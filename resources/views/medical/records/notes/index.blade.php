@extends('layouts.institute')

@section('title', 'Clinical Notes — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-journal-text"></i> Clinical Notes</h4>
        @if($user && $user->hasPermission('medical.records.note.create'))
            <a href="{{ route('medical.records.notes.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> New Note
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
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search number/assessment..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="note_type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ request('note_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
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
                            <th>Note #</th>
                            <th>Type</th>
                            <th>Patient</th>
                            <th>Author</th>
                            <th>Noted At</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notes as $note)
                            <tr>
                                <td><strong>{{ $note->note_number }}</strong></td>
                                <td>{{ $note->noteTypeLabel() }}</td>
                                <td>{{ $note->patient->full_name ?? 'N/A' }}</td>
                                <td>{{ $note->author->name ?? 'N/A' }}</td>
                                <td>{{ $note->noted_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($note->is_signed)<span class="badge bg-success">Signed</span>@else<span class="badge bg-warning">Draft</span>@endif
                                    @if($note->is_amended)<span class="badge bg-info">Amended</span>@endif
                                </td>
                                <td>
                                    <a href="{{ route('medical.records.notes.show', $note) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No clinical notes found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $notes->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
