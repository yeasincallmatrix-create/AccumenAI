@extends('layouts.institute')

@section('title', 'Edit Meal — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Edit {{ $meal->mealTypeLabel() }} — {{ $meal->meal_date->format('d M Y') }}</h4>
        <a href="{{ route('medical.diet.plans.meals.show', [$plan, $meal]) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.diet.plans.meals.update', [$plan, $meal]) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Meal Date *</label>
                        <input type="date" name="meal_date" class="form-control" value="{{ old('meal_date', $meal->meal_date->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Meal Type *</label>
                        <select name="meal_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ $meal->meal_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Scheduled Time *</label>
                        <input type="time" name="scheduled_time" class="form-control" value="{{ old('scheduled_time', $meal->scheduled_time ? \Carbon\Carbon::parse($meal->scheduled_time)->format('H:i') : '08:00') }}" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Menu Items *</label>
                        <textarea name="menu_items" class="form-control" rows="3" required>{{ old('menu_items', $meal->menu_items) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Calories</label>
                        <input type="number" name="calories" class="form-control" value="{{ old('calories', $meal->calories) }}" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $meal->notes) }}</textarea>
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
