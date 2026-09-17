@extends('layouts.institute')

@section('title', 'Edit Diet Template — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Edit {{ $template->name }}</h4>
        <a href="{{ route('medical.diet.templates.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.diet.templates.update', $template) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Diet Type *</label>
                        <select name="diet_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ $template->diet_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Calories</label>
                        <input type="number" name="total_calories" class="form-control" value="{{ old('total_calories', $template->total_calories) }}" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $template->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Meal Items (one per line)</label>
                        <textarea name="meal_items" class="form-control" rows="6">{{ old('meal_items', $template->meal_items ? implode("\n", $template->meal_items) : '') }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $template->is_active ? 'checked' : '' }}>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save</button>
            </form>
                    <form method="POST" action="{{ route('medical.diet.templates.destroy', $template) }}" onsubmit="return confirm('Delete this template?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">Delete</button>
                    </form>
                </div>
        </div>
    </div>
</div>
@endsection
