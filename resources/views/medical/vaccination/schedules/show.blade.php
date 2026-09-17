@extends('layouts.institute')

@section('title', 'Schedule Details — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Vaccination Schedule</h4>
        <div class="d-flex gap-2">
            @if($schedule->status === 'scheduled' && $user?->hasPermission('medical.vaccination.administer'))
                <a href="{{ route('medical.vaccination.schedules.administer', $schedule) }}" class="btn btn-primary btn-sm"><i class="bi bi-syringe"></i> Administer</a>
            @endif
            <a href="{{ route('medical.vaccination.schedules.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Patient:</strong> {{ $schedule->patient->full_name ?? 'N/A' }}</div>
                        <div class="col-md-6"><strong>Vaccine:</strong> {{ $schedule->vaccineMaster->name ?? 'N/A' }}</div>
                        <div class="col-md-4"><strong>Dose #:</strong> {{ $schedule->dose_number }}</div>
                        <div class="col-md-4"><strong>Due Date:</strong> {{ $schedule->due_date->format('d M Y') }}</div>
                        <div class="col-md-4"><strong>Status:</strong> <span class="badge bg-{{ $schedule->statusColor() }}">{{ $schedule->statusLabel() }}</span></div>
                        @if($schedule->given_date)
                            <div class="col-md-4"><strong>Given Date:</strong> {{ $schedule->given_date->format('d M Y') }}</div>
                        @endif
                        @if($schedule->notes)
                            <div class="col-12"><strong>Notes:</strong> {{ $schedule->notes }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Vaccination Records</h6></div>
                <div class="card-body">
                    @forelse($schedule->records as $record)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $record->record_number }}</strong>
                                <br><small class="text-muted">{{ $record->administered_date->format('d M Y') }} | {{ $record->administeredBy->name ?? 'N/A' }}</small>
                            </div>
                            <a href="{{ route('medical.vaccination.records.show', $record) }}" class="btn btn-outline-primary btn-sm">View</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No records yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
