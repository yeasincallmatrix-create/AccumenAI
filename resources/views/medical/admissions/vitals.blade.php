@extends('layouts.institute')

@section('title', 'Record Vitals — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Record Vitals — {{ $admission->patient->full_name ?? 'N/A' }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.admissions.show', $admission) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.vitals.store') }}" method="POST">
            @csrf
            <input type="hidden" name="admission_id" value="{{ $admission->id }}">

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="temperature">Temperature (°C)</label>
                        <input type="number" id="temperature" name="temperature" step="0.1" min="35" max="42"
                               class="form-control @error('temperature') is-invalid @enderror"
                               value="{{ old('temperature') }}" placeholder="e.g. 37.5">
                        @error('temperature')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="blood_pressure_systolic">BP Systolic</label>
                        <input type="number" id="blood_pressure_systolic" name="blood_pressure_systolic" min="60" max="250"
                               class="form-control @error('blood_pressure_systolic') is-invalid @enderror"
                               value="{{ old('blood_pressure_systolic') }}" placeholder="e.g. 120">
                        @error('blood_pressure_systolic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="blood_pressure_diastolic">BP Diastolic</label>
                        <input type="number" id="blood_pressure_diastolic" name="blood_pressure_diastolic" min="30" max="150"
                               class="form-control @error('blood_pressure_diastolic') is-invalid @enderror"
                               value="{{ old('blood_pressure_diastolic') }}" placeholder="e.g. 80">
                        @error('blood_pressure_diastolic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="pulse">Pulse (bpm)</label>
                        <input type="number" id="pulse" name="pulse" min="30" max="250"
                               class="form-control @error('pulse') is-invalid @enderror"
                               value="{{ old('pulse') }}" placeholder="e.g. 72">
                        @error('pulse')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="respiratory_rate">Respiratory Rate (/min)</label>
                        <input type="number" id="respiratory_rate" name="respiratory_rate" min="5" max="60"
                               class="form-control @error('respiratory_rate') is-invalid @enderror"
                               value="{{ old('respiratory_rate') }}" placeholder="e.g. 16">
                        @error('respiratory_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="spo2">SpO2 (%)</label>
                        <input type="number" id="spo2" name="spo2" min="70" max="100"
                               class="form-control @error('spo2') is-invalid @enderror"
                               value="{{ old('spo2') }}" placeholder="e.g. 98">
                        @error('spo2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="blood_sugar">Blood Sugar (mg/dL)</label>
                        <input type="number" id="blood_sugar" name="blood_sugar" step="0.1" min="20" max="500"
                               class="form-control @error('blood_sugar') is-invalid @enderror"
                               value="{{ old('blood_sugar') }}" placeholder="e.g. 110">
                        @error('blood_sugar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="weight">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" step="0.1" min="1" max="300"
                               class="form-control @error('weight') is-invalid @enderror"
                               value="{{ old('weight') }}" placeholder="e.g. 65">
                        @error('weight')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="height">Height</label>
                        <div class="input-group">
                            <input type="number" id="height" name="height" step="0.1" min="30" max="250"
                                   class="form-control @error('height') is-invalid @enderror"
                                   value="{{ old('height') }}" placeholder="e.g. 170">
                            <select id="height_unit" class="form-select" style="max-width:5.5rem;flex:0 0 auto;" aria-label="Height unit">
                                <option value="cm" selected>cm</option>
                                <option value="ft">ft</option>
                            </select>
                        </div>
                        @error('height')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text" id="height_hint"></div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Save Vitals
            </button>
            <a href="{{ route('medical.admissions.show', $admission) }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Height cm/ft toggle: the server stores centimetres, so feet are
// converted back to cm on submit. The hint shows the converted value live.
function bindHeightUnit(inputId, unitId, hintId) {
    var input = document.getElementById(inputId);
    var unit = document.getElementById(unitId);
    var hint = hintId ? document.getElementById(hintId) : null;
    if (!input || !unit) return;
    var FT = 30.48;
    function round(n, d) { var p = Math.pow(10, d); return (Math.round(n * p) / p).toString(); }
    function refreshHint() {
        if (!hint) return;
        var v = parseFloat(input.value);
        if (isNaN(v)) { hint.textContent = ''; return; }
        hint.textContent = unit.value === 'ft' ? '≈ ' + round(v * FT, 1) + ' cm' : '≈ ' + round(v / FT, 2) + ' ft';
    }
    function applyUnit() {
        var v = parseFloat(input.value);
        if (unit.value === 'ft') {
            if (!isNaN(v)) input.value = round(v / FT, 2);
            input.min = '1'; input.max = '9'; input.step = '0.01';
            input.placeholder = 'e.g. 5.58';
        } else {
            if (!isNaN(v)) input.value = round(v * FT, 1);
            input.min = '30'; input.max = '250'; input.step = '0.1';
            input.placeholder = 'e.g. 170';
        }
        refreshHint();
    }
    unit.addEventListener('change', applyUnit);
    input.addEventListener('input', refreshHint);
    var form = input.closest('form');
    if (form && !form.dataset.heightBound) {
        form.dataset.heightBound = '1';
        form.addEventListener('submit', function () {
            if (unit.value === 'ft') {
                var v = parseFloat(input.value);
                if (!isNaN(v)) input.value = round(v * FT, 1);
            }
        });
    }
    refreshHint();
}
bindHeightUnit('height', 'height_unit', 'height_hint');
</script>
@endpush
