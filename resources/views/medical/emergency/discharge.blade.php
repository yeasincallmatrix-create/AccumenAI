@extends('layouts.institute')

@section('title', 'Discharge — ' . $visit->visit_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-check-circle"></i> Discharge — {{ $visit->visit_number }}</h4>
        <a href="{{ route('medical.emergency.show', $visit) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <form method="POST" action="{{ route('medical.emergency.discharge', $visit) }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">Patient: {{ $visit->patientDisplayName() }}</div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr><th width="120">Visit #</th><td>{{ $visit->visit_number }}</td></tr>
                            <tr><th>Arrived</th><td>{{ $visit->arrived_at?->format('d M Y H:i') }}</td></tr>
                            <tr><th>Triage</th><td>{{ strtoupper($visit->triage_level ?? '?') }}</td></tr>
                            <tr><th>Complaint</th><td>{{ $visit->chief_complaint ?? '-' }}</td></tr>
                            <tr><th>Doctor</th><td>{{ $visit->attendingDoctor?->name ?? '-' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><strong>Discharge Details</strong></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Disposition *</label>
                                <select name="disposition" class="form-select" required>
                                    <option value="">Select Disposition</option>
                                    <option value="discharge" @selected(old('disposition') === 'discharge')>Discharge Home</option>
                                    <option value="admit" @selected(old('disposition') === 'admit')>Admit to IPD</option>
                                    <option value="transfer" @selected(old('disposition') === 'transfer')>Transfer to Another Facility</option>
                                    <option value="observation" @selected(old('disposition') === 'observation')>Observation</option>
                                    <option value="left_without" @selected(old('disposition') === 'left_without')>Left Without Treatment</option>
                                    <option value="expired" @selected(old('disposition') === 'expired')>Expired</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Treatment Given</label>
                                <textarea name="treatment_given" class="form-control" rows="3" placeholder="Summary of treatment provided...">{{ old('treatment_given', $visit->treatment_given) }}</textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Discharge Notes</label>
                                <textarea name="disposition_notes" class="form-control" rows="3" placeholder="Additional notes...">{{ old('disposition_notes', $visit->disposition_notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-circle"></i> Confirm Discharge</button>
                        <a href="{{ route('medical.emergency.show', $visit) }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
