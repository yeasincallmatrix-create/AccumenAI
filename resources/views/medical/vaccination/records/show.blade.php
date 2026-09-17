@extends('layouts.institute')

@section('title', 'Vaccination Record — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-journal-medical"></i> Vaccination Record: {{ $record->record_number }}</h4>
        <a href="{{ route('medical.vaccination.records.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Record #:</strong> {{ $record->record_number }}</div>
                        <div class="col-md-6"><strong>Patient:</strong> {{ $record->patient->full_name ?? 'N/A' }}</div>
                        <div class="col-md-4"><strong>Vaccine:</strong> {{ $record->vaccineMaster->name ?? 'N/A' }}</div>
                        <div class="col-md-4"><strong>Dose #:</strong> {{ $record->dose_number }}</div>
                        <div class="col-md-4"><strong>Date:</strong> {{ $record->administered_date->format('d M Y') }}</div>
                        <div class="col-md-4"><strong>Administered By:</strong> {{ $record->administeredBy->name ?? 'N/A' }}</div>
                        <div class="col-md-4"><strong>Site:</strong> {{ $record->site ?? '—' }}</div>
                        <div class="col-md-4"><strong>Route:</strong> {{ $record->route ?? '—' }}</div>
                        <div class="col-md-4"><strong>Dose Volume:</strong> {{ $record->dose_volume ?? '—' }}</div>
                        <div class="col-md-4"><strong>Batch:</strong> {{ $record->batch_number ?? '—' }}</div>
                        <div class="col-md-4"><strong>Manufacturer:</strong> {{ $record->manufacturer ?? '—' }}</div>
                        <div class="col-md-4"><strong>Fee:</strong> {{ number_format($record->fee, 2) }}</div>
                        @if($record->pre_vaccination_notes)
                            <div class="col-12"><strong>Pre-Vaccination Notes:</strong> {{ $record->pre_vaccination_notes }}</div>
                        @endif
                        @if($record->post_vaccination_notes)
                            <div class="col-12"><strong>Post-Vaccination Notes:</strong> {{ $record->post_vaccination_notes }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Adverse Event</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Event:</strong> <span class="badge bg-{{ $record->statusColor() }}">{{ $record->adverseEventLabel() }}</span></div>
                        @if($record->adverse_event_details)
                            <div class="col-12"><strong>Details:</strong> {{ $record->adverse_event_details }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Certificate</h6></div>
                <div class="card-body">
                    @if($record->certificate_number)
                        <div class="text-center">
                            <h5 class="text-success">{{ $record->certificate_number }}</h5>
                            <small class="text-muted">Issued: {{ $record->certificate_issued_at?->format('d M Y H:i') ?? 'N/A' }}</small>
                        </div>
                    @else
                        <p class="text-muted text-center mb-0">No certificate issued.</p>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Schedule Info</h6></div>
                <div class="card-body">
                    @if($record->schedule)
                        <div><strong>Schedule:</strong> <a href="{{ route('medical.vaccination.schedules.show', $record->schedule) }}">View</a></div>
                    @endif
                    @if($record->next_dose_due)
                        <div><strong>Next Dose Due:</strong> {{ $record->next_dose_due->format('d M Y') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
