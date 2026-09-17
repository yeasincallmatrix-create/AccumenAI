@extends('layouts.institute')

@section('title', 'Record Dental Procedure — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-tools"></i> Record Dental Procedure</h4>
        <a href="{{ route('medical.dental.procedures.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <form method="POST" action="{{ route('medical.dental.procedures.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Procedure Number</label>
                                <input type="text" class="form-control" value="{{ $procedureNumber }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Patient *</label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">Select Patient</option>
                                    @foreach($patients as $p)
                                        <option value="{{ $p->id }}" {{ old('patient_id') == $p->id ? 'selected' : '' }}>{{ $p->full_name }}</option>
                                    @endforeach
                                </select>
                                @error('patient_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dentist *</label>
                                <select name="dentist_id" class="form-select" required>
                                    <option value="">Select Dentist</option>
                                    @foreach($dentists as $d)
                                        <option value="{{ $d->id }}" {{ old('dentist_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                                @error('dentist_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $k => $v)
                                        <option value="{{ $k }}" {{ old('category') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Procedure Name *</label>
                                <input type="text" name="procedure_name" class="form-control" value="{{ old('procedure_name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Procedure Code</label>
                                <input type="text" name="procedure_code" class="form-control" value="{{ old('procedure_code') }}" placeholder="e.g. D2391">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tooth Number (FDI)</label>
                                <select name="tooth_number" class="form-select">
                                    <option value="">— None —</option>
                                    @foreach(\App\Models\Medical\DentalChart::TOOTH_NUMBERS as $t)
                                        <option value="{{ $t }}" {{ old('tooth_number') === $t ? 'selected' : '' }}>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tooth Surface</label>
                                <input type="text" name="tooth_surface" class="form-control" value="{{ old('tooth_surface') }}" placeholder="e.g. mesial, occlusal">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quadrant</label>
                                <select name="quadrant" class="form-select">
                                    <option value="">— Auto from tooth —</option>
                                    @foreach(\App\Models\Medical\DentalProcedure::QUADRANTS as $k => $v)
                                        <option value="{{ $k }}" {{ old('quadrant') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Diagnosis</label>
                                <textarea name="diagnosis" class="form-control" rows="2">{{ old('diagnosis') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Procedure Notes</label>
                                <textarea name="procedure_notes" class="form-control" rows="3">{{ old('procedure_notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Anesthesia</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="anesthesia_type" class="form-select">
                                <option value="">— None —</option>
                                @foreach($anesthesiaTypes as $k => $v)
                                    <option value="{{ $k }}" {{ old('anesthesia_type') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Agent</label>
                            <input type="text" name="anesthesia_agent" class="form-control" value="{{ old('anesthesia_agent') }}" placeholder="e.g. Lidocaine">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Volume (ml)</label>
                            <input type="number" name="anesthesia_volume_ml" class="form-control" value="{{ old('anesthesia_volume_ml') }}" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Timing & Billing</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Performed At *</label>
                            <input type="datetime-local" name="performed_at" class="form-control" value="{{ old('performed_at', now()->format('Y-m-d\TH:i')) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" class="form-control" value="{{ old('duration_minutes') }}" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fee</label>
                            <input type="number" name="fee" class="form-control" value="{{ old('fee') }}" step="0.01" min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Follow-up Date</label>
                            <input type="date" name="follow_up_date" class="form-control" value="{{ old('follow_up_date') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Follow-up Instructions</label>
                            <textarea name="follow_up_instructions" class="form-control" rows="2">{{ old('follow_up_instructions') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Medications</h6></div>
                    <div class="card-body">
                        <textarea name="medications_prescribed" class="form-control" rows="3" placeholder="Prescribed medications...">{{ old('medications_prescribed') }}</textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Record Procedure</button>
            </div>
        </div>
    </form>
</div>
@endsection
