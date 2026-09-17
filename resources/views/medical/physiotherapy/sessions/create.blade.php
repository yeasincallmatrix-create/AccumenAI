@extends('layouts.institute')

@section('title', 'New Session — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-plus-circle"></i> New Session for Plan {{ $plan->plan_number }}</h4>
        <a href="{{ route('medical.physiotherapy.plans.show', $plan) }}" class="btn btn-outline-secondary btn-sm">Back to Plan</a>
    </div>

    <form method="POST" action="{{ route('medical.physiotherapy.sessions.store', $plan) }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-calendar-check"></i> Session # {{ $sessionNumber }} (Order: {{ $sessionOrder }})
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-person-badge"></i> Session Details</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Therapist *</label>
                                <select name="therapist_id" class="form-select @error('therapist_id') is-invalid @enderror" required>
                                    <option value="">Select Therapist</option>
                                    @foreach($therapists as $therapist)
                                        <option value="{{ $therapist->id }}" @selected(old('therapist_id', $plan->therapist_id) == $therapist->id)>{{ $therapist->name }}</option>
                                    @endforeach
                                </select>
                                @error('therapist_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Session Date *</label>
                                <input type="date" name="session_date" class="form-control @error('session_date') is-invalid @enderror" value="{{ old('session_date', now()->format('Y-m-d')) }}" required>
                                @error('session_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration (minutes) *</label>
                                <input type="number" name="duration_minutes" class="form-control @error('duration_minutes') is-invalid @enderror" value="{{ old('duration_minutes', 30) }}" min="1" required>
                                @error('duration_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-activity"></i> Pain & Fee</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Pain Score Before (0-10) *</label>
                                <input type="number" name="pain_score_before" class="form-control @error('pain_score_before') is-invalid @enderror" value="{{ old('pain_score_before') }}" min="0" max="10" required>
                                @error('pain_score_before')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fee</label>
                                <input type="number" name="fee" class="form-control @error('fee') is-invalid @enderror" value="{{ old('fee', $plan->fee_per_session) }}" min="0" step="0.01">
                                @error('fee')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg"></i> Create Session
                </button>
                <a href="{{ route('medical.physiotherapy.plans.show', $plan) }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection