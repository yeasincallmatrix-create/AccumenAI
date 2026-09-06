@extends('layouts.institute')

@section('title', 'Transfer Patient — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Transfer — {{ $admission->patient->full_name ?? 'N/A' }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.admissions.show', $admission) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            Currently in <strong>{{ $admission->bed->bed_number ?? 'no bed' }}</strong>
            @if($admission->bed?->ward)
                ({{ $admission->bed->ward->name }})
            @endif.
            Select the destination bed below.
        </div>

        <form action="{{ route('medical.admissions.transfer', $admission) }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="bed_id">Destination Bed <span class="text-danger">*</span></label>
                        <select id="bed_id" name="bed_id" class="form-select @error('bed_id') is-invalid @enderror" required>
                            <option value="">Select Bed</option>
                            @foreach($availableBeds as $bed)
                                <option value="{{ $bed->id }}" @selected((string) old('bed_id') === (string) $bed->id)>
                                    {{ $bed->bed_number }} — {{ $bed->ward->name ?? '' }} ({{ strtoupper($bed->ward->type ?? '') }})
                                </option>
                            @endforeach
                        </select>
                        @error('bed_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Transfer this patient to the selected bed?')">
                <i class="bi bi-arrow-left-right me-1"></i>Confirm Transfer
            </button>
            <a href="{{ route('medical.admissions.show', $admission) }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
