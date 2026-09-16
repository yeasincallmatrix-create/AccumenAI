@extends('layouts.institute')

@section('title', 'Triage — ' . $visit->visit_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-clipboard2-pulse"></i> Triage — {{ $visit->visit_number }}</h4>
        <a href="{{ route('medical.emergency.show', $visit) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <form method="POST" action="{{ route('medical.emergency.triage', $visit) }}">
                @csrf
                <div class="card shadow-sm">
                    <div class="card-header bg-warning"><strong>Assign Triage Level</strong></div>
                    <div class="card-body">
                        @if($suggested)
                            <div class="alert alert-info mb-3">
                                <strong>System Suggestion:</strong> <span class="badge bg-{{ match($suggested) {
                                    'red' => 'danger', 'orange' => 'warning', 'yellow' => 'info',
                                    'green' => 'success', default => 'secondary',
                                } }}">{{ strtoupper($suggested) }}</span>
                                {{ \App\Models\Medical\EmergencyVisit::TRIAGE_LEVELS[$suggested] ?? '' }}
                            </div>
                        @endif

                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Triage Level *</label>
                                <select name="triage_level" class="form-select" required>
                                    @foreach($triageLevels as $key => $label)
                                        <option value="{{ $key }}" @selected(old('triage_level', $visit->triage_level) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('triage_level')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg"></i> Apply Triage</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">Vitals (Auto-populate if available)</div>
                <div class="card-body">
                    <form id="vitalsForm">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Systolic BP</label>
                                <input type="number" name="vitals_snapshot[systolic_bp]" class="form-control" value="{{ old('vitals_snapshot.systolic_bp', $visit->vitals_snapshot['systolic_bp'] ?? '') }}" placeholder="mmHg">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Diastolic BP</label>
                                <input type="number" name="vitals_snapshot[diastolic_bp]" class="form-control" value="{{ old('vitals_snapshot.diastolic_bp', $visit->vitals_snapshot['diastolic_bp'] ?? '') }}" placeholder="mmHg">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Heart Rate</label>
                                <input type="number" name="vitals_snapshot[heart_rate]" class="form-control" value="{{ old('vitals_snapshot.heart_rate', $visit->vitals_snapshot['heart_rate'] ?? '') }}" placeholder="bpm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Resp Rate</label>
                                <input type="number" name="vitals_snapshot[respiratory_rate]" class="form-control" value="{{ old('vitals_snapshot.respiratory_rate', $visit->vitals_snapshot['respiratory_rate'] ?? '') }}" placeholder="/min">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Temperature</label>
                                <input type="number" name="vitals_snapshot[temperature]" class="form-control" value="{{ old('vitals_snapshot.temperature', $visit->vitals_snapshot['temperature'] ?? '') }}" step="0.1" placeholder="°C">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SpO2</label>
                                <input type="number" name="vitals_snapshot[spo2]" class="form-control" value="{{ old('vitals_snapshot.spo2', $visit->vitals_snapshot['spo2'] ?? '') }}" min="0" max="100" placeholder="%">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Weight</label>
                                <input type="number" name="vitals_snapshot[weight]" class="form-control" value="{{ old('vitals_snapshot.weight', $visit->vitals_snapshot['weight'] ?? '') }}" step="0.1" placeholder="kg">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
