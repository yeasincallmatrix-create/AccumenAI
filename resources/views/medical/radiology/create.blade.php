@extends('layouts.institute')

@section('title', 'New Radiology Order — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-plus-circle"></i> New Radiology Order</h4>
        <a href="{{ route('medical.radiology.orders.index') }}" class="btn btn-outline-secondary btn-sm">Back to List</a>
    </div>

    <form method="POST" action="{{ route('medical.radiology.orders.store') }}">
        @csrf

        <div class="row g-3">
            <!-- Order Number -->
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-radioactive"></i> Order # {{ $orderNumber }}
                    </div>
                </div>
            </div>

            <!-- Patient & Referring Doctor -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-person"></i> Patient & Referring Doctor</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Patient ID *</label>
                                <input type="number" name="patient_id" class="form-control" value="{{ old('patient_id') }}" required placeholder="Enter patient ID">
                                @error('patient_id')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Referring Doctor</label>
                                <select name="doctor_id" class="form-select">
                                    <option value="">Select Doctor</option>
                                    @foreach($doctors as $doctor)
                                        <option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Fee</label>
                                <input type="number" name="fee" class="form-control" value="{{ old('fee', '0') }}" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Study Details -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-body-text"></i> Study Details</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Modality *</label>
                                <select name="modality" class="form-select" required>
                                    <option value="">Select Modality</option>
                                    @foreach($modalities as $key => $label)
                                        <option value="{{ $key }}" @selected(old('modality') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('modality')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Body Part *</label>
                                <select name="body_part" class="form-select" required>
                                    <option value="">Select Body Part</option>
                                    @foreach($bodyParts as $part)
                                        <option value="{{ $part }}" @selected(old('body_part') === $part)>{{ $part }}</option>
                                    @endforeach
                                </select>
                                @error('body_part')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Laterality</label>
                                <select name="laterality" class="form-select">
                                    <option value="">Not Applicable</option>
                                    <option value="left" @selected(old('laterality') === 'left')>Left</option>
                                    <option value="right" @selected(old('laterality') === 'right')>Right</option>
                                    <option value="bilateral" @selected(old('laterality') === 'bilateral')>Bilateral</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Scheduled At</label>
                                <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ old('scheduled_at') }}">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Clinical Indication</label>
                                <textarea name="clinical_indication" class="form-control" rows="3" placeholder="Reason for study, symptoms, clinical history...">{{ old('clinical_indication') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flags -->
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-flag"></i> Flags</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_contrast" value="1" class="form-check-input" id="is_contrast" @checked(old('is_contrast'))>
                                    <label class="form-check-label" for="is_contrast">Contrast Required</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="contrast_type" class="form-control" value="{{ old('contrast_type') }}" placeholder="Contrast type (if applicable)">
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_urgent" value="1" class="form-check-input" id="is_urgent" @checked(old('is_urgent'))>
                                    <label class="form-check-label" for="is_urgent">Urgent</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_fasting_required" value="1" class="form-check-input" id="is_fasting_required" @checked(old('is_fasting_required'))>
                                    <label class="form-check-label" for="is_fasting_required">Fasting Required</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg"></i> Create Order
                </button>
                <a href="{{ route('medical.radiology.orders.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
