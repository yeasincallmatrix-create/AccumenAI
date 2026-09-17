@extends('layouts.institute')

@section('title', 'Edit Diet Plan — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Edit {{ $plan->plan_number }}</h4>
        <a href="{{ route('medical.diet.plans.show', $plan) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.diet.plans.update', $plan) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Plan Name *</label>
                        <input type="text" name="plan_name" class="form-control" value="{{ old('plan_name', $plan->plan_name) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Diet Type *</label>
                        <select name="diet_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ $plan->diet_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" {{ $plan->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $plan->end_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Daily Calories</label>
                        <input type="number" name="daily_calories" class="form-control" value="{{ old('daily_calories', $plan->daily_calories) }}" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Protein (g)</label>
                        <input type="number" step="0.01" name="protein_grams" class="form-control" value="{{ old('protein_grams', $plan->protein_grams) }}" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Carbs (g)</label>
                        <input type="number" step="0.01" name="carbs_grams" class="form-control" value="{{ old('carbs_grams', $plan->carbs_grams) }}" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fat (g)</label>
                        <input type="number" step="0.01" name="fat_grams" class="form-control" value="{{ old('fat_grams', $plan->fat_grams) }}" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Restrictions</label>
                        <textarea name="restrictions" class="form-control" rows="2">{{ old('restrictions', $plan->restrictions) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Medical Notes</label>
                        <textarea name="medical_notes" class="form-control" rows="2">{{ old('medical_notes', $plan->medical_notes) }}</textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sodium (mg)</label>
                        <input type="number" step="0.01" name="sodium_mg" class="form-control" value="{{ old('sodium_mg', $plan->sodium_mg) }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Potassium (mg)</label>
                        <input type="number" step="0.01" name="potassium_mg" class="form-control" value="{{ old('potassium_mg', $plan->potassium_mg) }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fluid (ml)</label>
                        <input type="number" step="0.01" name="fluid_ml" class="form-control" value="{{ old('fluid_ml', $plan->fluid_ml) }}" min="0">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
