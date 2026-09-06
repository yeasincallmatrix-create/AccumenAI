@extends('layouts.institute')

@section('title', 'Add Lab Test — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Add Lab Test</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.lab.tests.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.lab.tests.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" id="code" name="code" maxlength="50"
                               class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code') }}" required placeholder="e.g. LAB-010">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label" for="name">Test Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" maxlength="150"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="category">Category</label>
                        <input type="text" id="category" name="category" maxlength="50"
                               class="form-control @error('category') is-invalid @enderror"
                               value="{{ old('category') }}" placeholder="e.g. Biochemistry">
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="unit">Unit</label>
                        <input type="text" id="unit" name="unit" maxlength="20"
                               class="form-control @error('unit') is-invalid @enderror"
                               value="{{ old('unit') }}" placeholder="e.g. mg/dL">
                        @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="price">Price (৳) <span class="text-danger">*</span></label>
                        <input type="number" id="price" name="price" min="0" step="0.01"
                               class="form-control @error('price') is-invalid @enderror"
                               value="{{ old('price', 0) }}" required>
                        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="normal_range">Normal Range</label>
                        <input type="text" id="normal_range" name="normal_range"
                               class="form-control @error('normal_range') is-invalid @enderror"
                               value="{{ old('normal_range') }}" placeholder="e.g. 70-100 or < 5.7">
                        <div class="form-text">Formats understood by auto-interpretation: <code>min-max</code>, <code>&lt; value</code>, <code>&gt; value</code>.</div>
                        @error('normal_range')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" rows="1"
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mb-3">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               class="form-check-input" @checked(old('is_active', true))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Create Test
                </button>
                <a href="{{ route('medical.lab.tests.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
