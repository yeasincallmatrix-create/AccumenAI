@extends('layouts.institute')

@section('title', 'New Exercise — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-plus-circle"></i> New Exercise</h4>
        <a href="{{ route('medical.physiotherapy.exercises.index') }}" class="btn btn-outline-secondary btn-sm">Back to List</a>
    </div>

    <form method="POST" action="{{ route('medical.physiotherapy.exercises.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-info-circle"></i> Exercise Information</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Name *</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category *</label>
                                <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                    <option value="">Select Category</option>
                                    @foreach($categories as $key => $label)
                                        <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Body Area *</label>
                                <select name="body_area" class="form-select @error('body_area') is-invalid @enderror" required>
                                    <option value="">Select Body Area</option>
                                    @foreach($bodyAreas as $key => $label)
                                        <option value="{{ $key }}" @selected(old('body_area') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('body_area')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Difficulty *</label>
                                <select name="difficulty" class="form-select @error('difficulty') is-invalid @enderror" required>
                                    <option value="">Select Difficulty</option>
                                    @foreach($difficulties as $key => $label)
                                        <option value="{{ $key }}" @selected(old('difficulty') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('difficulty')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', true))>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" placeholder="Describe the exercise...">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Instructions</label>
                                <textarea name="instructions" class="form-control @error('instructions') is-invalid @enderror" rows="3" placeholder="Step-by-step instructions...">{{ old('instructions') }}</textarea>
                                @error('instructions')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-list-ol"></i> Default Parameters</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Default Reps</label>
                                <input type="number" name="default_reps" class="form-control @error('default_reps') is-invalid @enderror" value="{{ old('default_reps') }}" min="1">
                                @error('default_reps')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Default Sets</label>
                                <input type="number" name="default_sets" class="form-control @error('default_sets') is-invalid @enderror" value="{{ old('default_sets') }}" min="1">
                                @error('default_sets')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Hold (seconds)</label>
                                <input type="number" name="default_hold_seconds" class="form-control @error('default_hold_seconds') is-invalid @enderror" value="{{ old('default_hold_seconds') }}" min="0">
                                @error('default_hold_seconds')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg"></i> Create Exercise
                </button>
                <a href="{{ route('medical.physiotherapy.exercises.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection