@extends('layouts.institute')

@section('title', 'Clinical Note — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $note->note_number }} — {{ $note->noteTypeLabel() }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.records.notes.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            @if(! $note->is_signed)
                <a href="{{ route('medical.records.notes.edit', $note) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">SOAP Note</h6>
                    <div>
                        @if($note->is_signed)<span class="badge bg-success">Signed {{ $note->signed_at?->format('d M Y H:i') }}</span>
                        @else<span class="badge bg-warning">Draft</span>@endif
                        @if($note->is_amended)<span class="badge bg-info">Amended</span>@endif
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Patient: <strong>{{ $note->patient->full_name ?? 'N/A' }}</strong> | Author: {{ $note->author->name ?? 'N/A' }} | {{ $note->noted_at->format('d M Y H:i') }}</p>
                    @if($note->subjective)<h6>Subjective</h6><p>{{ $note->subjective }}</p>@endif
                    @if($note->objective)<h6>Objective</h6><p>{{ $note->objective }}</p>@endif
                    @if($note->assessment)<h6>Assessment</h6><p>{{ $note->assessment }}</p>@endif
                    @if($note->plan)<h6>Plan</h6><p>{{ $note->plan }}</p>@endif
                    @if($note->content)<h6>Content</h6><p>{{ $note->content }}</p>@endif
                    @if($note->addendum)<h6>Addendum</h6><p class="text-info">{{ $note->addendum }}</p>@endif
                    @if($note->amendment_reason)<p class="small text-muted">Amendment reason: {{ $note->amendment_reason }}</p>@endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            @if($user && $user->hasPermission('medical.records.note.sign'))
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Sign / Amend</h6></div>
                    <div class="card-body">
                        @if(! $note->is_signed)
                            <form method="POST" action="{{ route('medical.records.notes.sign', $note) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm w-100">Sign Note</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('medical.records.notes.amend', $note) }}">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label small">Amendment Reason *</label>
                                    <textarea name="amendment_reason" class="form-control form-control-sm" rows="2" required></textarea>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Addendum</label>
                                    <textarea name="addendum" class="form-control form-control-sm" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-outline-info btn-sm w-100">Amend Note</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Danger Zone</h6></div>
                <div class="card-body">
                    @if(! $note->is_signed)
                        <form method="POST" action="{{ route('medical.records.notes.destroy', $note) }}" onsubmit="return confirm('Delete this note?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Delete Note</button>
                        </form>
                    @else
                        <p class="text-muted small mb-0">Signed notes cannot be deleted.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
