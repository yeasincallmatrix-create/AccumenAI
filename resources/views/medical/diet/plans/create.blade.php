@extends('layouts.institute')

@section('title', 'New Diet Plan — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-journal-plus"></i> New Diet Plan</h4>
        <a href="{{ route('medical.diet.plans.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small">Next number: <strong>{{ $planNumber }}</strong> (assigned on save). Meals auto-generate on save.</p>
            <form method="POST" action="{{ route('medical.diet.plans.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select patient...</option>
                            @foreach($patients as $p)
                                <option value="{{ $p->id }}" {{ (string) old('patient_id') === (string) $p->id ? 'selected' : '' }}>
                                    {{ $p->full_name ?? ($p->first_name . ' ' . $p->last_name) }} ({{ $p->mr_number ?? $p->id }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Plan Name *</label>
                        <input type="text" name="plan_name" class="form-control" value="{{ old('plan_name') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Diet Type *</label>
                        <select name="diet_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Days Planned</label>
                        <input type="number" name="days_planned" class="form-control" value="{{ old('days_planned', 7) }}" min="1" max="365">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Restrictions (allergies/preferences)</label>
                        <textarea name="restrictions" class="form-control" rows="2">{{ old('restrictions') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Medical Notes</label>
                        <textarea name="medical_notes" class="form-control" rows="2">{{ old('medical_notes') }}</textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Daily Calories</label>
                        <input type="number" name="daily_calories" class="form-control" value="{{ old('daily_calories') }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Protein (g)</label>
                        <input type="number" step="0.01" name="protein_grams" class="form-control" value="{{ old('protein_grams') }}" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Carbs (g)</label>
                        <input type="number" step="0.01" name="carbs_grams" class="form-control" value="{{ old('carbs_grams') }}" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fat (g)</label>
                        <input type="number" step="0.01" name="fat_grams" class="form-control" value="{{ old('fat_grams') }}" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fluid (ml)</label>
                        <input type="number" step="0.01" name="fluid_ml" class="form-control" value="{{ old('fluid_ml') }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sodium (mg)</label>
                        <input type="number" step="0.01" name="sodium_mg" class="form-control" value="{{ old('sodium_mg') }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Potassium (mg)</label>
                        <input type="number" step="0.01" name="potassium_mg" class="form-control" value="{{ old('potassium_mg') }}" min="0">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Create Plan & Generate Meals</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
