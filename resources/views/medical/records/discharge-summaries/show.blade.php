@extends('layouts.institute')

@section('title', 'Discharge Summary — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Discharge Summary {{ $summary->summary_number }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.records.discharge-summaries.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('medical.records.discharge-summaries.pdf', $summary) }}" target="_blank" class="btn btn-outline-info btn-sm">Print / PDF</a>
            <a href="{{ route('medical.records.discharge-summaries.edit', $summary) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Patient:</strong> {{ $summary->patient->full_name ?? 'N/A' }}<br>
                    <strong>Admission:</strong> {{ $summary->admission_date->format('d M Y') }} |
                    <strong>Discharge:</strong> {{ $summary->discharge_date->format('d M Y') }} |
                    <strong>LOS:</strong> {{ $summary->length_of_stay_days }} days
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="badge bg-{{ $summary->conditionColor() }} fs-6">{{ ucfirst(str_replace('_', ' ', $summary->condition_on_discharge)) }}</span><br>
                    <small class="text-muted">Prepared by {{ $summary->preparedBy->name ?? 'N/A' }}</small>
                </div>
            </div>
            <hr>
            <h6>Admission Diagnosis</h6><p>{{ $summary->admission_diagnosis }}</p>
            @if($summary->final_diagnosis)<h6>Final Diagnosis</h6><p>{{ $summary->final_diagnosis }}</p>@endif
            <h6>Hospital Course</h6><p>{{ $summary->hospital_course }}</p>
            @if($summary->procedures_done)<h6>Procedures Done</h6><p>{{ $summary->procedures_done }}</p>@endif
            @if($summary->investigations_summary)<h6>Investigations</h6><p>{{ $summary->investigations_summary }}</p>@endif
            @if($summary->treatment_given)<h6>Treatment Given</h6><p>{{ $summary->treatment_given }}</p>@endif
            <h6>Discharge Medications</h6><p>{{ $summary->discharge_medications }}</p>
            <h6>Discharge Instructions</h6><p>{{ $summary->discharge_instructions }}</p>
            @if($summary->diet_instructions)<h6>Diet</h6><p>{{ $summary->diet_instructions }}</p>@endif
            @if($summary->activity_restrictions)<h6>Activity Restrictions</h6><p>{{ $summary->activity_restrictions }}</p>@endif
            @if($summary->follow_up_date)
                <h6>Follow-up</h6>
                <p>{{ $summary->follow_up_date->format('d M Y') }}
                    @if($summary->follow_up_department) ({{ $summary->follow_up_department }}) @endif
                    {{ $summary->follow_up_instructions ?? '' }}
                    @if($summary->isFollowUpDue())<span class="badge bg-warning">Due</span>@endif
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
