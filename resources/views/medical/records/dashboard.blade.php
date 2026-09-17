@extends('layouts.institute')

@section('title', 'Medical Records Dashboard — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-folder2-open"></i> Medical Records (EMR)</h4>
        <div class="d-flex gap-2">
            @if($user && $user->hasPermission('medical.records.document.upload'))
                <a href="{{ route('medical.records.documents.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-upload"></i> Upload Document
                </a>
            @endif
            @if($user && $user->hasPermission('medical.records.note.create'))
                <a href="{{ route('medical.records.notes.create') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-journal-plus"></i> New Note
                </a>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Patients with Records</h6>
                            <h2 class="mb-0 mt-1">{{ $stats['patients_with_records'] }}</h2>
                        </div>
                        <i class="bi bi-people fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Documents</h6>
                            <h2 class="mb-0 mt-1">{{ $stats['documents'] }}</h2>
                        </div>
                        <i class="bi bi-file-earmark-text fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Discharge Summaries</h6>
                            <h2 class="mb-0 mt-1">{{ $stats['summaries'] }}</h2>
                        </div>
                        <i class="bi bi-box-arrow-right fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Clinical Notes</h6>
                            <h2 class="mb-0 mt-1">{{ $stats['notes'] }}</h2>
                        </div>
                        <i class="bi bi-journal-text fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Events Today</h6>
                            <h2 class="mb-0 mt-1">{{ $stats['events_today'] }}</h2>
                        </div>
                        <i class="bi bi-clock-history fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Recent Activity</h6></div>
                <div class="card-body">
                    @forelse($recentActivity as $event)
                        <div class="d-flex gap-2 border-bottom py-2">
                            <span class="badge bg-{{ $event->severityColor() }} align-self-start mt-1">
                                <i class="bi {{ $event->icon() }}"></i>
                            </span>
                            <div class="flex-grow-1">
                                <strong>{{ $event->title }}</strong>
                                <br><small class="text-muted">
                                    {{ $event->patient->full_name ?? 'N/A' }} |
                                    {{ $event->eventTypeLabel() }} |
                                    {{ $event->event_at->format('d M Y H:i') }}
                                </small>
                            </div>
                            @if($event->patient)
                                <a href="{{ route('medical.records.patients.timeline', $event->patient) }}" class="btn btn-outline-primary btn-sm align-self-center">Timeline</a>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No recent activity. Use Backfill on a patient timeline to aggregate history.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Recent Documents</h6>
                    <a href="{{ route('medical.records.documents.index') }}" class="btn btn-link btn-sm">All</a>
                </div>
                <div class="card-body">
                    @forelse($recentDocuments as $doc)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $doc->document_number }}</strong> — {{ $doc->title }}
                                <br><small class="text-muted">{{ $doc->patient->full_name ?? 'N/A' }} | {{ $doc->documentTypeLabel() }}</small>
                            </div>
                            <a href="{{ route('medical.records.documents.show', $doc) }}" class="btn btn-outline-primary btn-sm">View</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No documents yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Upcoming Follow-ups (Discharge)</h6></div>
                <div class="card-body">
                    @forelse($pendingFollowUps as $s)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $s->summary_number }}</strong> — {{ $s->patient->full_name ?? 'N/A' }}
                                <br><small class="text-muted">Due: {{ $s->follow_up_date->format('d M Y') }}</small>
                            </div>
                            <a href="{{ route('medical.records.discharge-summaries.show', $s) }}" class="btn btn-outline-primary btn-sm">View</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No upcoming follow-ups.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
