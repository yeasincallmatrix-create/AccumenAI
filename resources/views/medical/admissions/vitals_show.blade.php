@extends('layouts.institute')

@section('title', 'Vitals Entry — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Vitals Entry — {{ $admission->patient->full_name ?? 'N/A' }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.vitals.index', ['admission_id' => $admission->id]) }}">
            <i class="bi bi-arrow-left me-1"></i>History
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Recorded {{ $vital->recorded_at?->format('d M Y h:i A') }} by {{ $vital->recordedBy->name ?? 'Staff' }}</h6></div>
    <div class="card-body">
        <div class="row">
            @foreach([
                'Temperature (°C)' => $vital->temperature,
                'Blood Pressure' => $vital->blood_pressure,
                'Pulse (bpm)' => $vital->pulse,
                'Respiratory Rate (/min)' => $vital->respiratory_rate,
                'SpO2 (%)' => $vital->spo2,
                'Blood Sugar (mg/dL)' => $vital->blood_sugar,
                'Weight (kg)' => $vital->weight,
                'Height (cm)' => $vital->height,
                'BMI' => $vital->bmi,
            ] as $label => $value)
            <div class="col-md-4 mb-3">
                <strong>{{ $label }}:</strong> {{ $value ?? '—' }}
            </div>
            @endforeach
            <div class="col-md-12">
                <strong>Notes:</strong>
                <p class="mb-0">{{ $vital->notes ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
