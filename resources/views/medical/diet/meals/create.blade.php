@extends('layouts.institute')

@section('title', 'Add Meal — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Add Meal — {{ $plan->plan_number }}</h4>
        <a href="{{ route('medical.diet.plans.show', $plan) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.diet.plans.meals.store', $plan) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Meal Date *</label>
                        <input type="date" name="meal_date" class="form-control" value="{{ old('meal_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Meal Type *</label>
                        <select name="meal_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Scheduled Time *</label>
                        <input type="time" name="scheduled_time" class="form-control" value="{{ old('scheduled_time', '08:00') }}" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Menu Items *</label>
                        <textarea name="menu_items" class="form-control" rows="3" required>{{ old('menu_items') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Calories</label>
                        <input type="number" name="calories" class="form-control" value="{{ old('calories') }}" min="0">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Schedule Meal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
