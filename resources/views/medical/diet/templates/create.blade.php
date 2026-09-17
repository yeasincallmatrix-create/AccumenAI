@extends('layouts.institute')

@section('title', 'New Diet Template — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">New Diet Template</h4>
        <a href="{{ route('medical.diet.templates.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.diet.templates.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Diet Type *</label>
                        <select name="diet_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Calories</label>
                        <input type="number" name="total_calories" class="form-control" value="{{ old('total_calories') }}" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Meal Items (one per line)</label>
                        <textarea name="meal_items" class="form-control" rows="6" placeholder="Breakfast: Oats + milk&#10;Lunch: Rice + dal + vegetables">{{ old('meal_items') }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_global" value="1" class="form-check-input" id="global">
                            <label class="form-check-label" for="global">Global template (available to all institutes)</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Create Template</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
