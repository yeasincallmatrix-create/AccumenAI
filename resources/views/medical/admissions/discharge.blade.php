@extends('layouts.institute')

@section('title', 'Discharge Patient — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Discharge — {{ $admission->patient->full_name ?? 'N/A' }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.admissions.show', $admission) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Stay Summary</h6></div>
            <div class="card-body">
                <p><strong>Admitted:</strong> <x-tdate :value="$admission->admission_date" fallback="d M Y" /></p>
                <p><strong>Bed:</strong>
                    @if($admission->bed)
                        {{ $admission->bed->bed_number }} ({{ $admission->bed->ward->name ?? '' }})
                    @else
                        <span class="text-muted">No bed</span>
                    @endif
                </p>
                <p><strong>Length of Stay:</strong> {{ $admission->length_of_stay }} day(s)</p>
                <p class="mb-0"><strong>Vitals Recorded:</strong> {{ $vitals->count() }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Discharge Details</h6></div>
            <div class="card-body">
                <form action="{{ route('medical.admissions.discharge', $admission) }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="discharge_date">Discharge Date <span class="text-danger">*</span></label>
                                <x-tdate-input name="discharge_date" :value="old('discharge_date', date('Y-m-d'))" id="discharge_date" :class="'form-control'.($errors->has('discharge_date') ? ' is-invalid' : '')" required />
                                @error('discharge_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="discharge_time">Discharge Time <span class="text-danger">*</span></label>
                                <input type="time" id="discharge_time" name="discharge_time"
                                       class="form-control @error('discharge_time') is-invalid @enderror"
                                       value="{{ old('discharge_time', date('H:i')) }}" required>
                                @error('discharge_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="discharge_summary">Discharge Summary</label>
                                <textarea id="discharge_summary" name="discharge_summary" rows="5"
                                          class="form-control @error('discharge_summary') is-invalid @enderror"
                                          placeholder="Condition on discharge, treatment given, follow-up advice...">{{ old('discharge_summary', $admission->discharge_summary) }}</textarea>
                                @error('discharge_summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Discharging will release
                        <strong>{{ $admission->bed->bed_number ?? 'the assigned bed' }}</strong>
                        back to the available pool.
                    </div>
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('Confirm discharge of this patient?')">
                        <i class="bi bi-box-arrow-right me-1"></i>Confirm Discharge
                    </button>
                    <a href="{{ route('medical.admissions.show', $admission) }}" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
