@extends('layouts.institute')

@section('title', 'Edit Lab Test — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Test — {{ $test->display_name }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.lab.tests.show', $test) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.lab.tests.update', $test) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" id="code" name="code" maxlength="50"
                               class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code', $test->code) }}" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label" for="name">Test Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" maxlength="150"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $test->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="category">Category</label>
                        <input type="text" id="category" name="category" maxlength="50"
                               class="form-control @error('category') is-invalid @enderror"
                               value="{{ old('category', $test->category) }}">
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="unit">Unit</label>
                        <input type="text" id="unit" name="unit" maxlength="20"
                               class="form-control @error('unit') is-invalid @enderror"
                               value="{{ old('unit', $test->unit) }}">
                        @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="price">Price (৳) <span class="text-danger">*</span></label>
                        <input type="number" id="price" name="price" min="0" step="0.01"
                               class="form-control @error('price') is-invalid @enderror"
                               value="{{ old('price', $test->price) }}" required>
                        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="normal_range">Normal Range</label>
                        <input type="text" id="normal_range" name="normal_range"
                               class="form-control @error('normal_range') is-invalid @enderror"
                               value="{{ old('normal_range', $test->normal_range) }}">
                        @error('normal_range')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" rows="1"
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $test->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mb-3">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               class="form-check-input" @checked(old('is_active', $test->is_active))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Update Test
                </button>
                <a href="{{ route('medical.lab.tests.show', $test) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
